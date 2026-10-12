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

    /**
     * A tárhely PHP-feltöltési korlátja elég-e a 10 MB-os fotóhoz. Ha nem, a nagy fotó a szerverig
     * sem jut el — ezt a Beállítások lapon kiírjuk, hogy ne élesben derüljön ki.
     */
    public static function uploadLimitWarning(): ?string
    {
        $bajt = fn (string $v) => (int) $v * match (strtoupper(substr(trim($v), -1))) {
            'G' => 1024 ** 3, 'M' => 1024 ** 2, 'K' => 1024, default => 1,
        };
        $file = $bajt((string) ini_get('upload_max_filesize'));
        $post = $bajt((string) ini_get('post_max_size'));
        $kell = SubmissionForm::PHOTO_MAX_BYTES + 1024 * 1024; // a fotó + az űrlap többi része

        if ($file >= SubmissionForm::PHOTO_MAX_BYTES && ($post === 0 || $post >= $kell)) {
            return null;
        }

        return 'FIGYELEM: a tárhely PHP-ja most legfeljebb ' . ini_get('upload_max_filesize') . ' fájlt / '
            . ini_get('post_max_size') . ' kérést fogad, a fotó pedig ' . SubmissionForm::photoMaxMb() . ' MB is lehet. '
            . 'A cPanel „Select PHP Version” → Options lapján: upload_max_filesize legalább 12M, post_max_size legalább 16M.';
    }

    public static function closedMessage(): string
    {
        return (string) Settings::get('submission.closed_message');
    }
}
