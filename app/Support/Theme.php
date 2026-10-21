<?php

namespace App\Support;

use App\Models\ThemeVersion;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * A téma (arculat) — EGYETLEN forrás minden szín, betű, forma, mozgás és firka számára.
 *
 * · Az adat: a `theme_versions` tábla LEGUTOLSÓ sora (append-only; egy publikálás = egy új sor).
 * · A CSS: ThemeCss::generate() állítja elő, és fájlba íródik: public/arculat/tema-<lenyomat>.css.
 *   A lenyomat a CSS TARTALMÁBÓL számol, ezért bármilyen változás (téma-adat VAGY a generátor kódja)
 *   új fájlnevet ad → a böngésző gyorsítótára magától érvénytelenül, kézi verziószám nélkül.
 * · A fájlok (logó, fejléc-logó, favicon, a hat firka-motívum) VALÓDI feltöltött fájlok a
 *   public/arculat/fajlok/ mappában, szintén lenyomatos névvel. Beépített tartalék NINCS (CLAUDE.md 2/c):
 *   ha valami hiányzik, a kiszolgálás hibával áll meg, nem csúszik át csendben egy kódbeli alapértékre.
 * · Téma nélkül nincs kiszolgálás: a current() hibát dob (token-szabály, CLAUDE.md 3.).
 */
class Theme
{
    public const DISK = 'arculat';

    /** Arculati fájl-mezők (assets.*) — mind kötelező. */
    public const ASSET_KEYS = ['logo', 'logoHeader', 'favicon'];

    /** A firka-motívumok helyeinek száma (doodles.motifs). */
    public const MOTIF_SLOTS = 6;

    /** Ezekre a címekre a témában semmi nem mutathat (a NAS-on futó 2026-os rendszer). */
    public const TILTOTT_CIMEK = ['admin.forgatag.ro', 'elo.forgatag.ro'];

