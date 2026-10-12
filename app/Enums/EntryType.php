<?php

namespace App\Enums;

enum EntryType: string
{
    case Free = 'free';
    case Ticketed = 'ticketed';
    case Donation = 'donation';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Ingyenes',
            self::Ticketed => 'Jegyes',
            self::Donation => 'Adományalapú',
        };
    }
}
