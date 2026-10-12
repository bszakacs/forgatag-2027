<?php

namespace App\Enums;

/** A program gépi fordításának hatóköre: csak a beküldött, külsős szövegek. */
enum TranslatableField: string
{
    case Title = 'title';
    case Description = 'description';
    case TicketInfo = 'ticket_info';
}
