<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A saját tartalom (hír, aloldal-blokk) szövege HTML-ként él (App\Filament\Forms\Szovegszerkeszto). A 2026-os
 * szövegek Markdownban voltak — ez alakítja át őket, EGY helyen (migráció + a 2026-os behozatal).
 *
 * Ugyanaz, amit a 2026-os oldal mutatott (forgatag-os frontend/src/lib/markdown.ts): bekezdések, címsor, félkövér,
 * dőlt, link, lista; a beírt HTML NEM fut le (escape), és nem biztonságos link (javascript:) nem marad.
 */
class TartalomHtml
{
    /** Már HTML-e (a szerkesztő kimenete mindig címkével kezdődik) — az átalakítás így ismételhető. */
    public static function html(?string $szoveg): bool
    {
        return $szoveg !== null && str_starts_with(ltrim($szoveg), '<');
    }

    public static function markdownbol(?string $szoveg): ?string
    {
        if ($szoveg === null || trim($szoveg) === '' || self::html($szoveg)) {
            return $szoveg === null || trim($szoveg) === '' ? null : $szoveg;
        }

        return trim(Str::markdown($szoveg, ['html_input' => 'escape', 'allow_unsafe_links' => false]));
    }
}
