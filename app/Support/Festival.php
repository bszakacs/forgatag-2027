<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * A fesztivál adatai és a programnapok. A napokat a rendszer SZÁMOLJA a Beállítások
 * „Fesztivál alapadatai” blokkjából: az előkészítő napok a kezdő dátum előtti N nap,
 * utána a kezdő és záró dátum közötti fesztiválnapok. Nincs tárolt napok-lista.
 * A kiszámolt napok határolják be, mely napokra lehet programot beküldeni.
 */
class Festival
{
    public static function start(): CarbonImmutable
    {
        return CarbonImmutable::parse(Settings::get('festival.start_date'));
    }

    public static function end(): CarbonImmutable
    {
        return CarbonImmutable::parse(Settings::get('festival.end_date'));
    }

    public static function prepDays(): int
    {
        return (int) Settings::get('festival.prep_days', 0);
    }

    /** A „Mi van ma?” tartalék célja és a nyitóesemény napja. */
    public static function openingDay(): string
    {
        return Settings::get('festival.opening_day') ?: self::start()->format('Y-m-d');
    }

    public static function year(): int
    {
        return self::start()->year;
    }

    public static function name(string $locale = 'hu'): string
    {
        return (string) (Settings::get("festival.name_{$locale}") ?: Settings::get('festival.name_hu'));
    }

    /** Hajnali óra, ameddig a fesztiválnap tart (pl. 3: a 00:00–03:00 közötti befejezés az előző naphoz tartozik). */
    public static function dayCutHour(): int
    {
        return (int) Settings::get('festival.day_cut_hour', 3);
    }

    /**
     * Időpont a FESZTIVÁLNAP skáláján, percben (a 2026-os szabály): a záró óra (`day_cut_hour`) előtti idő a nap
     * VÉGÉRE kerül — 02:00 → 26:00 = 1560. Így egy 23:00–00:30 program a KEZDÉS napján marad, a nap végére
     * rendeződik, és nem lesz belőle „délelőtti” program. A tárolt adat nem változik (a beküldő a napot pipálja).
     */
    public static function scaledMinutes(?string $time): ?int
    {
        if (blank($time) || ! preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return null;
        }
        $perc = (int) $m[1] * 60 + (int) $m[2];

        return $perc < self::dayCutHour() * 60 ? $perc + 1440 : $perc;
    }

    /** A vég a skálán: a kezdés UTÁNI első ilyen időpont (00:30 egy 23:00-as kezdés után → 24:30). */
    public static function scaledEnd(int $kezdes, ?string $time): ?int
    {
        $veg = self::scaledMinutes($time);
        if ($veg === null) {
            return null;
        }
        while ($veg <= $kezdes) {
            $veg += 1440;
        }

        return $veg;
    }

