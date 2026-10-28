<?php

namespace App\Console\Commands;

use App\Enums\Gyujtemeny;
use App\Enums\MediaLicense;
use App\Support\Mentes2026;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

/**
 * A 2026-os képanyag CSOMAGOLÁSA — a fejlesztőgépen fut, a mentésből (MENTES-LELTAR.md), NEM a szerveren.
 *
 * Kimenet:
 *  · database/import/mediatar-2026.json — a leírás: minden fájl sorsa, a gyűjteménye, a jogállás-adatai és a
 *    kapcsolatai (galéria, támogató, eredet). A tárolóba kerül (személyes adat NINCS benne: csak nyilvános
 *    kreditek, programcímek, támogatónevek).
 *  · <kimenet>/import-2026-N.zip — maguk a fájlok (fajlok/<directus-név>), tömörítés nélkül, ~200 MB-os
 *    darabokban (a cPanel Fájlkezelőn így biztonságosan feltölthető). Ezeket a felhasználó tölti fel.
 *
 * MINDEN fájlnak pontosan egy sorsa van, és a végén a számok összegének ki kell adnia a mentés fájlszámát —
 * ha nem, hibával áll meg (néma hiba tilos).
 *
 * Futtatás (a mentés mappáját csak olvasásra csatolva):
 *   docker compose run --rm -v "E:/Work/mysites/forgatag.ro/MENTES-2026:/mentes" app \
 *     php artisan forgatag:mediatar-csomag /mentes/2026-10-21 /mentes/import-csomag
 */
#[Signature('forgatag:mediatar-csomag {mentes : a mentés mappája (benne adatbazis/, fajltar/)} {kimenet : ide kerülnek a zip-ek} {--darab=200 : egy zip legnagyobb mérete MB-ban}')]
#[Description('A 2026-os kepanyag csomagolasa a mentesbol (fejlesztogepen fut)')]
class MediatarCsomag extends Command
{
    public const LEIRAS = 'database/import/mediatar-2026.json';

    /** Sorsok — a jelentés és a behozó ugyanezekkel a kulcsokkal dolgozik (App\Support\MediatarImport::SORSOK). */
    private const ONKENTES = 'onkentes';

