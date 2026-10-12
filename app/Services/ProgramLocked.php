<?php

namespace App\Services;

use RuntimeException;

/** A program időközben jóváhagyásra került — a beküldő már nem módosíthatja. */
class ProgramLocked extends RuntimeException
{
    public const UZENET = 'Ezt a programot a szervezők már jóváhagyták, ezért nem módosítható. Ha változtatni kell rajta, keresd a szervezőt.';

    public function __construct()
    {
        parent::__construct(self::UZENET);
    }
}