    private const KEPEK = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'ico'];

    private static ?ThemeVersion $current = null;

    /** @var array{name: string, css: string}|null */
    private static ?array $css = null;

    public static function current(): ThemeVersion
    {
        return self::$current ??= ThemeVersion::query()->latest('id')->first()
            ?? throw new RuntimeException('Nincs publikált téma — a kiszolgálás megáll (a kezdő témát a „php artisan forgatag:torzsadatok” tölti be).');
    }

    /** @return array<string, mixed> */
    public static function data(): array
    {
        return self::current()->data;
    }

    public static function get(string $path): mixed
    {
        return data_get(self::data(), $path);
    }

    /** A generált téma-CSS címe. Ha a fájl hiányzik (pl. új generátor-kód), itt íródik ki. */
    public static function cssUrl(): string
    {
        return asset(self::DISK . '/' . self::ensureCss());
    }

    /**
     * A kint lévő téma CSS-fájljának kiírása, ha még nincs meg; a fájlnevet adja vissza. A telepítés
     * is hívja (forgatag:tema-css), így egy generátor-változás után nem a látogató első kérése írja ki.
     */
    public static function ensureCss(): string
    {
        self::$css ??= self::cssFor(self::data());
        self::writeCss(self::$css['name'], self::$css['css']);

        return self::$css['name'];
    }

    /** Egy arculati fájl (logo / logoHeader / favicon) címe. */
    public static function assetUrl(string $key): string
    {
        if (! in_array($key, self::ASSET_KEYS, true)) {
            throw new RuntimeException("Ismeretlen arculati elem: {$key}");
        }

        return asset(self::DISK . '/' . self::existing((string) self::get("assets.{$key}"), "assets.{$key}"));
    }

    /** Egy firka-motívum (0–5) tisztított SVG-je, a lapba ágyazáshoz. */
    public static function motifSvg(int $i): string
    {
        $path = self::existing((string) self::get("doodles.motifs.{$i}"), "doodles.motifs.{$i}");
        $svg = self::sanitizeSvg((string) self::disk()->get($path));

        return $svg !== '' ? $svg : throw new RuntimeException("Téma: a(z) {$i}. firka-motívum nem érvényes SVG.");
    }

    /** Az előtöltendő betűfájlok (a témában választott betűkből — nem beégetve). @return list<string> */
    public static function fontPreloads(): array
    {
        return collect(['type.body', 'type.head', 'type.accent'])
            ->map(fn ($p) => ThemeCss::FONTS[self::get($p)]['preload'] ?? null)
            ->filter()->unique()->map(fn ($f) => asset($f))->values()->all();
    }

    /**
     * Publikálás: ellenőrzés (szerkezet, értékek, fájlok, KONTRASZT) → CSS generálás és fájlba írás →
     * új verzió-sor → a piszkozat törlése. Ha bármi nem sikerül, nem keletkezik verzió (a kint lévő
     * téma marad). Kontraszt-hiba csak írásos felülbírálással mehet ki; az indoklás a verzión marad.
     *
     * @param  array<string, mixed>  $data
     */
    public static function publish(array $data, ?User $user, string $comment, ?string $byName = null, ?string $contrastOverride = null): ThemeVersion
    {
        $data = self::clean($data);
        self::validate($data);
        $hibak = ThemeContrast::failures($data);
        $override = trim((string) $contrastOverride);
        if ($hibak && mb_strlen($override) < 10) {
            throw new ThemeContrastException($hibak);
        }
        ['name' => $name, 'css' => $css] = self::cssFor($data);
        self::writeCss($name, $css);

        $v = DB::transaction(function () use ($data, $name, $user, $byName, $comment, $hibak, $override) {
            $v = ThemeVersion::create([
                'data' => $data,
                'css_file' => $name,
                'user_id' => $user?->id,
                'published_by' => $byName ?? $user?->email,
                'comment' => mb_substr(trim($comment), 0, 255) ?: null,
                'contrast_override' => $hibak ? $override : null,
            ]);
            DB::table('theme_drafts')->delete();

            return $v;
        });
        self::flush();

        return $v;
    }

    /** A közös piszkozat (egy van; bárki folytathatja, aki a /tema-hoz fér). */
    public static function draft(): ?object
    {
        return DB::table('theme_drafts')->first();
    }

    /**
     * Piszkozat mentése. Ugyanúgy ellenőrizzük, mint a publikálást (a kontrasztot kivéve — azt a
     * publikálás kapuja nézi), hogy egy hibás import ne csak a publikáláskor derüljön ki.
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveDraft(array $data, User $user, int $baseVersionId): void
    {
        $data = self::clean($data);
        self::validate($data);
        DB::transaction(function () use ($data, $user, $baseVersionId) {
            DB::table('theme_drafts')->delete();
            DB::table('theme_drafts')->insert([
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'base_version_id' => $baseVersionId,
                'user_id' => $user->id,
                'saved_by' => $user->email,
                'updated_at' => now(),
            ]);
        });
    }

    public static function discardDraft(): void
    {
        DB::table('theme_drafts')->delete();
    }

    /** Az utolsó N publikált verzió (legújabb elöl). @return \Illuminate\Support\Collection<int, ThemeVersion> */
    public static function history(int $n = 10)
    {
        return ThemeVersion::query()->latest('id')->limit($n)->get();
    }

    /**
     * A gyári téma (seed/tema/) — a fájlok RENDES feltöltésként kerülnek a tárba (azonos tartalom =
     * azonos név, tehát újrahívva sem keletkezik másolat). Ebből indul a kezdő téma, és ez a
     * szerkesztő „Gyári” visszaállítása (egészben vagy fájl-mezőnként).
     *
     * @return array<string, mixed>
     */
    public static function factoryData(): array
    {
        $ut = base_path('seed/tema/tema.json');
        $adat = is_readable($ut) ? json_decode((string) file_get_contents($ut), true) : null;
        if (! is_array($adat)) {
            throw new RuntimeException('seed/tema/tema.json hiányzik vagy hibás JSON.');
        }
        $fajl = fn (mixed $nev) => base_path('seed/tema/fajlok/' . basename((string) $nev));

        foreach (['logo' => 'logo', 'logoHeader' => 'logo-fejlec', 'favicon' => 'favicon'] as $kulcs => $hely) {
            $adat['assets'][$kulcs] = self::storeAsset($fajl($adat['assets'][$kulcs] ?? ''), $hely);
        }
        foreach (array_values((array) ($adat['doodles']['motifs'] ?? [])) as $i => $nev) {
            $adat['doodles']['motifs'][$i] = self::storeAsset($fajl($nev), 'firka-' . ($i + 1));
        }

        return self::clean($adat);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private static function clean(array $data): array
    {
        return array_filter($data, fn ($k) => ! str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Egy fájl betétele az arculati tárba (feltöltés). Név: <hely>-<lenyomat>.<kiterjesztés> —
     * a csere így a fájl címét is megváltoztatja. A visszaadott útvonal megy a téma-adatba.
     */
    public static function storeAsset(string $sourcePath, string $slot): string
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new RuntimeException("Téma: a feltöltendő fájl nem olvasható: {$sourcePath}");
        }
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (! in_array($ext, self::KEPEK, true) || ! preg_match('/^[a-z0-9-]+$/', $slot)) {
            throw new RuntimeException("Téma: nem engedett fájltípus vagy hely: {$slot}.{$ext}");
        }
        $tartalom = (string) file_get_contents($sourcePath);
        if ($ext === 'svg') {
            $tartalom = self::sanitizeSvg($tartalom);
            if ($tartalom === '') {
                throw new RuntimeException("Téma: a(z) {$slot} SVG nem fogadható el (nincs <svg>, vagy szkriptet/hivatkozást tartalmaz).");
            }
        }
        $path = 'fajlok/' . $slot . '-' . substr(hash('sha256', $tartalom), 0, 12) . '.' . $ext;
        self::disk()->put($path, $tartalom);

        return $path;
    }

    /**
     * A kezdő téma betöltése: seed/tema/tema.json (a 2026-os rendszer utolsó publikált témája).
     * A logó, a fejléc-logó, a favicon és a hat firka-motívum RENDES feltöltésként kerül az arculati
     * tárba (seed/tema/fajlok/), és a téma-adat ezekre mutat — nincs kódbeli tartalék (CLAUDE.md 2/c).
     */
    public static function importSeed(string $byName): ThemeVersion
    {
        return self::publish(self::factoryData(), null, 'A 2026-os rendszer utolsó publikált témájának átvétele (schema-bot, 2026-09-02)', $byName);
    }

    /** @param array<string, mixed> $data */
    public static function validate(array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        foreach (self::TILTOTT_CIMEK as $cim) {
            if (str_contains((string) $json, $cim)) {
                throw new RuntimeException("Téma: a(z) {$cim} címre nem mutathat semmi (a NAS nélkül eltörne).");
            }
        }
        foreach (self::ASSET_KEYS as $k) {
            self::existing((string) data_get($data, "assets.{$k}"), "assets.{$k}");
        }
        $motifs = data_get($data, 'doodles.motifs');
        if (! is_array($motifs) || count($motifs) !== self::MOTIF_SLOTS) {
            throw new RuntimeException('Téma: a doodles.motifs mezőben pontosan ' . self::MOTIF_SLOTS . ' firka-fájl kell (üres hely nincs).');
        }
        foreach (array_values($motifs) as $i => $m) {
            $p = self::existing((string) $m, "doodles.motifs.{$i}");
            if (! str_ends_with($p, '.svg') || self::sanitizeSvg((string) self::disk()->get($p)) === '') {
                throw new RuntimeException("Téma: a(z) {$i}. firka-motívum nem érvényes SVG.");
            }
        }
        ThemeEditorSpec::validate($data); // szerkezet (ismeretlen/hiányzó mező) + értéktartományok
        ThemeCss::generate($data); // hiányzó/hibás token → hiba
    }

    /**
     * SVG megszelídítése beágyazás előtt (a 2026-os sanitizeSvg átirata): csak <svg>, szkript,
     * esemény-attribútum és külső hivatkozás nélkül. Hibás bemenet → üres string.
     */
    public static function sanitizeSvg(string $input): string
    {
        if (! preg_match('/<svg[\s\S]*<\/svg>/i', trim($input), $m)) {
            return '';
        }
        $svg = $m[0];
        if (preg_match('/<\s*(script|foreignObject|iframe|object|embed|image)\b/i', $svg)
            || preg_match('/\bjavascript:|\bxlink:href\s*=|\bhref\s*=/i', $svg)) {
            return '';
        }

        return (string) preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg);
    }

    public static function flush(): void
    {
        self::$current = null;
        self::$css = null;
    }

    /** @param array<string, mixed> $data @return array{name: string, css: string} */
    private static function cssFor(array $data): array
    {
        $css = ThemeCss::generate($data);
        foreach (self::TILTOTT_CIMEK as $cim) {
            if (str_contains($css, $cim)) {
                throw new RuntimeException("Téma: a generált CSS a(z) {$cim} címre mutat.");
            }
        }

        return ['name' => 'tema-' . substr(hash('sha256', $css), 0, 12) . '.css', 'css' => $css];
    }

    private static function writeCss(string $name, string $css): void
    {
        $disk = self::disk();
        if (! $disk->exists($name)) {
            $disk->put($name, $css);
        }
    }

    /** A téma-adatban tárolt fájl-útvonal ellenőrzése: helyi, az arculati tárban van, létezik. */
    private static function existing(string $path, string $mezo): string
    {
        if ($path === '' || ! preg_match('#^fajlok/[a-z0-9-]+\.(' . implode('|', self::KEPEK) . ')$#', $path)) {
            throw new RuntimeException("Téma: a(z) {$mezo} mező nem feltöltött arculati fájl („{$path}”).");
        }
        if (! self::disk()->exists($path)) {
            throw new RuntimeException("Téma: a(z) {$mezo} fájlja hiányzik a tárból: {$path}");
        }

        return $path;
    }

    private static function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
