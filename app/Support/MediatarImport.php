<?php

namespace App\Support;

use App\Console\Commands\MediatarCsomag;
use App\Enums\Gyujtemeny;
use App\Enums\MediaLicense;
use App\Enums\MediaOrigin;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Models\Sponsor;
use App\Models\SponsorTier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * A 2026-os képanyag BEHOZATALA a szerveren (PROMPT-07). A leírás a tárolóban van
 * (database/import/mediatar-2026.json — a fejlesztőgépen készül: forgatag:mediatar-csomag), a fájlokat a
 * felhasználó tölti fel zip-ekben ide: storage/app/import-2026/ (vagy kicsomagolva: import-2026/fajlok/).
 *
 * · ISMÉTLÉS-BIZTOS: minden fájl a 2026-os azonosítójával (legacy_id) kerül be; a második futás csak ellenőriz.
 * · ELLENŐRZÖTT: minden fájl ellenőrző összege (sha256) egyezik a mentés leltárával, különben nem kerül be.
 * · KAPCSOLATOKKAL: galériakép → galéria, logó → támogató; a többinél az eredet a képen marad (legacy_note).
 * · SZÁMSZERŰ JELENTÉS: storage/app/import-2026/jelentes.json — a médiatár lapja ezt mutatja (néma hiba tilos).
 * · A jóvá nem hagyott beküldött fotó a ZÁRT tárba megy (storage/app/private/bekuldesek-2026), nem a médiatárba.
 * · Egy zip csak akkor törlődik, ha MINDEN fájlja bent van és ellenőrzött (helyet szabadít a tárhelyen).
 */
class MediatarImport
{
    public const ZART = 'bekuldesek-2026';

    public static function mappa(): string
    {
        return storage_path('app/import-2026');
    }

    public static function jelentesUt(?string $mappa = null): string
    {
        return ($mappa ?? self::mappa()) . '/jelentes.json';
    }

    private string $dir;

    private string $leirasUt;

    /** A két útvonal csak a tesztek kedvéért állítható (alapból: storage/app/import-2026 és a tárolóbeli leírás). */
    public function __construct(?string $mappa = null, ?string $leiras = null)
    {
        $this->dir = $mappa ?? self::mappa();
        $this->leirasUt = $leiras ?? base_path(MediatarCsomag::LEIRAS);
    }

    /** A médiatár lapja mutatja. Null, ha még nem futott. */
    public static function utolsoJelentes(): ?array
    {
        $ut = self::jelentesUt();

        return is_file($ut) ? (json_decode((string) file_get_contents($ut), true) ?: null) : null;
    }

    /** @var array<string, string> zip-bejegyzés neve → zip útvonala */
    private array $zipIndex = [];

    /** @var list<string> */
    private array $hibak = [];

    /** @var list<string> nem hiba, de a felhasználónak tennivalója van (pl. túl nagy kép) */
    private array $figyelmeztetesek = [];

    /**
     * E fölött a méretváltozat (bélyegkép) nem készül el a tárhely memóriájával (a Glide-útvonalon 512 MB,
     * AppServiceProvider). Az eredetit NEM módosítjuk — a felhasználó cseréli kisebbre.
     */
    public const MAX_MEGAPIXEL = 40;

    /** @var array<string, array{0:int,1:int}> sors → [bent, várt] */
    private array $szamok = [];

    /** @var array<string, bool> zip → minden fájlja rendben */
    private array $zipRendben = [];

