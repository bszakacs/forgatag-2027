<?php

namespace App\Enums;

/** Mire mutat egy menüpont. */
enum MenuItemType: string
{
    /** Egy aloldalra — a lap slugjának változása nem töri el. */
    case Page = 'page';
    /** Tetszőleges cím (belső útvonal vagy külső oldal). */
    case Link = 'link';
    /** Nem kattintható: csak az almenü fejléce (pl. „Programok”). */
    case Group = 'group';

    public function label(): string
    {
        return match ($this) {
            self::Page => 'Aloldal',
            self::Link => 'Hivatkozás (cím)',
            self::Group => 'Csak almenü-fejléc (nem kattintható)',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
