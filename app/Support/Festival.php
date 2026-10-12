<?php

namespace App\Support;

use Carbon\CarbonImmutable;

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
