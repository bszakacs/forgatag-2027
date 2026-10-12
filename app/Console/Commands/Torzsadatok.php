<?php

namespace App\Console\Commands;

use App\Enums\FestivalDayKind;
use App\Models\Category;
use App\Models\FestivalDay;
use App\Models\SponsorTier;
use App\Models\Venue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A törzsadatok betöltése a seed/ mappából: programnapok, kategóriák, helyszínek, szponzorszintek.
 *
 * CSAK ÜRES TÁBLÁBA ír. Ha egy táblában már van adat, ahhoz nem nyúl — így az adminban törölt
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
            $this->betolt('programnapok', FestivalDay::class, fn () => $this->programnapok());
            $this->betolt('kategóriák', Category::class, fn () => $this->kategoriak());
            $this->betolt('helyszínek', Venue::class, fn () => $this->helyszinek());
            $this->betolt('szponzorszintek', SponsorTier::class, fn () => $this->szponzorszintek());
        } catch (RuntimeException $e) {
            $this->error('TÖRZSADATOK: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    private function betolt(string $nev, string $model, callable $tolto): void
    {
        $meglevo = $model::count();
        if ($meglevo > 0) {
            $this->line("TÖRZSADATOK: {$nev} — már van {$meglevo} sor, nem nyúltam hozzá.");

            return;
        }

        $db = DB::transaction(fn () => $tolto());
        $this->info("TÖRZSADATOK: {$nev} — {$db} sor betöltve.");
    }

    private function programnapok(): int
    {
        $ut = base_path('seed/festival-days.json');
        $adat = is_readable($ut) ? json_decode((string) file_get_contents($ut), true) : null;
        if (! is_array($adat) || empty($adat['days'])) {
            throw new RuntimeException('seed/festival-days.json hiányzik vagy nincs benne „days” lista.');
        }

        foreach ($adat['days'] as $i => $nap) {
            $tipus = FestivalDayKind::tryFrom($nap['kind'] ?? '');
            if (empty($nap['date']) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $nap['date']) || $tipus === null) {
                throw new RuntimeException('seed/festival-days.json ' . ($i + 1) . '. nap: hibás dátum vagy típus (festival | prep).');
            }
            FestivalDay::create(['date' => $nap['date'], 'kind' => $tipus]);
        }

        return count($adat['days']);
    }

    private function kategoriak(): int
    {
        $sorok = $this->csv('categories.csv', ['slug', 'name_hu']);
        foreach ($sorok as $i => $sor) {
            Category::create(['slug' => $sor['slug'], 'name_hu' => $sor['name_hu'], 'sort' => $i + 1]);
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