    public function handle(): int
    {
        $mentes = rtrim((string) $this->argument('mentes'), '/');
        $kimenet = rtrim((string) $this->argument('kimenet'), '/');

        try {
            $t = $this->tablak($mentes);
            $leiras = $this->leiras($mentes, $t);
            $this->zipek($mentes, $kimenet, $leiras);
        } catch (RuntimeException $e) {
            $this->error('CSOMAG: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return array<string, list<array<string, ?string>>> a szükséges táblák a PostgreSQL-mentésből */
    private function tablak(string $mentes): array
    {
        return Mentes2026::tablak($mentes, ['directus_files', 'media_asset', 'gallery', 'gallery_files', 'sponsor', 'sponsor_tier',
            'program_item', 'news_item', 'page', 'page_block', 'team_member', 'craftsperson', 'theme', 'volunteer_application', 'category', 'venue']);
    }

    /** @param array<string, list<array<string, ?string>>> $t */
    private function leiras(string $mentes, array $t): array
    {
        // Ellenőrző összegek a mentés saját leltárából (manifest.tsv: id, fájlnév, méret, letöltött méret, HTTP, sha256).
        $sha = [];
        foreach (file("{$mentes}/fajltar/manifest.tsv", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $sor) {
            $c = explode("\t", $sor);
            $sha[$c[0]] = $c[5] ?? null;
        }

        $fajlok = collect($t['directus_files'])->keyBy('id');
        $ma = collect($t['media_asset'])->keyBy('file');
        $prog = collect($t['program_item'])->keyBy('id');
        $galeriaKep = collect($t['gallery_files'])->keyBy('directus_files_id');
        $galeria = collect($t['gallery'])->keyBy('id');
        $tamogato = collect($t['sponsor'])->keyBy('logo');
        $borito = collect($t['program_item'])->keyBy('cover_image');
        $onkentes = collect($t['volunteer_application'])->flatMap(fn ($a) => [$a['parental_consent_file'], $a['motivation_letter_file']])->filter()->flip();
        $hir = collect($t['news_item'])->keyBy('cover');
        $csapat = collect($t['team_member'])->keyBy('photo');
        $kezmuves = collect($t['craftsperson'])->keyBy('image');
        $lapblokk = collect($t['page_block'])->keyBy('image');
        $lapKep = collect($t['page'])->keyBy('hero_image');
        $szoveg = fn (string $tabla, array $mezok) => collect($t[$tabla])->map(fn ($r) => ['r' => $r, 's' => implode(' ', array_map(fn ($m) => (string) ($r[$m] ?? ''), $mezok))]);
        $lapSzoveg = $szoveg('page', ['home_sections', 'lead_hu', 'lead_ro']);
        $temaSzoveg = (string) ($t['theme'][0]['data'] ?? '');

        $kimenet = [];
        $sorsok = [];
        foreach ($fajlok as $id => $f) {
            $e = $this->fajlAlap($mentes, $f, $sha[$id] ?? null);
            $m = $ma->get($id);
            $kredit = fn () => [
                'license' => MediaLicense::from2026($m['license_type'] ?? null)->value,
                'rights_holder' => $m['rights_holder'] ?? null,
                'photographer' => $m['photographer_credit'] ?? null,
            ];

            if ($onkentes->has($id)) {
                // SZEMÉLYES ADAT (szülői hozzájárulás, motivációs levél) — NEM médiatár-anyag, nem jön át.
                $sorsok[self::ONKENTES] = ($sorsok[self::ONKENTES] ?? 0) + 1;

                continue;
            } elseif ($g = $galeriaKep->get($id)) {
                $gal = $galeria[$g['gallery_id']];
                $e += ['sors' => 'galeria', 'gyujtemeny' => [Gyujtemeny::Galeriak->value], 'license' => MediaLicense::Sajat->value,
                    'rights_holder' => 'Vásárhelyi Forgatag', 'galeria' => ['id' => $g['gallery_id'], 'sort' => (int) $g['sort']],
                    'note' => "2026-os rendszerből: {$gal['title_hu']} galéria"];
            } elseif ($s = $tamogato->get($id)) {
                $e += ['sors' => 'tamogato', 'gyujtemeny' => [Gyujtemeny::Tamogatok->value], 'license' => MediaLicense::Sajat->value,
                    'rights_holder' => $s['name'], 'tamogato' => $s['id'], 'note' => "2026-os támogató logója: {$s['name']}"];
            } elseif ($p = $borito->get($id)) {
                // A tavalyi programra kapott jog — „Programok 2026”, 2027-es programhoz nem rendelhető (tulajdonosi döntés).
                $k = $m ? $kredit() : ['license' => $p['photo_rights_declared'] === 't' ? MediaLicense::MindenJogFenntartva->value : MediaLicense::Ismeretlen->value,
                    'rights_holder' => $p['organizer_names'] ?: $p['photographer_credit'], 'photographer' => $p['photographer_credit']];
                $e += ['sors' => 'program2026', 'gyujtemeny' => [Gyujtemeny::Programok2026->value], ...$k,
                    'note' => "2026-os program borítója: {$p['title']}"];
            } elseif (str_starts_with((string) $f['filename_download'], 'bekuldes-')) {
                // Beküldött fotó, aminek a programja 2026-ban NEM jelent meg (törölt/elutasított/újraküldött):
                // a jóvá nem hagyott fotó szabálya szerint a ZÁRT tárba megy, nem a nyilvános médiatárba.
                $e += ['sors' => 'bekuldes_zart', 'gyujtemeny' => [], ...($m ? $kredit() : ['license' => MediaLicense::Ismeretlen->value]),
                    'note' => '2026-ban beküldött, de meg nem jelent program fotója: ' . ($m['title'] ?? $f['title'] ?? $f['filename_download'])];
            } elseif ($n = $hir->get($id)) {
                $e += ['sors' => 'hir', 'gyujtemeny' => [Gyujtemeny::Hirek->value], 'license' => MediaLicense::Sajat->value,
                    'note' => "2026-os hír borítója: {$n['title_hu']} ({$n['slug']})"];
            } elseif ($c = $csapat->get($id)) {
                $e += ['sors' => 'csapat', 'gyujtemeny' => [Gyujtemeny::Csapat->value], 'license' => MediaLicense::Sajat->value,
                    'note' => "2026-os csapattag fotója: {$c['name']}"];
            } elseif ($k = $kezmuves->get($id)) {
                $e += ['sors' => 'kezmuves', 'gyujtemeny' => [Gyujtemeny::Kezmuvesek->value], 'license' => MediaLicense::Sajat->value,
                    'note' => "2026-os kézműves képe: {$k['name']}"];
            } elseif (($lapHol = $this->lapHol($id, $lapblokk->get($id), $lapKep->get($id), $lapSzoveg)) !== null) {
                $e += ['sors' => 'lap', 'gyujtemeny' => [Gyujtemeny::Lapok->value], 'license' => MediaLicense::Sajat->value,
                    'note' => "2026-os {$lapHol}"];
            } elseif (str_contains($temaSzoveg, $id)) {
                $e += ['sors' => 'arculat2026', 'gyujtemeny' => [Gyujtemeny::Arculat->value], 'license' => MediaLicense::Sajat->value,
                    'rights_holder' => 'Vásárhelyi Forgatag', 'note' => 'A 2026-os téma faviconja (a 2027-es téma saját példányt használ)'];
            } else {
                // Semmi nem hivatkozik rá — NEM dobjuk el: „Rendezetlen”, a felhasználó dönt (PROMPT-07).
                $demo = str_starts_with((string) $f['filename_download'], 'demo-');
                $e += ['sors' => $demo ? 'rendezetlen_demo' : 'rendezetlen', 'gyujtemeny' => [],
                    ...($m ? $kredit() : ['license' => MediaLicense::Sajat->value]),
                    'note' => $demo ? '2026-os demó-tartalom (próbakép) — semmi nem hivatkozik rá' : '2026-os fájl — semmi nem hivatkozott rá'];
            }
            $sorsok[$e['sors']] = ($sorsok[$e['sors']] ?? 0) + 1;
            $kimenet[] = $e;
        }

        $osszes = array_sum($sorsok);
        if ($osszes !== $fajlok->count()) {
            throw new RuntimeException("a sorsok összege ({$osszes}) nem egyezik a fájlok számával ({$fajlok->count()})");
        }
        // Minden galériakép, logó és borító sorsot kapott-e (a kapcsolat nem veszhetett el)?
        $this->egyezik('galériakép', $t['gallery_files'], $sorsok['galeria'] ?? 0);
        $this->egyezik('támogatói logó', $t['sponsor'], $sorsok['tamogato'] ?? 0);
        $this->egyezik('programborító', $t['program_item'], $sorsok['program2026'] ?? 0);

        $leiras = [
            'forras' => basename($mentes),
            'keszult' => now()->toIso8601String(),
            'fajlok_szama' => $fajlok->count(),
            'sorsok' => $sorsok,
            'kihagyva' => [self::ONKENTES => 'Önkéntes-jelentkezések mellékletei (szülői hozzájárulás, motivációs levél) — SZEMÉLYES ADAT, nem médiatár-anyag; a mentésben maradnak.'],
            'szintek' => collect($t['sponsor_tier'])->map(fn ($r) => ['id' => $r['id'], 'name' => $r['name'], 'name_ro' => $r['name_ro'], 'sort' => (int) $r['sort']])->values()->all(),
            'galeriak' => collect($t['gallery'])->map(fn ($r) => ['id' => $r['id'], 'year' => (int) $r['year'], 'title_hu' => $r['title_hu'], 'title_ro' => $r['title_ro'], 'credit' => $r['credit'], 'sort' => (int) $r['sort']])->values()->all(),
            'tamogatok' => collect($t['sponsor'])->map(fn ($r) => ['id' => $r['id'], 'name' => $r['name'], 'tier' => $r['tier'], 'url' => $r['url'], 'sort' => (int) $r['sort'], 'logo' => $r['logo']])->values()->all(),
            'fajlok' => $kimenet,
        ];
        file_put_contents(base_path(self::LEIRAS), json_encode($leiras, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

        $this->info('CSOMAG: leírás → ' . self::LEIRAS);
        foreach ($sorsok as $k => $v) {
            $this->line(sprintf('  %-18s %5d', $k, $v));
        }
        $this->line(sprintf('  %-18s %5d', 'ÖSSZESEN', $osszes));

        return $leiras;
    }

    /** Melyik aloldal használja (lapblokk képe, aloldal nyitóképe, vagy a lap szövegében/szekcióiban hivatkozott). */
    private function lapHol(string $id, ?array $blokk, ?array $lap, Collection $lapSzoveg): ?string
    {
        if ($blokk !== null) {
            $szulo = $lapSzoveg->first(fn ($x) => $x['r']['id'] === $blokk['page'])['r']['slug'] ?? null;

            return 'aloldal képe (lapblokk' . ($szulo ? ", {$szulo}" : ', laphoz nem kötött blokk') . ')';
        }
        $lap ??= $lapSzoveg->first(fn ($x) => str_contains($x['s'], $id))['r'] ?? null;

        return $lap !== null ? "aloldal képe: {$lap['slug']}" : null;
    }

    private function egyezik(string $mi, array $sorok, int $db): void
    {
        if (count($sorok) !== $db) {
            throw new RuntimeException("{$mi}: a mentésben " . count($sorok) . ", sorsot kapott {$db} — kapcsolat veszett el");
        }
    }

    /** @param array<string, ?string> $f */
    private function fajlAlap(string $mentes, array $f, ?string $sha): array
    {
        $forras = "{$mentes}/fajltar/files/{$f['filename_disk']}";
        if (! is_file($forras)) {
            throw new RuntimeException("hiányzik a fájl a mentésből: {$f['filename_disk']}");
        }
        if ($sha === null || hash_file('sha256', $forras) !== $sha) {
            throw new RuntimeException("a fájl ellenőrző összege nem egyezik a mentés leltárával: {$f['filename_disk']}");
        }
        // A típust a fájl TARTALMÁBÓL vesszük — két fájl „application/octet-stream” volt, „… másolata” névvel (leltár 9.).
        $tipus = (new \finfo(FILEINFO_MIME_TYPE))->file($forras) ?: (string) $f['type'];
        $ext = match ($tipus) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
            'image/svg+xml' => 'svg', 'application/pdf' => 'pdf',
            default => throw new RuntimeException("ismeretlen fájltípus ({$tipus}): {$f['filename_disk']}"),
        };

        return [
            'id' => $f['id'],
            'forras' => $f['filename_disk'],
            'cel' => "{$f['id']}.{$ext}",
            'type' => $tipus,
            'ext' => $ext,
            'type_2026' => $f['type'] !== $tipus ? $f['type'] : null,
            'size' => (int) filesize($forras),
            'width' => $f['width'] !== null ? (int) $f['width'] : null,
            'height' => $f['height'] !== null ? (int) $f['height'] : null,
            'sha256' => $sha,
            'title' => $f['title'] ?: pathinfo((string) $f['filename_download'], PATHINFO_FILENAME),
            'eredeti_nev' => $f['filename_download'],
            'created' => $f['created_on'],
        ];
    }

    /** A fájlok zip-ekbe, tömörítés nélkül (a JPEG/WebP úgysem tömöríthető), ~--darab MB-onként. */
    private function zipek(string $mentes, string $kimenet, array $leiras): void
    {
        if (! is_dir($kimenet) && ! mkdir($kimenet, 0775, true)) {
            throw new RuntimeException("nem hozható létre: {$kimenet}");
        }
        foreach (glob("{$kimenet}/import-2026-*.zip") as $regi) {
            unlink($regi);
        }
        $hatar = (int) $this->option('darab') * 1024 * 1024;
        $n = 0;
        $meret = PHP_INT_MAX;
        $zip = null;
        $db = 0;
        foreach ($leiras['fajlok'] as $f) {
            if ($meret + $f['size'] > $hatar) {
                $zip?->close();
                $zip = new ZipArchive();
                $nev = sprintf('%s/import-2026-%d.zip', $kimenet, ++$n);
                if ($zip->open($nev, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException("nem hozható létre: {$nev}");
                }
                $meret = 0;
            }
            $belso = 'fajlok/' . $f['forras'];
            $zip->addFile("{$mentes}/fajltar/files/{$f['forras']}", $belso);
            $zip->setCompressionName($belso, ZipArchive::CM_STORE);
            $meret += $f['size'];
            $db++;
        }
        $zip?->close();
        $this->info("CSOMAG: {$db} fájl, {$n} zip → {$kimenet}");
        foreach (glob("{$kimenet}/import-2026-*.zip") as $z) {
            $this->line(sprintf('  %s  %.1f MB', basename($z), filesize($z) / 1048576));
        }
    }
}
