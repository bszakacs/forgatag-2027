<?php

namespace App\Support;

use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A helyszínek 2026-os adatai a mentésből — három lépés, ismételhetően, mindig CSAK az üresbe írva:
 *
 * 1. PÓTLÁS (tulajdonosi döntés, 2026-11-04, PROMPT-15): a 2027-es kezdőlistából hiányzó 8 helyszín (POTLANDO) 2027-ben
 *    is helyszín — ha nincs az adatbázisban, a 2026-os nevével, címével és szülőjével létrejön. (A helyszínlista egészét
 *    ez nem rendezi: a szervezők a tavaszi beküldés előtt nézik át — ALLAPOT.md, nyitott kérdések.)
 * 2. A ROMÁN NÉV ÉS CÍM (2026-ban minden helyszínnek volt román neve; a 2027-es kezdőlista román név nélkül jött):
 *    ahol a 2027-es mező üres, a 2026-os értéket kapja. Kitöltött mezőhöz nem nyúl.
 * 3. A TÉRKÉP-POZÍCIÓ (PROMPT-12): zóna (vár/város), x/y (a rajz dobozához mért százalék), sorszám, „a térkép szélén”
 *    jelölés és irány, rövid név — kitalálás nélkül, CSAK az érintetlen helyszínbe (a térkép-szerkesztőben később
 *    beállított értékhez nem nyúl). A 2026-ban pozíció nélküli pozíció nélkül marad; a szélre tett ugyanúgy a szélen lesz;
 *    a terem (szülővel) a szülő jelölőjén.
 * A csomag: database/import/helyszin-terkep-2026.json (nyilvános adat; a fejlesztőgépen készül a mentésből).
 */
class HelyszinTerkepImport
{
    public const CSOMAG = 'database/import/helyszin-terkep-2026.json';

    /** A 2027-es kezdőlistából hiányzó, 2027-ben is helyszínként megtartott 2026-os helyszínek (tulajdonosi döntés, 2026-11-04). */
    public const POTLANDO = ['var-csicsergo-1', 'var-csicsergo-2', 'bernady-haz', 'var-pulzus-by-rmdsz-maros',
        'studium-hub-eloadoterem', 'fociakademia', 'bolyai-szabadulo-szoba', 'uat-galeria'];

    /** @return array{helyszin: int, poziciot: int, szelen: int} a fejlesztőgépen: a mentésből → a csomag */
    public static function csomagol(string $mentes): array
    {
        $t = Mentes2026::tablak($mentes, ['venue']);
        $szulo = array_column($t['venue'], 'slug', 'id');
        $sorok = [];
        foreach ($t['venue'] as $v) {
            $sorok[] = [
                'slug' => $v['slug'],
                'name' => $v['name'],
                'name_ro' => $v['name_ro'] ?: null,
                'address' => $v['address'] ?: null,
                'address_ro' => $v['address_ro'] ?: null,
                'parent' => $v['parent_venue'] ? ($szulo[$v['parent_venue']] ?? null) : null,
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
            '_forras' => 'A 2026-os helyszínek neve, román neve, címe, szülője és térkép-mezői (App\Support\HelyszinTerkepImport). Mentés: MENTES-2026/2026-10-21.',
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
        $sorok = collect($csomag['helyszinek'])->keyBy('slug');

        $potolva = [];
        $roman = 0;
        $kitoltve = 0;
        $nincsListan = [];
        $pozicioNelkul = [];
        DB::transaction(function () use ($sorok, &$potolva, &$roman, &$kitoltve, &$nincsListan, &$pozicioNelkul): void {
            // 1. A 8 hiányzó helyszín (a szülő a 2026-os, ha a 2027-es listában megvan).
            foreach (self::POTLANDO as $slug) {
                $s = $sorok->get($slug) ?? throw new RuntimeException("a pótlandó „{$slug}” helyszín nincs a csomagban");
                if (Venue::query()->where('slug', $slug)->exists()) {
                    continue;
                }
                $szulo = $s['parent'] ? Venue::query()->where('slug', $s['parent'])->value('id') : null;
                if ($s['parent'] && $szulo === null) {
                    throw new RuntimeException("a pótlandó „{$slug}” szülője („{$s['parent']}”) nincs a 2027-es listában");
                }
                Venue::create(['slug' => $slug, 'name' => $s['name'], 'address' => $s['address'], 'parent_id' => $szulo]);
                $potolva[] = $s['name'];
            }

            foreach ($sorok as $s) {
                $v = Venue::query()->where('slug', $s['slug'])->first();
                if ($v === null) {
                    $nincsListan[] = $s['slug'];

                    continue;
                }
                // 2. Román név és cím — csak az üresbe.
                foreach (['name_ro', 'address_ro'] as $mezo) {
                    if (blank($v->{$mezo}) && filled($s[$mezo])) {
                        $v->{$mezo} = $s[$mezo];
                    }
                }
                if ($v->isDirty()) {
                    $v->save();
                    $roman++;
                }

                // 3. Térkép — csak az érintetlenbe.
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

        $naplo($potolva ? count($potolva) . ' hiányzó helyszín pótolva (tulajdonosi döntés, 2026-11-04): ' . implode(' · ', $potolva) . '.'
            : 'a 8 pótlandó helyszín már mind megvan.');
        $naplo("{$roman} helyszín román neve/címe pótolva a 2026-os mentésből (a kitöltötthöz nem nyúlt).");
        $naplo("{$kitoltve} helyszín térkép-adata kitöltve a 2026-os mentésből (a már beállítotthoz nem nyúlt).");
        if ($pozicioNelkul) {
            $naplo('2026-ban sem volt pozíciója (a térkép-szerkesztőben kell a helyére húzni): ' . implode(' · ', $pozicioNelkul));
        }
        if ($nincsListan) {
            $naplo('a 2026-os helyszín NINCS a 2027-es listában, a térképre nem kerül: ' . implode(', ', $nincsListan));
        }
    }
}
