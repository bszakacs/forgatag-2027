<?php

namespace App\Support;

use App\Models\Venue;

/**
 * A PROGRAMKERESŐ TÉRKÉPÉNEK adata — a 2026-os `lib/finder-map.ts` `buildFinderMaps()` betűre (CLAUDE.md 2/e).
 *
 * Két térkép (vár, város): a rajz a public/terkeprajz/ alatt (a 2026-os fájlok; NEM „terkep”: az a /terkep lap címe — a mappa elnyelné, 2026-11-04), a jelölő a rajz dobozához mért SZÁZALÉK
 * (venues.map_x/map_y, a térkép-szerkesztő tölti; a 2026-os adat a HelyszinTerkepImport-tal jött). A helyszín azonosítója
 * a SLUG (2026-ban a lap-adatcsomag indexe volt — a szerepe ugyanaz: a helyszín-szűrő kulcsa).
 *  · jelölő: zóna + x/y van, és nem szélső; sorszám szerint; `kids` = a termei (pakli);
 *  · szélső jelvény: `map_edge` — a térkép SZÉLÉRE kerül, irány (`map_bearing`) szerint, rövid névvel;
 *  · jelmagyarázat: MINDEN helyszín (a 0 programosak is), szülő → termei, sorszám szerint, utána név szerint;
 *    ami nincs a térképen, az halvány, de ott van (néma kimaradás nincs).
 */
final class Terkep
{
    /** A két rajz — a 2026-os lib/map-assets.ts értékei (a jelölők ehhez a dobozhoz képest állnak százalékban). */
    public const RAJZOK = [
        'castle' => ['src' => 'terkeprajz/varterkep-vektor.svg', 'w' => 779.93, 'h' => 475.18, 'hu' => 'Vár', 'ro' => 'Cetate'],
        'city' => ['src' => 'terkeprajz/varosterkep-2000.webp', 'w' => 2000, 'h' => 1194, 'hu' => 'Város', 'ro' => 'Oraș'],
    ];

    /** A 8 irány felirata (2026: lib/offmap.ts BEARINGS — a szélső jelvény akadálymentes címkéjében). */
    public const IRANYOK = [0 => 'fel (észak)', 45 => 'jobbra fel', 90 => 'jobbra (kelet)', 135 => 'jobbra le',
        180 => 'le (dél)', 225 => 'balra le', 270 => 'balra (nyugat)', 315 => 'balra fel'];

