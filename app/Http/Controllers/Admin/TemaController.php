<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeVersion;
use App\Models\User;
use App\Support\Theme;
use App\Support\ThemeContrastException;
use App\Support\ThemeEditorSpec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

/**
 * Az Arculat-szerkesztő (/admin/tema) szerver-oldala. Az élő előnézet a böngészőben fut (szerver
 * nélkül); ide CSAK mentéskor, publikáláskor és feltöltéskor fordul a felület.
 * Jog: UserRole::canEditTheme (szuperadmin, arculat-szerkesztő) — minden végponton.
 * Hiba soha nem néma: minden elutasítás emberi nyelvű üzenettel jön vissza (422).
 */
class TemaController extends Controller
{
    /** Az előnézet lapja (iframe). A téma-CSS-t a szerkesztő írja bele élőben — itt nincs téma-link. */
    public function elonezet(): View
    {
        $this->jog();

        return view('tema.elonezet');
    }

    public function vazlat(Request $r): JsonResponse
    {
        $u = $this->jog();

        return $this->probal(function () use ($r, $u) {
            $in = self::nyers($r);
            Theme::saveDraft((array) ($in['data'] ?? []), $u, (int) ($in['base_version_id'] ?? 0));

            return ['ok' => true, 'draft' => Theme::draft()];
        });
    }

    public function vazlatEldob(): JsonResponse
    {
        $this->jog();
        Theme::discardDraft();

        return response()->json(['ok' => true]);
    }

    public function publikalas(Request $r): JsonResponse
    {
        $u = $this->jog();

        return $this->probal(function () use ($r, $u) {
            $in = self::nyers($r);
            $v = Theme::publish((array) ($in['data'] ?? []), $u, (string) ($in['comment'] ?? ''), null, isset($in['override']) ? (string) $in['override'] : null);

            return ['ok' => true, 'version' => self::verzioSor($v, $v->id), 'css_url' => asset(Theme::DISK . '/' . $v->css_file)];
        });
    }

    public function feltoltes(Request $r): JsonResponse
    {
        $this->jog();
        $path = (string) $r->input('path');
        $f = $r->file('file');

        return $this->probal(function () use ($path, $f) {
            if ($f === null || ! $f->isValid()) {
                throw new RuntimeException('A fájl nem érkezett meg (túl nagy, vagy megszakadt a feltöltés).');
            }
            if ($f->getSize() > 5 * 1024 * 1024) {
                throw new RuntimeException('A fájl túl nagy (legfeljebb 5 MB).');
            }
            $ext = strtolower($f->getClientOriginalExtension());
            if (preg_match('/^doodles\.motifs\.([0-5])$/', $path, $m)) {
                [$slot, $exts] = ['firka-' . ($m[1] + 1), ['svg']];
            } elseif (isset(ThemeEditorSpec::MEDIA[$path])) {
                [$slot, $exts] = [ThemeEditorSpec::MEDIA[$path]['slot'], ThemeEditorSpec::MEDIA[$path]['ext']];
            } else {
                throw new RuntimeException('Ismeretlen arculati hely.');
            }
            if (! in_array($ext, $exts, true)) {
                throw new RuntimeException('Ide csak ' . implode(', ', $exts) . ' fájl tölthető fel.');
            }
            if ($ext !== 'svg' && $ext !== 'ico' && @getimagesize($f->getRealPath()) === false) {
                throw new RuntimeException('A fájl nem olvasható képként.');
            }
            // A Theme::storeAsset a kiterjesztést a forrásnévből olvassa — az ideiglenes fájlnak nincs.
            $tmp = $f->getRealPath() . '.' . $ext;
            copy($f->getRealPath(), $tmp);
            try {
                $stored = Theme::storeAsset($tmp, $slot);
            } finally {
                @unlink($tmp);
            }

            return ['ok' => true, 'path' => $stored, 'url' => asset(Theme::DISK . '/' . $stored),
                'svg' => $ext === 'svg' ? Theme::sanitizeSvg((string) Storage::disk(Theme::DISK)->get($stored)) : null];
        });
    }

    public function gyari(): JsonResponse
    {
        $this->jog();

        return $this->probal(fn () => ['ok' => true, 'data' => Theme::factoryData()]);
    }

    public function verzio(int $version): JsonResponse
    {
        $this->jog();
        $v = ThemeVersion::findOrFail($version);

        return response()->json(['ok' => true, 'data' => $v->data, 'version' => self::verzioSor($v, Theme::current()->id)]);
    }

    /** Egy verzió-sor a felületnek (előzmény-lista). @return array<string, mixed> */
    public static function verzioSor(ThemeVersion $v, int $kintId): array
    {
        return [
            'id' => $v->id,
            'at' => $v->created_at?->timezone('Europe/Bucharest')->format('Y-m-d H:i'),
            'by' => $v->published_by,
            'comment' => $v->comment,
            'override' => $v->contrast_override,
            'current' => $v->id === $kintId,
        ];
    }

    /**
     * A kérés NYERS JSON-tartalma. A Laravel köztes rétege (ConvertEmptyStringsToNull) az üres
     * szöveget null-ra cserélné — a témában viszont az üres szöveg érvényes érték (pl. üres
     * TikTok-link = nincs ikon), a null pedig hiba. Ezért itt nem a feldolgozott bemenetet olvassuk.
     *
     * @return array<string, mixed>
     */
    private static function nyers(Request $r): array
    {
        $d = json_decode((string) $r->getContent(), true);
        if (! is_array($d)) {
            throw new RuntimeException('A kérés nem érvényes JSON.');
        }

        return $d;
    }

    private function jog(): User
    {
        $u = auth('web')->user();
        abort_unless($u instanceof User && $u->role->canEditTheme(), 403);

        return $u;
    }

    private function probal(callable $f): JsonResponse
    {
        try {
            return response()->json($f());
        } catch (ThemeContrastException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage(), 'contrast' => $e->failures], 422);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
