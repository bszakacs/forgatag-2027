<?php

namespace App\Enums;

enum MapZone: string
{
    case None = 'none';
    case Castle = 'castle';
    case City = 'city';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Nincs a térképen',
            self::Castle => 'Vár',
            self::City => 'Város',
        };
    }
}
