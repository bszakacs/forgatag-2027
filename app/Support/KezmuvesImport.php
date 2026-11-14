<?php

namespace App\Support;

use App\Models\Craftsperson;
use App\Models\Media;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A 2026-os kézművesek behozatala (PROMPT-17) a csomagból (database/import/kezmuvesek-2026.json — a fejlesztőgépen a
 * forgatag:kezmuves-csomag készíti a mentésből). A telepítés futtatja (forgatag:kezmuves-import), a médiatár-behozatal UTÁN:
 * a képek a médiatár `legacy_id`-jén át kötődnek — nem töltődnek fel újra.
 *
 * Csak ÜRES táblába ír (mint a lapok és a menü): ha az adminban már van kézműves, nem nyúl hozzá. Ha egy kép hiányzik a
 * médiatárból, SEMMIT nem ír, és hibával áll meg (néma félbehozatal nincs).
 */
class KezmuvesImport
{
    public const LEIRAS = 'database/import/kezmuvesek-2026.json';

    /** @return array{behozva: int} */
    public function run(callable $naplo): array
    {
        $fajl = base_path(self::LEIRAS);
        $csomag = json_decode((string) @file_get_contents($fajl), true);
        if (! is_array($csomag) || ! isset($csomag['kezmuvesek'])) {
            throw new RuntimeException('hiányzik vagy hibás a csomag: ' . self::LEIRAS);
        }
        if (Craftsperson::query()->exists()) {
            $naplo('a kézművesek táblája nem üres — kihagyva (az adminban felvett adathoz nem nyúl).');

            return ['behozva' => 0];
        }

        $kell = array_values(array_filter(array_column($csomag['kezmuvesek'], 'kep')));
        $kepek = Media::query()->whereIn('legacy_id', $kell)->pluck('id', 'legacy_id')->all();
        $hiany = array_diff($kell, array_keys($kepek));
        if ($hiany !== []) {
            throw new RuntimeException(count($hiany) . ' kézműves képe nincs a médiatárban (előbb a médiatár-behozatal kell): ' . implode(', ', array_slice($hiany, 0, 3)));
        }

        DB::transaction(function () use ($csomag, $kepek) {
            foreach ($csomag['kezmuvesek'] as $k) {
                Craftsperson::create([
                    'name' => $k['name'], 'description_hu' => $k['description_hu'], 'description_ro' => $k['description_ro'],
                    'image_media_id' => $k['kep'] !== null ? $kepek[$k['kep']] : null,
                    'sort' => $k['sort'], 'visible' => $k['visible'], 'legacy_id' => $k['id'],
                ]);
            }
        });
        $db = count($csomag['kezmuvesek']);
        $naplo("{$db} kézműves behozva (" . count($kell) . ' képpel, a médiatárból).');

        return ['behozva' => $db];
    }
}
