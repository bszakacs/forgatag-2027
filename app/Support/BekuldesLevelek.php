<?php

namespace App\Support;

use App\Enums\NotificationTopic;
use App\Mail\SzovegesLevel;
use App\Models\ProgramItem;
use App\Models\ProgramManager;

/**
 * A programbeküldés levelei, egy helyen. A szövegek a 2026-os Directus-flow-k levelei
 * („Új beküldés értesítő”, „Jóváhagyás értesítő”); a fesztivál neve és éve a Beállításokból jön.
 *
 * - beküldéskor (és javításkor): a koordinátor kap levelet,
 * - a beküldő a JÓVÁHAGYÁSKOR kap levelet, nem beküldéskor (ahogy 2026-ban),
 * - ha a fotó nem ment fel: a koordinátor jelzést kap (a beküldő a felületen látja).
 */
class BekuldesLevelek
{
    public static function ujBekuldes(ProgramItem $program, bool $javitas = false): bool
    {
        $nev = Festival::name();
        $targy = $javitas
            ? "Beküldött program módosult – {$nev}"
            : "Új programbeküldés érkezett – {$nev}";
        $bevezeto = $javitas
            ? 'A beküldő módosította a programját a beküldő űrlapon:'
            : 'Új program érkezett a beküldő űrlapról:';

        $szoveg = $bevezeto . "\n\n"
            . $program->title . "\n"
            . 'Kategória: ' . ($program->category?->name_hu ?? '—') . "\n"
            . 'Beküldő: ' . $program->contact_name . ' <' . $program->contact_email . ">\n\n"
            . 'Kérlek, nézd át és hagyd jóvá az adminban.';

        // A koordinátor a „Válasz” gombbal egyből a beküldőnek ír.
        $level = new SzovegesLevel($targy, $szoveg);
        if (filled($program->contact_email)) {
            $level->replyTo($program->contact_email, $program->contact_name);
        }

        return Notifier::topic(NotificationTopic::ProgramSubmission, $level);
    }

    public static function jovahagyva(ProgramItem $program): bool
    {
        if (blank($program->contact_email)) {
            return false;
        }
        $nev = Festival::name();
        $szoveg = 'Kedves ' . $program->contact_name . "!\n\n"
            . 'A(z) „' . $program->title . '” című programodat a szervezők jóváhagyták, és hamarosan megjelenik a fesztivál programoldalán.' . "\n\n"
            . "Köszönjük a beküldést!\n\n"
            . "— {$nev} csapata";

        // A feladó noreply@ — ha a beküldő válaszol, a Beállításokban megadott kapcsolattartó címre menjen.
        // Ha az üres, nincs Reply-To (a válasz a noreply@ címre menne).
        $level = new SzovegesLevel('A programodat jóváhagytuk – ' . $nev . ' ' . Festival::year(), $szoveg);
        $valaszcim = Settings::get('submission.contact_email');
        if (filled($valaszcim)) {
            $level->replyTo($valaszcim, $nev);
        }

        return Notifier::send($program->contact_email, $level);
    }

    /** A fotó nem ment fel — a program NEM jött létre / NEM módosult; a koordinátor tudjon róla. */
    public static function fotoHiba(ProgramManager $beku, string $kategoria, string $cim, string $ok): bool
    {
        $szoveg = 'Egy beküldőnél nem sikerült a programfotó feltöltése, ezért a beküldése NEM ment át.' . "\n"
            . 'A beküldő a felületen hibaüzenetet látott; lehet, hogy segítség kell neki.' . "\n\n"
            . 'Beküldő: ' . $beku->name . ' <' . $beku->email . ">\n"
            . 'Kategória: ' . $kategoria . "\n"
            . 'Program címe: ' . ($cim !== '' ? $cim : '—') . "\n"
            . 'A hiba oka: ' . $ok;

        $level = new SzovegesLevel('Programfotó feltöltése nem sikerült – ' . Festival::name(), $szoveg);
        $level->replyTo($beku->email, $beku->name);

        return Notifier::topic(NotificationTopic::ProgramSubmission, $level);
    }
}
