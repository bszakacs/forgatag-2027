<?php

namespace App\Support;

use App\Models\Craftsperson;

/**
 * A Kézműves vásár lap adata (PROMPT-17) — EGY helyen. A 2026-os CraftspeopleView.astro számolásai betűre: a bemutatkozás
 * bekezdésekre bontva (sortörés), a kártya kivonata 170 karakter szóhatáron, „…”-val; a kártya képe 560×420, a felugró
 * ablaké 900×506, mindkettő a megjelenés arányára vágva (webp).
 */
class Kezmuvesek
{
    /** Kártya / lap (2026: PER = 18, a rács 3 × 6). */
    public const PER = 18;

    /** @return list<array{nev: string, bekezdesek: list<string>, kivonat: string, kiskep: ?string, nagykep: ?string}> */
    public static function lista(string $nyelv): array
    {
        return Craftsperson::query()->lathato()->with('image')->get()->map(function (Craftsperson $k) use ($nyelv) {
            $szoveg = (string) ($nyelv === 'ro' ? ($k->description_ro ?: $k->description_hu) : ($k->description_hu ?: $k->description_ro));
            $bekezdesek = array_values(array_filter(array_map('trim', preg_split('/\n+/', $szoveg) ?: []), fn ($s) => $s !== ''));

            return [
                'nev' => $k->name,
                'bekezdesek' => $bekezdesek,
                'kivonat' => self::kivonat(implode(' ', $bekezdesek)),
                'kiskep' => $k->image ? url($k->image->getGlideUrl(['w' => 560, 'h' => 420, 'fit' => 'crop', 'fm' => 'webp', 'q' => 74])) : null,
                'nagykep' => $k->image ? url($k->image->getGlideUrl(['w' => 900, 'h' => 506, 'fit' => 'crop', 'fm' => 'webp', 'q' => 78])) : null,
            ];
        })->all();
    }

    /** A 2026-os `cut()` átirata: 170 karakterig szóhatáron (legalább 40), a végéről az írásjel le, „…”. */
    public static function kivonat(string $s, int $max = 170): string
    {
        $t = trim((string) preg_replace('/\s+/u', ' ', $s));
        if (mb_strlen($t) <= $max) {
            return $t;
        }
        $c = mb_substr($t, 0, $max);
        $szokoz = mb_strrpos($c, ' ');

        return rtrim(mb_substr($c, 0, max($szokoz === false ? 0 : $szokoz, 40)), ',;:.-–—') . '…';
    }
}
