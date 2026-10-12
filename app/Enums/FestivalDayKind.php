<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FestivalDayKind: string implements HasLabel
{
    case Festival = 'festival';
    case Prep = 'prep';

    public function label(): string
    {
        return match ($this) {
            self::Festival => 'Fesztiválnap',
            self::Prep => 'Előkészítő nap',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