    /** Skálázott perc → „23.00” / „0.30” (a 2026-os kiírás: pont, a 24 fölötti óra visszaosztva). */
    public static function clock(int $perc): string
    {
        return intdiv($perc % 1440, 60) . '.' . str_pad((string) ($perc % 60), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Egy pillanat a fesztiválnap skáláján: melyik nap ÉS hányadik perce. Hajnali 2-kor még az előző nap
     * tart (a perc 1440 fölött) — a „Most zajlik” és a „Mi van ma?” ezt kérdezi.
     *
     * @return array{nap: string, perc: int}
     */
    public static function festivalMoment(?\DateTimeInterface $mikor = null): array
    {
        $t = CarbonImmutable::instance($mikor ?? now())->setTimezone(config('app.timezone'));
        $perc = $t->hour * 60 + $t->minute;
        if ($t->hour < self::dayCutHour()) {
            return ['nap' => $t->subDay()->format('Y-m-d'), 'perc' => $perc + 1440];
        }

        return ['nap' => $t->format('Y-m-d'), 'perc' => $perc];
    }

    /**
     * @return list<array{id: string, date: CarbonImmutable, prep: bool}> időrendben
     */
    public static function days(): array
    {
        return self::computeDays(
            Settings::get('festival.start_date'),
            Settings::get('festival.end_date'),
            self::prepDays(),
        );
    }

    /**
     * A napok kiszámítása — a Beállítások lap élő előnézete is ezt hívja (még mentetlen értékekkel).
     *
     * @return list<array{id: string, date: CarbonImmutable, prep: bool}>
     */
    public static function computeDays(?string $start, ?string $end, int $prepDays): array
    {
        if (blank($start) || blank($end)) {
            return [];
        }
        $kezdo = CarbonImmutable::parse($start);
        $zaro = CarbonImmutable::parse($end);
        if ($zaro->lt($kezdo)) {
            return [];
        }

        $napok = [];
        for ($d = $kezdo->subDays(max(0, $prepDays)); $d->lte($zaro); $d = $d->addDay()) {
            $napok[] = ['id' => $d->format('Y-m-d'), 'date' => $d, 'prep' => $d->lt($kezdo)];
        }

        return $napok;
    }

    /** @return list<string> a programnapok azonosítói (Y-m-d) */
    public static function dayIds(): array
    {
        return array_column(self::days(), 'id');
    }

    public static function isProgramDay(string $date): bool
    {
        return in_array($date, self::dayIds(), true);
    }

    /** Felirat a 2026-os formátumban: hu „aug. 23. (h)”, ro „23 aug. (lu)”. */
    public static function dayLabel(CarbonImmutable $date, string $locale = 'hu'): string
    {
        $d = $date->locale($locale);
        $rovid = self::ROVID_NAP[$locale][$d->dayOfWeekIso] ?? '';

        return $locale === 'ro'
            ? $d->day . ' ' . $d->isoFormat('MMM') . ' (' . $rovid . ')'
            : $d->isoFormat('MMM') . ' ' . $d->day . '. (' . $rovid . ')';
    }

    /** A 2026-os rövid napnév (napválasztó felső sora): hu „sze”, ro „mi”. */
    public static function shortWeekday(CarbonImmutable $date, string $locale = 'hu'): string
    {
        return self::ROVID_NAP[$locale][$date->dayOfWeekIso] ?? '';
    }

    /** Csoportfejléc és lapcím: hu „Augusztus 27., péntek”, ro „27 august, vineri” (a 2026-os alak). */
    public static function fullDayLabel(CarbonImmutable $date, string $locale = 'hu'): string
    {
        $d = $date->locale($locale);

        return $locale === 'ro'
            ? $d->day . ' ' . $d->isoFormat('MMMM') . ', ' . $d->isoFormat('dddd')
            : Str::ucfirst($d->isoFormat('MMMM')) . ' ' . $d->day . '., ' . $d->isoFormat('dddd');
    }

    /** A fesztivál időtartama emberi formában: hu „2027. augusztus 25–29.” (a 2026-os seed-felirat alakja), ro „25–29 august 2027”. */
    public static function spanLabel(string $locale = 'hu'): string
    {
        $k = self::start()->locale($locale);
        $z = self::end()->locale($locale);
        if ($locale === 'ro') {
            $eleje = $k->day . ($k->month !== $z->month || $k->year !== $z->year ? ' ' . $k->isoFormat('MMMM') : '')
                . ($k->year !== $z->year ? ' ' . $k->year : '');

            return ($k->isSameDay($z) ? '' : $eleje . '–') . $z->day . ' ' . $z->isoFormat('MMMM') . ' ' . $z->year;
        }
        if ($k->year !== $z->year) {
            return $k->year . '. ' . $k->isoFormat('MMMM') . ' ' . $k->day . '. – ' . $z->year . '. ' . $z->isoFormat('MMMM') . ' ' . $z->day . '.';
        }
        if ($k->month !== $z->month) {
            return $k->year . '. ' . $k->isoFormat('MMMM') . ' ' . $k->day . '. – ' . $z->isoFormat('MMMM') . ' ' . $z->day . '.';
        }

        return $k->year . '. ' . $k->isoFormat('MMMM') . ' ' . $k->day . ($k->day === $z->day ? '.' : '–' . $z->day . '.');
    }

    public static function weekday(CarbonImmutable $date, string $locale = 'hu'): string
    {
        return $date->locale($locale)->isoFormat('dddd');
    }

    /** A 2026-ban használt rövid napnevek (a Carbon rövidítései ettől eltérnek). */
    private const ROVID_NAP = [
        'hu' => [1 => 'h', 2 => 'k', 3 => 'sze', 4 => 'cs', 5 => 'p', 6 => 'szo', 7 => 'v'],
        'ro' => [1 => 'lu', 2 => 'ma', 3 => 'mi', 4 => 'jo', 5 => 'vi', 6 => 'sâ', 7 => 'du'],
    ];
}
