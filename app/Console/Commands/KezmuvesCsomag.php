<?php

namespace App\Console\Commands;

use App\Support\KezmuvesImport;
use App\Support\Mentes2026;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * A 2026-os KÉZMŰVESEK csomagolása — a fejlesztőgépen fut, a mentésből (MENTES-LELTAR.md), NEM a szerveren.
 * Kimenet: database/import/kezmuvesek-2026.json (a tárolóba kerül; nyilvános adat: a 2026-os Kézműves vásár lapján
 * látszó név, bemutatkozás és a kép azonosítója). A szerveren a forgatag:kezmuves-import tölti be.
 *
 * Futtatás:
 *   docker compose run --rm -v "E:/Work/mysites/forgatag.ro/MENTES-2026:/mentes" app \
 *     php artisan forgatag:kezmuves-csomag /mentes/2026-10-21
 */
#[Signature('forgatag:kezmuves-csomag {mentes : a mentés mappája (benne adatbazis/)}')]
#[Description('A 2026-os kezmuvesek csomagolasa a mentesbol (fejlesztogepen fut)')]
class KezmuvesCsomag extends Command
{
    public function handle(): int
    {
        try {
            $t = Mentes2026::tablak((string) $this->argument('mentes'), ['craftsperson', 'directus_files']);
        } catch (RuntimeException $e) {
            $this->error('CSOMAG: ' . $e->getMessage());

            return self::FAILURE;
        }

        $fajlok = array_flip(array_column($t['directus_files'], 'id'));
        $sorok = collect($t['craftsperson'])
            ->sortBy(fn ($k) => [(int) $k['sort'], $k['name']])
            ->map(function ($k) use ($fajlok) {
                if ($k['image'] !== null && ! isset($fajlok[$k['image']])) {
                    throw new RuntimeException("{$k['name']}: a kép ({$k['image']}) nincs a mentés fájltárában");
                }

                return [
                    'id' => $k['id'], 'name' => trim($k['name']),
                    'description_hu' => $k['description_hu'], 'description_ro' => $k['description_ro'],
                    'kep' => $k['image'], 'sort' => (int) $k['sort'], 'visible' => $k['visible'] === 't',
                ];
            })->values()->all();

        $csomag = [
            'forras' => 'MENTES-2026/2026-10-21 (Directus PostgreSQL-mentés, craftsperson)',
            'szamok' => [
                'kézműves' => count($sorok),
                'látható' => count(array_filter($sorok, fn ($k) => $k['visible'])),
                'képpel' => count(array_filter($sorok, fn ($k) => $k['kep'] !== null)),
                'román bemutatkozással' => count(array_filter($sorok, fn ($k) => filled($k['description_ro']))),
            ],
            'kezmuvesek' => $sorok,
        ];
        file_put_contents(base_path(KezmuvesImport::LEIRAS), json_encode($csomag, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        foreach ($csomag['szamok'] as $mi => $db) {
            $this->line("  {$mi}: {$db}");
        }
        $this->info('CSOMAG: kész → ' . KezmuvesImport::LEIRAS);

        return self::SUCCESS;
    }
}
