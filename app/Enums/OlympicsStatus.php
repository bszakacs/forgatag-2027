<?php

namespace App\Enums;

/** A Családi Olimpia csapatának állapota (a 2026-os `olympics_team.status`, ugyanazokkal a feliratokkal). */
enum OlympicsStatus: string
{
    case Applied = 'applied';
    case Waitlist = 'waitlist';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Applied => 'Jelentkezett',
            self::Waitlist => 'Várólista',
            self::Withdrawn => 'Visszalépett',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Applied => 'success',
            self::Waitlist => 'warning',
            self::Withdrawn => 'gray',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
