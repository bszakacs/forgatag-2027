<?php

namespace App\Enums;

/** A program gépi fordításának hatóköre: csak a beküldött, külsős szövegek (CLAUDE.md 5.). */
enum TranslatableField: string
{
    case Title = 'title';
    case Description = 'description';
    case TicketInfo = 'ticket_info';

    public function label(): string
    {
        return match ($this) {
            self::Title => 'Cím',
            self::Description => 'Leírás',
            self::TicketInfo => 'Jegyinformáció',
        };
    }
}
