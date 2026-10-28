<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\SponsorTier;
use App\Models\ThemeVersion;
use App\Models\Venue;
use App\Support\Settings;
use App\Support\Theme;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A kezdő tartalom betöltése a seed/ mappából (CLAUDE.md 2/b): beállítások, kategóriák,
 * helyszínek, szponzorszintek, a kezdő téma.
 *
 * CSAK ÜRES TÁBLÁBA ír (a beállításoknál: csak a hiányzó blokkot). Ha egy táblában már van adat, ahhoz nem nyúl — így az adminban törölt
 * vagy átírt sor soha nem jön vissza (2026-ban a menü-feltöltés épp ezt csinálta).
 * A telepítés (.cpanel.yml) minden alkalommal futtatja; a második futástól már csak kihagy.
 */
#[Signature('forgatag:torzsadatok')]
#[Description('Torzsadatok betoltese a seed/ mappabol (csak ures tablaba)')]
class Torzsadatok extends Command
{
    public function handle(): int
    {
        try {
            $this->beallitasok();
            $this->betolt('kategóriák', Category::class, fn () => $this->kategoriak());
            $this->betolt('helyszínek', Venue::class, fn () => $this->helyszinek());
            // Csak az év nélküli (2027-es kezdő) szintek számítanak: a 2026-ból behozott szintek (year=2026,
            // forgatag:mediatar-import) ne akadályozzák meg a kezdőlista betöltését.
            $this->betolt('szponzorszintek', SponsorTier::class, fn () => $this->szponzorszintek(),
                fn () => SponsorTier::whereNull('year')->count());
            $this->betolt('téma', ThemeVersion::class, fn () => $this->tema());
        } catch (RuntimeException $e) {
            $this->error('TÖRZSADATOK: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    private function betolt(string $nev, string $model, callable $tolto, ?callable $szamlalo = null): void
    {
        $meglevo = $szamlalo ? $szamlalo() : $model::count();
        if ($meglevo > 0) {
            $this->line("TÖRZSADATOK: {$nev} — már van {$meglevo} sor, nem nyúltam hozzá.");

            return;
        }

        $db = DB::transaction(fn () => $tolto());
        $this->info("TÖRZSADATOK: {$nev} — {$db} sor betöltve.");
    }

    /**
     * A Beállítások blokkjai: BLOKKONKÉNT csak a még hiányzót tölti be — így egy később érkező új
     * blokk (pl. szabadulószoba) magától bekerül, a meglévő, adminban átírt blokkhoz viszont nem nyúl.
     */
    private function beallitasok(): void
    {
        $ut = base_path('seed/settings.json');
        $adat = is_readable($ut) ? json_decode((string) file_get_contents($ut), true) : null;
        if (! is_array($adat)) {
            throw new RuntimeException('seed/settings.json hiányzik vagy hibás JSON.');
        }

        foreach ($adat as $blokk => $ertek) {
            if (str_starts_with($blokk, '_')) {
                continue; // magyarázó mező
            }
            if (Settings::has($blokk)) {
                $this->line("TÖRZSADATOK: beállítások / {$blokk} — már megvan, nem nyúltam hozzá.");

                continue;
            }
            Settings::save([$blokk => $ertek]);
            $this->info("TÖRZSADATOK: beállítások / {$blokk} — betöltve.");
        }
    }

    private function kategoriak(): int
    {
        $sorok = $this->csv('categories.csv', ['slug', 'name_hu', 'name_ro']);
        foreach ($sorok as $i => $sor) {
            Category::create(['slug' => $sor['slug'], 'name_hu' => $sor['name_hu'], 'name_ro' => $sor['name_ro'] ?: null, 'sort' => $i + 1]);
        }

        return count($sorok);
    }

    private function helyszinek(): int
    {
        $sorok = $this->csv('venues.csv', ['slug', 'name', 'parent_slug', 'address']);

        // 1. kör: minden helyszín, szülő nélkül.
        $id = [];
        foreach ($sorok as $sor) {
            $id[$sor['slug']] = Venue::create([
                'slug' => $sor['slug'],
                'name' => $sor['name'],
                'address' => $sor['address'] ?: null,
            ])->id;
        }

        // 2. kör: a szülők bekötése (a CSV-ben a gyerek a szülő előtt is állhat).
        foreach ($sorok as $sor) {
            if ($sor['parent_slug'] === '') {
                continue;
            }
            if (! isset($id[$sor['parent_slug']])) {
                throw new RuntimeException("venues.csv: a(z) „{$sor['slug']}” szülője („{$sor['parent_slug']}”) nincs a listában.");
            }
            Venue::whereKey($id[$sor['slug']])->update(['parent_id' => $id[$sor['parent_slug']]]);
        }

        return count($sorok);
    }

    private function szponzorszintek(): int
    {
        $sorok = $this->csv('sponsor_tiers.csv', ['sort', 'name']);
        foreach ($sorok as $sor) {
            SponsorTier::create(['name' => $sor['name'], 'sort' => (int) $sor['sort']]);
        }

        return count($sorok);
    }

    /** A kezdő téma (seed/tema/) — lásd Theme::importSeed. */
    private function tema(): int
    {
        Theme::importSeed('forgatag:torzsadatok');

        return 1;
    }

    /**
     * @param  list<string>  $oszlopok  a kötelező fejléc
     * @return list<array<string,string>>
     */
    private function csv(string $fajl, array $oszlopok): array
    {
        $ut = base_path("seed/{$fajl}");
        $h = is_readable($ut) ? fopen($ut, 'r') : false;
        if ($h === false) {
            throw new RuntimeException("nem olvasható: seed/{$fajl}");
        }

        $fejlec = fgetcsv($h, escape: '');
        $fejlec = array_map(fn ($o) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $o)), $fejlec ?: []);
        if ($fejlec !== $oszlopok) {
            fclose($h);
            throw new RuntimeException("seed/{$fajl}: a fejléc „" . implode(',', $fejlec) . "”, várt: „" . implode(',', $oszlopok) . '”.');
        }

        $sorok = [];
        $sorszam = 1;
        while (($mezok = fgetcsv($h, escape: '')) !== false) {
            $sorszam++;
            if ($mezok === [null]) {
                continue; // üres sor
            }
            if (count($mezok) !== count($oszlopok)) {
                fclose($h);
                throw new RuntimeException("seed/{$fajl} {$sorszam}. sor: " . count($mezok) . ' mező, várt: ' . count($oszlopok) . '.');
            }
            $sorok[] = array_combine($oszlopok, array_map('trim', $mezok));
        }
        fclose($h);

        if ($sorok === []) {
            throw new RuntimeException("seed/{$fajl}: nincs benne adat.");
        }

        return $sorok;
    }
}
