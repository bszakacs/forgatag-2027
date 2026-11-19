<?php

namespace App\Support;

use App\Enums\TranslatableField;
use App\Models\ProgramItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * NAPTÁRBA (.ics) — EGY helyen (PROMPT-21). A 2026-os lib/ics.ts + lib/calendar-menu.ts átirata, de a fájl a SZERVEREN készül:
 * az iOS a blob:/data: címet nem adja át a Naptárnak, egy valódi https-címet `text/calendar` fejléccel igen (2026-ban ezért
 * gyártott a build minden programhoz külön fájlt). A webes naptárak (Google, Outlook…) hivatkozásaihoz a gomb ugyanezeket
 * az időpontokat kapja (`gombAdat`), így a böngészőben nincs időzóna-számolás.
 *
 * KÉT SZABÁLY, AMIT KÖNNYŰ ELRONTANI (2026 ugyanígy):
 *  1) A FESZTIVÁLNAP HAJNALI 3-IG TART (Beállítások: `day_cut_hour`): a „péntek 23:00–00:30” program SZOMBAT 00:30-kor ér véget,
 *     a „péntek 02:00” program SZOMBAT hajnalban kezdődik. A naptárba a VALÓDI pillanat kerül — ezt a ProgramSor skálája
 *     (kezd/veg percben, ≥ 1440 = másnap) adja, itt csak visszaváltjuk falióra-időre.
 *  2) IDŐZÓNA: a fájlba UTC-időpont kerül (…Z), a fesztivál időzónájából (config app.timezone, Europe/Bucharest) számolva —
 *     nem beégetett eltolással, mert az a nyári időszámítás végén hibás.
 * Vég nélküli időpont: 1 óra, és a leírás végén jelezzük (2026: ics_no_end).
 */
final class Naptar
{
    /** Egy időpont valódi kezdete és vége (UTC). @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public static function idotartam(ProgramSor $sor): array
    {
        $kezd = self::pillanat($sor->nap(), $sor->kezd);
        $veg = $sor->veg !== null ? self::pillanat($sor->nap(), $sor->veg) : $kezd->addHour();

        return [$kezd->utc(), $veg->utc()];
    }

    /** Fesztiválnap + skálázott perc → helyi falióra-idő (a napot a 24 óra fölötti rész lépteti, az órát nem). */
    private static function pillanat(string $nap, int $perc): CarbonImmutable
    {
        return CarbonImmutable::parse($nap, config('app.timezone'))
            ->addDays(intdiv($perc, 1440))
            ->setTime(intdiv($perc % 1440, 60), $perc % 60);
    }

    /** UTC-időbélyeg: „20270827T200000Z”. */
    public static function belyeg(CarbonImmutable $t): string
    {
        return $t->utc()->format('Ymd\THis\Z');
    }

    /**
     * Egy esemény adatai (a fájlhoz és a webes naptárakhoz ugyanaz).
     *
     * @return array{uid: string, cim: string, s: string, e: string, hely: string, leiras: string, url: string}
     */
    public static function esemeny(ProgramSor $sor): array
    {
        $p = $sor->program();
        [$kezd, $veg] = self::idotartam($sor);
        $v = $p->venue;
        $hely = $v ? str_replace(', ', ' › ', Nyelv::valaszt($v->name, $v->name_ro)) : '';
        if ($v && filled($v->address)) {
            $hely .= ' · ' . $v->address;
        }
        $leiras = trim((string) preg_replace('/\s+/u', ' ', ProgramSzoveg::mezo($p, TranslatableField::Description)['szoveg']));
        $leiras = mb_substr($leiras, 0, 900);
        if ($sor->veg === null) {
            $leiras = ltrim($leiras . "\n\n" . __('publikus.ics_nincs_veg'));
        }
        $url = url((new ProgramSzures($sor->nap(), program: $p->slug))->url() . '&idopont=' . $sor->idopont->id);

        return [
            'uid' => 'forgatag-' . $p->id . '-' . $sor->idopont->id . '@forgatag.ro',
            'cim' => ProgramSzoveg::cim($p),
            's' => self::belyeg($kezd),
            'e' => self::belyeg($veg),
            'hely' => $hely,
            'leiras' => ltrim($leiras . "\n\n" . $url),
            'url' => $url,
        ];
    }

    /** Az .ics fájl szövege (RFC 5545: CRLF, sor-hajtogatás 75 oktettnél, escape-elt szöveg). @param  iterable<ProgramSor>  $sorok */
    public static function ics(iterable $sorok): string
    {
        $most = now()->utc()->format('Ymd\THis\Z');
        $sorokKi = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Vasarhelyi Forgatag//programok//HU', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH'];
        foreach ($sorok as $sor) {
            $e = self::esemeny($sor);
            $sorokKi[] = 'BEGIN:VEVENT';
            $sorokKi[] = 'UID:' . self::esc($e['uid']);
            $sorokKi[] = 'DTSTAMP:' . $most;
            $sorokKi[] = 'DTSTART:' . $e['s'];
            $sorokKi[] = 'DTEND:' . $e['e'];
            $sorokKi[] = 'SUMMARY:' . self::esc($e['cim']);
            if ($e['hely'] !== '') {
                $sorokKi[] = 'LOCATION:' . self::esc($e['hely']);
            }
            $sorokKi[] = 'DESCRIPTION:' . self::esc($e['leiras']);
            $sorokKi[] = 'URL:' . $e['url'];
            $sorokKi[] = 'END:VEVENT';
        }
        $sorokKi[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::hajtogat(...), $sorokKi)) . "\r\n";
    }

    /** A letöltés válasza: `inline` — az iOS így a Naptárnak adja, asztalon és Androidon letöltődik és a naptár nyitja meg. */
    public static function valasz(string $ics, string $fajlnev): \Illuminate\Http\Response
    {
        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="' . $fajlnev . '"',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /**
     * A „Naptárba” gomb adata (data-naptar, JSON): a valódi .ics cím, és a webes naptárakhoz az ELSŐ időpont (egy
     * webes hivatkozásba egy esemény fér — ha több van, a lista kiírja, melyik került bele; 2026: cal_one_note).
     *
     * @param  Collection<int, ProgramSor>  $sorok
     */
    public static function gombAdat(ProgramItem $p, Collection $sorok, string $icsUrl): string
    {
        $elso = $sorok->first();
        $adat = [
            'ics' => $icsUrl,
            'ev' => $elso ? self::esemeny($elso) : null,
            'tobb' => $sorok->count() > 1 && $elso
                ? __('publikus.cal_egy') . ' ' . Festival::shortWeekday(CarbonImmutable::parse($elso->nap()), Nyelv::aktualis())
                    . ' ' . Festival::clock($elso->kezd) : '',
        ];

        return json_encode($adat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    private static function esc(string $s): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $s);
    }

    /** 75 oktettenként hajtogat, UTF-8 karaktert nem vág ketté. */
    private static function hajtogat(string $sor): string
    {
        if (strlen($sor) <= 75) {
            return $sor;
        }
        $ki = [];
        $akt = '';
        $hatar = 75;
        foreach (mb_str_split($sor) as $k) {
            if (strlen($akt) + strlen($k) > $hatar) {
                $ki[] = $akt;
                $akt = '';
                $hatar = 74;
            }
            $akt .= $k;
        }
        $ki[] = $akt;

        return implode("\r\n ", $ki);
    }
}
