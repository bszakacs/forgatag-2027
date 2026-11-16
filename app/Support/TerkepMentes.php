<?php

namespace App\Support;

use App\Enums\MapZone;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A térkép-szerkesztő (PROMPT-18) EGYETLEN író-útja: a böngészőben húzott pozíciók ellenőrzése és mentése.
 *
 * A tárolt adat (2026: MapEditorView PATCH-törzse betűre): `map_zone` · `map_x` · `map_y` (SZÁZALÉK a rajz dobozához,
 * 0–100) · `map_no` (sorszám a jelölőn) · `short_name` · `map_edge` + `map_bearing` (a térkép SZÉLÉN, irányjelvénnyel —
 * ilyenkor NINCS x/y). Három érvényes állapot:
 *  · a térképen: zóna (vár/város) + x/y, nem szélső, irány nincs;
 *  · a szélén:   zóna (vár/város) + szélső + a 8 irány egyike, x/y nincs;
 *  · nincs rajta: zóna none, minden üres.
 * Hibás sor esetén SEMMI nem íródik ki (egy tranzakció), és a hiba emberi nyelven jön vissza — néma hiba nincs.
 */
final class TerkepMentes
{
    /** A 8 választható irány (fok, 0 = fel). A feliratok: Terkep::IRANYOK — ugyanaz a kulcslista. */
    public const IRANYOK = [0, 45, 90, 135, 180, 225, 270, 315];

    /**
     * @param  list<array<string, mixed>>  $sorok
     * @return int a mentett helyszínek száma
     *
     * @throws InvalidArgumentException emberi nyelvű hibaüzenettel
     */
    public static function ment(array $sorok): int
    {
        if ($sorok === []) {
            throw new InvalidArgumentException('Nincs mit menteni.');
        }
        $tiszta = [];
        foreach ($sorok as $s) {
            $v = Venue::find((int) ($s['id'] ?? 0));
            if ($v === null) {
                throw new InvalidArgumentException('Ismeretlen helyszín (azonosító: ' . e((string) ($s['id'] ?? '?')) . ') — töltsd újra a lapot.');
            }
            $tiszta[] = [$v, self::ellenoriz($v->name, $s)];
        }

        DB::transaction(function () use ($tiszta): void {
            foreach ($tiszta as [$v, $adat]) {
                $v->forceFill($adat + ['map_set_at' => now()])->save();
            }
        });

        return count($tiszta);
    }

    /** @return array<string, mixed> */
    private static function ellenoriz(string $nev, array $s): array
    {
        $hiba = fn (string $mi) => new InvalidArgumentException("„{$nev}”: {$mi}");

        $zona = MapZone::tryFrom((string) ($s['map_zone'] ?? ''));
        if ($zona === null) {
            throw $hiba('ismeretlen térkép (' . e((string) ($s['map_zone'] ?? '')) . ').');
        }
        $szam = function (mixed $x) {
            return $x === null || $x === '' ? null : (is_numeric($x) ? (float) $x : false);
        };
        $x = $szam($s['map_x'] ?? null);
        $y = $szam($s['map_y'] ?? null);
        $szel = (bool) ($s['map_edge'] ?? false);
        $irany = ($s['map_bearing'] ?? null) === null ? null : (int) $s['map_bearing'];

        $no = $s['map_no'] ?? null;
        if ($no !== null && $no !== '') {
            if (! is_numeric($no) || (int) $no != $no || (int) $no < 1 || (int) $no > 999) {
                throw $hiba('a sorszám 1 és 999 közötti egész szám legyen.');
            }
            $no = (int) $no;
        } else {
            $no = null;
        }
        $rovid = trim((string) ($s['short_name'] ?? ''));
        if (mb_strlen($rovid) > 60) {
            throw $hiba('a rövid név legfeljebb 60 karakter.');
        }

        if ($zona === MapZone::None) {
            // Levéve: minden térkép-adat törlődik (2026: removeFromMap). A rövid név a helyszíné, az marad.
            return ['map_zone' => MapZone::None, 'map_x' => null, 'map_y' => null, 'map_no' => null,
                'map_edge' => false, 'map_bearing' => null, 'short_name' => $rovid ?: null];
        }
        if ($szel) {
            if (! in_array($irany, self::IRANYOK, true)) {
                throw $hiba('a térkép szélén lévő helyszínnek a 8 irány egyike kell.');
            }

            return ['map_zone' => $zona, 'map_x' => null, 'map_y' => null, 'map_no' => $no,
                'map_edge' => true, 'map_bearing' => $irany, 'short_name' => $rovid ?: null];
        }
        if ($x === null || $y === null || $x === false || $y === false || $x < 0 || $x > 100 || $y < 0 || $y > 100) {
            throw $hiba('a térképen lévő helyszínnek pozíció kell (0–100%) — húzd rá a térképre, vagy tedd a szélére.');
        }

        return ['map_zone' => $zona, 'map_x' => round($x, 2), 'map_y' => round($y, 2), 'map_no' => $no,
            'map_edge' => false, 'map_bearing' => null, 'short_name' => $rovid ?: null];
    }
}
