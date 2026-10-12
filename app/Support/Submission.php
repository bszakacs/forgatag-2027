<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * A programbeküldés állapota — EGY helyen (a Beállítások „Programbeküldés” blokkjából).
 * Nyitva csak akkor, ha a kapcsoló nyitva áll ÉS (nincs határidő VAGY még nem járt le).
 * Az űrlap ÉS a szerver-oldali ellenőrzés is ezt kérdezi.
 */
class Submission
{
    public static function isOpen(?CarbonImmutable $most = null): bool
    {
        if (! (bool) Settings::get('submission.open', false)) {
            return false;
        }

        $hatarido = Settings::get('submission.deadline');

        return blank($hatarido) || ($most ?? CarbonImmutable::now())->lt(CarbonImmutable::parse($hatarido));
    }

    public static function closedMessage(): string
    {
        return (string) Settings::get('submission.closed_message');
    }
}
