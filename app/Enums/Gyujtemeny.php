<?php

namespace App\Enums;

/**
 * A médiatár RENDSZER-gyűjteményei (CLAUDE.md 2/d) — EGY helyen. A kód ezekre hivatkozik (a kép a
 * feltöltés HELYÉBŐL kapja a gyűjteményét); a felhasználó ezeken felül sajátot is létrehozhat.
 * A „Rendezetlen” NEM tárolt gyűjtemény: az a kép rendezetlen, amelyik egyetlen gyűjteményben sincs —
 * így az áthelyezés magától kiveszi onnan, és nem lehet „rendezetlen ÉS hír” egyszerre.
 */
enum Gyujtemeny: string
{
    case Hirek = 'hirek';
    case Programok = 'programok';
    /**
     * A 2026-os fesztiválra leadott programborítók (külsős fotók, „minden jog fenntartva”). Megőrizzük,
     * de 2027-es programhoz NEM rendelhetők (a jogot a tavalyi programra adták) — tulajdonosi döntés,
     * 2026-10-22. Őr: ProgramItem::saving.
     */
    case Programok2026 = 'programok-2026';
    case Galeriak = 'galeriak';
    case Tamogatok = 'tamogatok';
    case Arculat = 'arculat';
    case Csapat = 'csapat';
    case Kezmuvesek = 'kezmuvesek';
    case Lapok = 'lapok';

    public function label(): string
    {
        return match ($this) {
            self::Hirek => 'Hírek',
            self::Programok => 'Programok',
            self::Programok2026 => 'Programok 2026',
            self::Galeriak => 'Galériák',
            self::Tamogatok => 'Támogatók',
            self::Arculat => 'Arculat',
            self::Csapat => 'Csapat',
            self::Kezmuvesek => 'Kézművesek',
            self::Lapok => 'Aloldalak',
        };
    }

    public function sort(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }
}
