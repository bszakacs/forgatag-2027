<?php

namespace App\Enums;

enum ProgramSource: string
{
    case Internal = 'internal';
    case PublicForm = 'public_form';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Belső felvitel',
            self::PublicForm => 'Beküldő űrlap',
        };
    }
}
