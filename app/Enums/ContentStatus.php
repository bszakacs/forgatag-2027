<?php

namespace App\Enums;

/** Saját tartalom (hír, aloldal) állapota. Nincs jóváhagyási lánc: a szerkesztő maga publikál. */
enum ContentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Piszkozat',
            self::Published => 'Publikált',
        };
    }

    public function color(): string
    {
        return $this === self::Published ? 'success' : 'gray';
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
