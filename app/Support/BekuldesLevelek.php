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
 * - ha a fotó nem ment fel: a koordinátor jelzést kap (a beküldő a felületen látja),
 * - új programgazda: üdvözlő levél a belépési adatokkal (2026: „Programfelelős — üdvözlő e-mail”),
 * - elfelejtett jelszó: levél a beállító linkkel.
 */
class BekuldesLevelek
{
    /**
     * Üdvözlő levél az új programgazdának: belépési cím + jelszó + a kategóriái linkként.
     * A link a kategória űrlapjára mutat — belépés után egyből az nyílik.
     */
    public static function udvozlo(ProgramManager $beku, #[\SensitiveParameter] string $jelszo): bool
    {
        $nev = Festival::name();
        $kategoriak = $beku->categories()->orderBy('sort')->orderBy('name_hu')->get();

        $sorok = [
            'Kedves ' . $beku->name . '!',
            '',
            "Létrehoztuk a hozzáférésedet a(z) {$nev} programbeküldő felületéhez. Belépési adatok:",
            '',
            'E-mail (belépési azonosító): ' . $beku->email,
            'Jelszó: ' . $jelszo,
            '',
        ];
        if ($kategoriak->isNotEmpty()) {
            $sorok[] = $kategoriak->count() === 1 ? 'A kategóriád, ahová beküldhetsz:' : 'A kategóriáid, ahová beküldhetsz:';
            $sorok[] = '';
            foreach ($kategoriak as $k) {
                $sorok[] = '- ' . $k->name_hu . ': ' . route('bekuldes.urlap', $k);
            }
            $sorok[] = '';
            $sorok[] = 'Kattints a kategória linkjére; a fenti adatokkal jelentkezz be, és rögtön az adott kategória űrlapja nyílik.';
        } else {
            $sorok[] = 'A beküldő felület: ' . route('bekuldes.belepes');
            $sorok[] = '';
            $sorok[] = 'A fenti adatokkal tudsz belépni.';
        }
        $sorok[] = '';
        $sorok[] = 'Ha elfelejted a jelszavad, a belépőlapon az „Elfelejtett jelszó” linkkel újat állíthatsz be.';
        $sorok[] = '';
        $sorok[] = "— {$nev} csapata";

        $level = new SzovegesLevel("{$nev} – programgazda hozzáférés", implode("\n", $sorok));
        foreach (Notifier::cimek(NotificationTopic::ProgramSubmission) as $cim) {
            $level->replyTo($cim, $nev); // ha kérdése van, a koordinátornak írjon
        }

        return Notifier::send($beku->email, $level);
    }

    /** Elfelejtett jelszó: a beállító link (60 percig él). */
    public static function jelszoLink(ProgramManager $beku, #[\SensitiveParameter] string $token): bool
    {
        $nev = Festival::name();
        $link = route('bekuldes.jelszo.uj', ['token' => $token, 'email' => $beku->email]);
        $szoveg = 'Kedves ' . $beku->name . "!\n\n"
            . "Új jelszót kértél a(z) {$nev} programbeküldő felületéhez. Az alábbi linken állíthatod be:\n\n"
            . $link . "\n\n"
            . "A link 60 percig érvényes, és csak egyszer használható.\n"
            . "Ha nem te kérted, nincs teendőd: a régi jelszavad marad érvényben.\n\n"
            . "— {$nev} csapata";

        return Notifier::send($beku->email, new SzovegesLevel("Új jelszó beállítása – {$nev}", $szoveg));
    }

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

        // A feladó noreply@ — ha a beküldő válaszol, a válasz a programbeküldés koordinátorához menjen
        // (ugyanaz a cím, amely a beküldésekről értesítést kap: egy funkció = egy cím).
        $level = new SzovegesLevel('A programodat jóváhagytuk – ' . $nev . ' ' . Festival::year(), $szoveg);
        foreach (Notifier::cimek(NotificationTopic::ProgramSubmission) as $cim) {
            $level->replyTo($cim, $nev);
        }

        return Notifier::send($program->contact_email, $level);
    }

    /** Visszaküldés javításra: az indok + a javítás linkje. A válasz a koordinátorhoz megy. */
    public static function visszakuldve(ProgramItem $program, string $indok): bool
    {
        if (blank($program->contact_email)) {
            return false;
        }
        $nev = Festival::name();
        $szoveg = 'Kedves ' . $program->contact_name . "!\n\n"
            . 'A(z) „' . $program->title . '” című programodat a szervezők átnézték, és javítást kérnek, mielőtt jóváhagynák:' . "\n\n"
            . $indok . "\n\n";
        $szoveg .= Submission::isOpen()
            ? "A programot itt javíthatod (a beküldő felület adataival lépj be):\n" . route('bekuldes.szerkesztes', $program) . "\n\n"
                . "A javítás után a szervezők újra átnézik.\n\n"
            : "A programbeküldés jelenleg zárva van, ezért a felületen most nem tudod javítani. Kérlek, válaszolj erre a levélre.\n\n";
        $szoveg .= "— {$nev} csapata";

        $level = new SzovegesLevel('Javítást kérünk a programodon – ' . $nev, $szoveg);
        foreach (Notifier::cimek(NotificationTopic::ProgramSubmission) as $cim) {
            $level->replyTo($cim, $nev);
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