    /**
     * @return array{zonak: list<array{zone: string, label: string, src: string, w: float, h: float, marks: list<array>, edges: list<array>}>, jelmagyarazat: list<array{head: array, kids: list<array>}>}
     */
    public static function adat(string $nyelv): array
    {
        $mind = Venue::query()->orderBy('name')->get();
        $nev = fn (Venue $v) => Nyelv::valaszt($v->name, $v->name_ro, $nyelv);
        $szulo = $mind->keyBy('id');
        $kidsOf = fn (Venue $v) => $mind->where('parent_id', $v->id)->pluck('slug')->values()->all();
        $sorszam = fn (Venue $v) => $v->map_no ?? '';
        $zonaja = fn (Venue $v) => $v->map_zone?->value ?? 'none';

        $zonak = [];
        foreach (self::RAJZOK as $zona => $r) {
            $benne = $mind->filter(fn ($v) => $zonaja($v) === $zona);
            $marks = $benne->filter(fn ($v) => ! $v->map_edge && $v->map_x !== null && $v->map_y !== null)
                ->sortBy(fn ($v) => (int) $v->map_no)->values()
                ->map(fn ($v) => ['slug' => $v->slug, 'no' => $sorszam($v), 'x' => (float) $v->map_x, 'y' => (float) $v->map_y,
                    'name' => $nev($v), 'kids' => $kidsOf($v)])->all();
            $edges = $benne->filter(fn ($v) => $v->map_edge)->sortBy(fn ($v) => (int) $v->map_no)->values()
                ->map(fn ($v) => ['slug' => $v->slug, 'no' => $sorszam($v), 'name' => $nev($v),
                    'label' => $v->short_name ?: $nev($v), 'bearing' => (int) $v->map_bearing])->all();
            $zonak[] = ['zone' => $zona, 'label' => $r[$nyelv === 'ro' ? 'ro' : 'hu'], 'src' => $r['src'], 'w' => $r['w'], 'h' => $r['h'],
                'marks' => $marks, 'edges' => $edges];
        }

        // JELMAGYARÁZAT (2026: legend) — sorszám szerint (a sorszámosak elöl), egyébként név szerint.
        $rend = function (Venue $a, Venue $b): int {
            $an = (int) $a->map_no;
            $bn = (int) $b->map_no;
            if ($an && $bn) {
                return $an <=> $bn;
            }
            if ($an || $bn) {
                return $an ? -1 : 1;
            }

            return strcoll((string) $a->name, (string) $b->name);
        };
        $tetel = fn (Venue $v) => ['slug' => $v->slug, 'no' => $sorszam($v), 'name' => $nev($v),
            'onMap' => $v->map_edge || ($v->map_x !== null && $v->map_y !== null), 'zone' => $zonaja($v) === 'city' ? 'city' : 'castle'];
        $szulok = $mind->whereNull('parent_id')->sort($rend)->values();
        $jelmagyarazat = $szulok->map(fn ($v) => ['head' => $tetel($v),
            'kids' => $mind->where('parent_id', $v->id)->sort($rend)->values()->map($tetel)->all()])->all();
        // akinek a szülője nincs a listában, önálló sorként kerül be (ne vesszen el)
        foreach ($mind->filter(fn ($v) => $v->parent_id && ! $szulo->has($v->parent_id))->sort($rend) as $v) {
            $jelmagyarazat[] = ['head' => $tetel($v), 'kids' => []];
        }

        // A CSALÁD (2026: VKIDS): szülő-slug → a termei — a „szülő kiválasztva = a termei is” kiterjesztéshez (venueSel).
        $csalad = $mind->whereNotNull('parent_id')->groupBy(fn ($v) => $szulo->get($v->parent_id)?->slug)
            ->filter(fn ($g, $k) => $k !== '')->map(fn ($g) => $g->pluck('slug')->values()->all())->all();

        return ['zonak' => $zonak, 'jelmagyarazat' => $jelmagyarazat, 'csalad' => $csalad];
    }

    /**
     * A helyszín lapjának térképszáma (PROMPT-15, tulajdonosi döntés 2026-11-04): a saját jelölője, vagy — ha terem és
     * nincs saját pozíciója — a SZÜLŐ jelölője (a térképen is az képviseli). Sorszám és hely nélkül null (nem találunk ki).
     *
     * @return ?array{no: int, slug: string}
     */
    public static function jeloloje(Venue $v): ?array
    {
        $rajta = fn (?Venue $x) => $x !== null && $x->map_no !== null && ($x->map_edge || ($x->map_x !== null && $x->map_y !== null));
        $cel = $rajta($v) ? $v : ($rajta($v->parent) ? $v->parent : null);

        return $cel === null ? null : ['no' => (int) $cel->map_no, 'slug' => $cel->slug];
    }

    /**
     * A szűrésre frissülő ADAT (a térkép ebből frissíti magát, nem rajzolódik újra): helyszínenként a mostani szűrésre eső
     * programszám (a szülőnél a termeivel együtt — 2026: venueFamily), és a kiválasztott helyszínek.
     *
     * @param  array<string, int>  $helyszinDb  ProgramKereso::darabok()['helyszin']
     * @param  list<string>  $kivalasztva
     * @return array{db: array<string, int>, sel: list<string>}
     */
    public static function allapot(array $helyszinDb, array $kivalasztva): array
    {
        return ['db' => (object) $helyszinDb, 'sel' => array_values($kivalasztva)];
    }
}
