<?php

namespace App\Enums;

/** Az önkéntes-jelentkezés állapota (a 2026-os `volunteer_application.status`, ugyanazokkal a feliratokkal). */
enum VolunteerStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Új',
            self::Contacted => 'Felkeresve',
            self::Accepted => 'Felvéve',
            self::Rejected => 'Elutasítva',
            self::Withdrawn => 'Visszalépett',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Contacted => 'info',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Withdrawn => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
