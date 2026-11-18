<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use RuntimeException;

/**
 * A belépés nélküli, publikus jelentkező űrlapok (Családi Olimpia, önkéntesek — PROMPT-20) közös darabjai, EGY helyen:
 *  · a méz-bödön (rejtett mező, amit csak robot tölt ki),
 *  · a lap szerkeszthető szövegei (a bevezető, a tudnivalók — App\Support\LapSablon `olimpia` / `onkentes`; Tartalom → Aloldalak),
 *  · a bevezető szövegek megjelenítése: a 2026-os szövegben kiemelés van (<strong>, <em>), ezért szűrt HTML (HtmlSzuro),
 *    és a `{…}` helyére a Beállításokból számolt érték kerül (pl. a csapatlétszám, az időszak) — hogy egy tény ne két helyen álljon.
 * A gyakorisági korlát az útvonalon: `throttle:jelentkezes` (AppServiceProvider).
 */
class PublikusUrlap
{
    /** A méz-bödön mező neve (2026: `website`). */
    public const MEZ_BODON = 'website';

    public static function mezBodon(Request $request): bool
    {
        return trim((string) $request->input(self::MEZ_BODON, '')) !== '';
    }

    /** A lap szövegei (magyarul — a két űrlap 2026-ban is csak magyar volt). Hiányzó lap = HIBA (a telepítés hozza létre). */
    public static function szovegek(string $slug): LapSzovegek
    {
        $lap = Page::query()->where('slug', $slug)->first()
            ?? throw new RuntimeException("Hiányzik a(z) „{$slug}” rendszerlap (php artisan forgatag:lap-szovegek hozza létre).");

        return new LapSzovegek($lap, 'hu');
    }

    /** @param array<string, string|int|null> $helyettesit */
    public static function html(string $szoveg, array $helyettesit = []): HtmlString
    {
        $csere = [];
        foreach ($helyettesit as $k => $v) {
            $csere['{' . $k . '}'] = e((string) $v);
        }

        return new HtmlString(HtmlSzuro::tisztit(strtr($szoveg, $csere)));
    }
}