    /**
     * @return array|null a jelentés; null, ha nincs mit csinálni (nincs csomag és még semmi nem jött be)
     */
    public function run(callable $naplo): ?array
    {
        $arculatUj = ArculatMedia::syncAll();
        $naplo("Arculat: {$arculatUj} új téma-fájl került a médiatárba.");

        $leiras = json_decode((string) @file_get_contents($this->leirasUt), true)
            ?: throw new RuntimeException('Hiányzik vagy hibás: ' . $this->leirasUt);

        $vanCsomag = is_dir($this->dir) && (glob($this->dir . '/*.zip') || is_dir($this->dir . '/fajlok'));
        $marBent = Media::whereNotNull('legacy_id')->count() + count(Storage::disk('local')->files(self::ZART));
        if (! $vanCsomag && $marBent === 0) {
            $naplo('2026-os képanyag: nincs feltöltött csomag (storage/app/import-2026/), kihagyva.');

            return null;
        }

        $this->zipIndex();
        $szintek = $this->szintek($leiras['szintek']);
        $galeriak = $this->galeriak($leiras['galeriak']);
        $tamogatok = $this->tamogatok($leiras['tamogatok'], $szintek);

        foreach ($leiras['fajlok'] as $f) {
            $sors = $f['sors'];
            $this->szamok[$sors] ??= [0, 0];
            $this->szamok[$sors][1]++;
            try {
                $ok = $sors === 'bekuldes_zart' ? $this->zartba($f) : $this->mediatarba($f, $galeriak, $tamogatok);
            } catch (Throwable $e) {
                report($e);
                $this->hibak[] = "{$f['eredeti_nev']} ({$f['id']}): {$e->getMessage()}";
                $ok = false;
            }
            if ($ok) {
                $this->szamok[$sors][0]++;
            }
            if ($zip = $this->zipIndex['fajlok/' . $f['forras']] ?? null) {
                $this->zipRendben[$zip] = ($this->zipRendben[$zip] ?? true) && $ok;
            }
        }

        $torolt = $this->zipTorles();
        $mindBent = array_sum(array_column($this->szamok, 0)) === array_sum(array_column($this->szamok, 1)) && $this->hibak === [];
        if ($mindBent) {
            foreach (glob($this->dir . '/*.zip') ?: [] as $zip) {
                if (@unlink($zip)) {
                    $torolt[] = basename($zip);
                }
            }
            if (is_dir($this->dir . '/fajlok') && (glob($this->dir . '/fajlok/*') ?: []) === []) {
                @rmdir($this->dir . '/fajlok');
            }
        }
        $jelentes = $this->jelentes($leiras, $arculatUj, $torolt);
        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }
        file_put_contents(self::jelentesUt($this->dir), json_encode($jelentes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $naplo('2026-os képanyag: ' . $jelentes['osszegzes']);
        foreach ($jelentes['sorok'] as [$mi, $db]) {
            $naplo(sprintf('  %-70s %s', $mi, $db));
        }
        foreach ($this->hibak as $h) {
            $naplo('  HIBA: ' . $h);
        }
        foreach ($this->figyelmeztetesek as $h) {
            $naplo('  TEENDŐ: ' . $h);
        }

        return $jelentes;
    }

    /** @return array<string, int> 2026-os szint-azonosító → új id */
    private function szintek(array $sorok): array
    {
        $map = [];
        foreach ($sorok as $s) {
            $map[$s['id']] = SponsorTier::updateOrCreate(['legacy_id' => $s['id']],
                ['year' => 2026, 'name' => $s['name'], 'name_ro' => $s['name_ro'], 'sort' => $s['sort']])->id;
        }

        return $map;
    }

    /** @return array<string, int> */
    private function galeriak(array $sorok): array
    {
        $map = [];
        foreach ($sorok as $g) {
            $map[$g['id']] = Gallery::firstOrCreate(['legacy_id' => $g['id']],
                ['year' => $g['year'], 'title_hu' => $g['title_hu'], 'title_ro' => $g['title_ro'], 'credit' => $g['credit'], 'sort' => $g['sort']])->id;
        }

        return $map;
    }

    /** @return array<string, int> */
    private function tamogatok(array $sorok, array $szintek): array
    {
        $map = [];
        foreach ($sorok as $s) {
            $map[$s['id']] = Sponsor::firstOrCreate(['legacy_id' => $s['id']],
                ['name' => $s['name'], 'year' => 2026, 'sponsor_tier_id' => $szintek[$s['tier']] ?? null, 'url' => $s['url'], 'sort' => $s['sort']])->id;
        }

        return $map;
    }

    private function mediatarba(array $f, array $galeriak, array $tamogatok): bool
    {
        $tar = Storage::disk('mediatar');
        $media = Media::where('legacy_id', $f['id'])->first();

        if ($media === null) {
            $this->atmasol($f, $tar->path($f['cel']));
            try {
                $media = DB::transaction(function () use ($f) {
                    $m = new Media([
                        'disk' => 'mediatar', 'directory' => null, 'visibility' => 'public',
                        'name' => pathinfo($f['cel'], PATHINFO_FILENAME), 'path' => $f['cel'],
                        'width' => $f['width'], 'height' => $f['height'], 'size' => $f['size'],
                        'type' => $f['type'], 'ext' => $f['ext'], 'title' => $f['title'],
                        'origin' => MediaOrigin::Import2026,
                        'license' => MediaLicense::tryFrom((string) ($f['license'] ?? '')) ?? MediaLicense::Sajat,
                        'rights_holder' => $f['rights_holder'] ?? null,
                        'photographer_credit' => $f['photographer'] ?? null,
                        'legacy_id' => $f['id'],
                        'legacy_note' => $f['note'],
                    ]);
                    $m->created_at = Carbon::parse($f['created']);
                    $m->save();
                    $m->collections()->sync(array_map(fn ($g) => MediaCollection::idFor(Gyujtemeny::from($g)), $f['gyujtemeny']));

                    return $m;
                });
            } catch (Throwable $e) {
                $tar->delete($f['cel']); // ne maradjon gazdátlan fájl a nyilvános tárban

                throw $e;
            }
        } elseif (! $tar->exists($media->path) || $tar->size($media->path) !== $f['size']) {
            throw new RuntimeException('a nyilvántartásban bent van, de a fájl hiányzik vagy a mérete eltér a médiatárban');
        }

        // A 2026-os nyilvántartásban néhány képnek nincs mérete — a fájlból pótoljuk (a túlméret-őr ebből dolgozik).
        if ($media->width === null && str_starts_with((string) $media->type, 'image/') && $media->ext !== 'svg') {
            [$w, $h] = @getimagesize($tar->path($media->path)) ?: [null, null];
            if ($w) {
                $media->forceFill(['width' => $w, 'height' => $h])->saveQuietly();
            }
        }
        if ($media->width && $media->height && $media->width * $media->height > self::MAX_MEGAPIXEL * 1_000_000) {
            $this->figyelmeztetesek[] = sprintf('%s (%d×%d, %.0f megapixel) — túl nagy, a bélyegképe nem készül el: cseréld kisebbre (pl. 3000 px szélesre) a kép „Csere” fülén. %s',
                $media->title, $media->width, $media->height, $media->width * $media->height / 1e6, $media->legacy_note);
        }

        if (isset($f['galeria'])) {
            $gid = $galeriak[$f['galeria']['id']] ?? throw new RuntimeException('a galériája nincs meg');
            DB::table('gallery_media')->insertOrIgnore(['gallery_id' => $gid, 'media_id' => $media->id, 'sort' => $f['galeria']['sort']]);
        }
        if (isset($f['tamogato'])) {
            $sid = $tamogatok[$f['tamogato']] ?? throw new RuntimeException('a támogatója nincs meg');
            $sp = Sponsor::find($sid);
            if ($sp->logo_media_id === null) {
                $sp->update(['logo_media_id' => $media->id]);
            }
        }

        return true;
    }

    private function zartba(array $f): bool
    {
        $zart = Storage::disk('local');
        $ut = self::ZART . '/' . $f['cel'];
        if (! $zart->exists($ut) || $zart->size($ut) !== $f['size']) {
            $this->atmasol($f, $zart->path($ut));
        }
        // Azonosíthatók maradjanak (mi ez, kié a jog) — a lista a fájlok mellett.
        $lista = json_decode((string) ($zart->exists(self::ZART . '/LISTA.json') ? $zart->get(self::ZART . '/LISTA.json') : '[]'), true) ?: [];
        $lista[$f['cel']] = ['cim' => $f['title'], 'eredet' => $f['note'], 'jogtulajdonos' => $f['rights_holder'] ?? null,
            'fotos' => $f['photographer'] ?? null, 'licenc' => $f['license'] ?? null, 'feltoltve_2026' => $f['created']];
        $zart->put(self::ZART . '/LISTA.json', json_encode($lista, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return true;
    }

    /** A fájl a csomagból (kicsomagolt mappa vagy zip) a célba, ellenőrző összeggel. */
    private function atmasol(array $f, string $cel): void
    {
        $kibontott = $this->dir . '/fajlok/' . $f['forras'];
        if (is_file($kibontott)) {
            // Kicsomagolt fájl: ÁTHELYEZÉS (ugyanazon a lemezen azonnali, és nem foglal még egyszer helyet).
            if (! is_dir(dirname($cel))) {
                mkdir(dirname($cel), 0755, true);
            }
            if (! @rename($kibontott, $cel)) {
                throw new RuntimeException('a kicsomagolt fájl nem helyezhető át ide: ' . $cel);
            }
            if (filesize($cel) !== $f['size'] || hash_file('sha256', $cel) !== $f['sha256']) {
                @rename($cel, $kibontott); // a hibás fájl marad a csomagban, megnézhető

                throw new RuntimeException('a kicsomagolt fájl ellenőrző összege nem egyezik a mentés leltárával (sérült feltöltés/kicsomagolás?)');
            }
            @chmod($cel, 0644);

            return;
        }
        if ($zip = $this->zipIndex['fajlok/' . $f['forras']] ?? null) {
            $z = new ZipArchive();
            if ($z->open($zip) !== true) {
                throw new RuntimeException('a zip nem nyitható meg: ' . basename($zip));
            }
            $be = $z->getStream('fajlok/' . $f['forras']);
        } else {
            throw new RuntimeException('nincs a feltöltött csomagban (hiányzó zip?)');
        }
        if (! $be) {
            throw new RuntimeException('a csomagból nem olvasható');
        }
        if (! is_dir(dirname($cel))) {
            mkdir(dirname($cel), 0755, true);
        }
        $ki = fopen($cel, 'wb') ?: throw new RuntimeException('a cél nem írható: ' . $cel);
        $irt = stream_copy_to_stream($be, $ki);
        fclose($ki);
        fclose($be);
        if (isset($z)) {
            $z->close();
        }
        if ($irt !== $f['size'] || hash_file('sha256', $cel) !== $f['sha256']) {
            @unlink($cel);

            throw new RuntimeException('az átmásolt fájl ellenőrző összege nem egyezik a mentés leltárával (sérült feltöltés?)');
        }
        @chmod($cel, 0644);
    }

    private function zipIndex(): void
    {
        foreach (glob($this->dir . '/*.zip') ?: [] as $zip) {
            if (! class_exists(ZipArchive::class)) {
                // A tárhely telepítő-PHP-jában (ea-php84) NINCS zip-kezelő (mérve 2026-10-28). Ilyenkor a felhasználó a
                // Fájlkezelőben csomagolja ki (import-2026/fajlok/) — a zip-ek ekkor csak helyet foglalnak.
                if (is_dir($this->dir . '/fajlok')) {
                    return;
                }
                throw new RuntimeException('A PHP-ban nincs zip-kezelő: csomagold ki a zip-eket a Fájlkezelőben az import-2026 mappába (lesz benne egy „fajlok” mappa), és futtasd újra a telepítést.');
            }
            $z = new ZipArchive();
            if ($z->open($zip) !== true) {
                $this->hibak[] = 'A zip nem nyitható meg (csonka feltöltés?): ' . basename($zip);

                continue;
            }
            for ($i = 0; $i < $z->numFiles; $i++) {
                $this->zipIndex[$z->getNameIndex($i)] = $zip;
            }
            $z->close();
        }
    }

    /** @return list<string> a törölt zip-ek */
    private function zipTorles(): array
    {
        $torolt = [];
        foreach ($this->zipRendben as $zip => $rendben) {
            if ($rendben && is_file($zip) && @unlink($zip)) {
                $torolt[] = basename($zip);
            }
        }

        return $torolt;
    }

    private function jelentes(array $leiras, int $arculatUj, array $torolt): array
    {
        // A kapcsolatok az ADATBÁZISBÓL számolva (nem a futás közbeni számlálóból): a várt érték a leírásból.
        $kapcsolat = [
            'galeria' => [DB::table('gallery_media')->join('curator', 'curator.id', '=', 'gallery_media.media_id')
                ->join('galleries', 'galleries.id', '=', 'gallery_media.gallery_id')
                ->whereNotNull('curator.legacy_id')->whereNotNull('galleries.legacy_id')->count(),
                count(array_filter($leiras['fajlok'], fn ($f) => isset($f['galeria'])))],
            'tamogato' => [Sponsor::whereNotNull('legacy_id')->whereNotNull('logo_media_id')->count(), count($leiras['tamogatok'])],
        ];
        $sz = fn (string $k) => $this->szamok[$k] ?? [0, 0];
        $tort = fn (array $x) => "{$x[0]} / {$x[1]}";
        $mediatar = array_filter($this->szamok, fn ($k) => $k !== 'bekuldes_zart', ARRAY_FILTER_USE_KEY);
        $mBent = array_sum(array_column($mediatar, 0));
        $mVart = array_sum(array_column($mediatar, 1));
        $kihagyott = $leiras['sorsok']['onkentes'] ?? 0;
        $zart = $sz('bekuldes_zart');
        $hianyzik = ($mVart - $mBent) + ($zart[1] - $zart[0]);

        $sorok = [
            ['Fájlok a 2026-os mentésben', (string) $leiras['fajlok_szama']],
            ['Szándékosan kihagyva: önkéntes-mellékletek (személyes adat, a mentésben maradnak)', (string) $kihagyott],
            ['A nyilvános médiatárba — bent / várt', $tort([$mBent, $mVart])],
            ['   galériakép („Galériák”)', $tort($sz('galeria'))],
            ['      ebből a galériájához kötve', $tort($kapcsolat['galeria'])],
            ['   támogatói logó („Támogatók”)', $tort($sz('tamogato'))],
            ['      ebből a támogatójához kötve', $tort($kapcsolat['tamogato'])],
            ['   2026-os programborító („Programok 2026”, eredet-jelöléssel)', $tort($sz('program2026'))],
            ['   hírborító („Hírek”) — a hír a tartalmi körben jön, a kép eredete jelölve', $tort($sz('hir'))],
            ['   csapattag-fotó („Csapat”) — eredet jelölve', $tort($sz('csapat'))],
            ['   kézműves-kép („Kézművesek”) — eredet jelölve', $tort($sz('kezmuves'))],
            ['   aloldal-kép („Aloldalak”) — eredet jelölve', $tort($sz('lap'))],
            ['   a 2026-os téma faviconja („Arculat”)', $tort($sz('arculat2026'))],
            ['   semmi nem hivatkozott rá → „Rendezetlen”', $tort($sz('rendezetlen'))],
            ['   2026-os demó-kép (próbatartalom) → „Rendezetlen”', $tort($sz('rendezetlen_demo'))],
            ['A ZÁRT tárba: meg nem jelent program beküldött fotója — bent / várt', $tort($zart)],
            ['Hibás típus javítva („… másolata”, octet-stream → kép)', (string) count(array_filter($leiras['fajlok'], fn ($f) => $f['type_2026'] !== null))],
            ['A 2027-es téma fájljai az „Arculat” gyűjteményben (új / összes)', $arculatUj . ' / ' . Media::where('origin', MediaOrigin::Arculat)->count()],
        ];
        if ($torolt) {
            $sorok[] = ['Feltöltött zip-ek törölve (minden fájljuk bent, ellenőrizve)', implode(', ', $torolt)];
        }

        $kapcsHiany = ($kapcsolat['galeria'][1] - $kapcsolat['galeria'][0]) + ($kapcsolat['tamogato'][1] - $kapcsolat['tamogato'][0]);
        if ($kapcsHiany > 0) {
            $this->hibak[] = "{$kapcsHiany} kapcsolat hiányzik (galéria {$kapcsolat['galeria'][0]}/{$kapcsolat['galeria'][1]}, logó {$kapcsolat['tamogato'][0]}/{$kapcsolat['tamogato'][1]}).";
        }

        return [
            'ido' => now()->format('Y-m-d H:i'),
            'osszegzes' => $hianyzik === 0 && $this->hibak === []
                ? "mind a(z) {$mBent} + {$zart[0]} fájl bent van, ellenőrizve; kapcsolat: galéria {$kapcsolat['galeria'][0]}/{$kapcsolat['galeria'][1]}, logó {$kapcsolat['tamogato'][0]}/{$kapcsolat['tamogato'][1]}."
                : "{$hianyzik} fájl HIÁNYZIK, {$kapcsHiany} kapcsolat hiányzik, " . count($this->hibak) . ' hibaüzenet — lásd lent.',
            'sorok' => $sorok,
            'hibak' => $this->hibak,
            'figyelmeztetesek' => $this->figyelmeztetesek,
        ];
    }
}
