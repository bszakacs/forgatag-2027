<?php

namespace App\Support;

use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A helyszínek TÉRKÉP-POZÍCIÓJA a 2026-os mentésből (PROMPT-12): a 2027-es `venues` tábla térkép-mezői üresek voltak,
 * a térkép nélkülük üres. Átjön BETŰRE, ami 2026-ban volt — zóna (vár/város), x/y (a rajz dobozához mért százalék),
 * sorszám, „a térkép szélén” jelölés és irány, rövid név —, kitalálás nélkül:
 *  · a 2026-ban pozíció nélküli helyszín pozíció nélkül marad; ha 2026-ban a térkép SZÉLÉN volt (irányjelvénnyel),
 *    ugyanúgy ott lesz; ha terem volt (szülővel), a szülő jelölője képviseli, mint 2026-ban;
 *  · CSAK az érintetlen helyszínt tölti ki (zóna „none”, nincs x/y, sorszám, szélső jelölés) — a térkép-szerkesztőben
 *    később beállított értékhez nem nyúl; ismételhető.
 * A csomag: database/import/helyszin-terkep-2026.json (nyilvános adat; a fejlesztőgépen készül a mentésből).
 */
class HelyszinTerkepImport
{
    public const CSOMAG = 'database/import/helyszin-terkep-2026.json';

    /** @return array{helyszin: int, poziciot: int, szelen: int} a fejlesztőgépen: a mentésből → a csomag */
    public static function csomagol(string $mentes): array
    {
        $t = Mentes2026::tablak($mentes, ['venue']);
        $sorok = [];
        foreach ($t['venue'] as $v) {
            $sorok[] = [
                'slug' => $v['slug'],
                'map_zone' => $v['map_zone'] ?: 'none',
                'map_x' => $v['map_x'] === null ? null : (float) $v['map_x'],
                'map_y' => $v['map_y'] === null ? null : (float) $v['map_y'],
                'map_no' => $v['map_no'] === null ? null : (int) $v['map_no'],
                'map_edge' => $v['map_edge'] === 't',
                'map_bearing' => $v['map_bearing'] === null ? null : (int) $v['map_bearing'],
                'short_name' => $v['short_name'] ?: null,
            ];
        }
        usort($sorok, fn ($a, $b) => strcmp($a['slug'], $b['slug']));
        file_put_contents(base_path(self::CSOMAG), json_encode([
            '_forras' => 'A 2026-os helyszínek térkép-mezői (App\Support\HelyszinTerkepImport). Mentés: MENTES-2026/2026-10-21.',
            'helyszinek' => $sorok,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'helyszin' => count($sorok),
            'poziciot' => count(array_filter($sorok, fn ($s) => $s['map_x'] !== null)),
            'szelen' => count(array_filter($sorok, fn ($s) => $s['map_edge'])),
        ];
    }

    /** A szerveren (telepítéskor), ismételhetően. */
    public function run(callable $naplo): void
    {
        $csomag = json_decode((string) @file_get_contents(base_path(self::CSOMAG)), true)
            ?? throw new RuntimeException('a csomag nem olvasható: ' . self::CSOMAG);

        $kitoltve = 0;
        $nincsListan = [];
        $pozicioNelkul = [];
        DB::transaction(function () use ($csomag, &$kitoltve, &$nincsListan, &$pozicioNelkul): void {
            foreach ($csomag['helyszinek'] as $s) {
                $v = Venue::query()->where('slug', $s['slug'])->first();
                if ($v === null) {
                    $nincsListan[] = $s['slug'];

                    continue;
                }
                if ($s['map_x'] === null && ! $s['map_edge']) {
                    $pozicioNelkul[] = $v->name . ($v->parent_id ? ' (terem — a szülő jelölője képviseli)' : '');
                }
                $erintetlen = ($v->map_zone?->value ?? 'none') === 'none' && $v->map_x === null && $v->map_no === null && ! $v->map_edge;
                if (! $erintetlen) {
                    continue;
                }
                $v->forceFill([
                    'map_zone' => $s['map_zone'], 'map_x' => $s['map_x'], 'map_y' => $s['map_y'], 'map_no' => $s['map_no'],
                    'map_edge' => $s['map_edge'], 'map_bearing' => $s['map_bearing'],
                ]);
                if (blank($v->short_name) && $s['short_name'] !== null) {
                    $v->short_name = $s['short_name'];
                }
                if ($v->isDirty()) {
                    $v->save();
                    $kitoltve++;
                }
            }
        });

        $naplo("{$kitoltve} helyszín térkép-adata kitöltve a 2026-os mentésből (a már beállítotthoz nem nyúlt).");
        if ($pozicioNelkul) {
            $naplo('2026-ban sem volt pozíciója (a szervezők pótolják a térkép-szerkesztőben): ' . implode(' · ', $pozicioNelkul));
        }
        if ($nincsListan) {
            $naplo('a 2026-os helyszín NINCS a 2027-es listában, a térképre nem kerül: ' . implode(', ', $nincsListan));
        }
    }
}
