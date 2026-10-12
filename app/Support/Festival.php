<?php

namespace App\Support;

use App\Enums\FestivalDayKind;
use App\Models\FestivalDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * A programnapok olvasója. Az egyetlen forrás a festival_days tábla (az adminban szerkeszthető);
 * minden olvasó ezen az osztályon át éri el. A fesztivál kezdete/vége a „Fesztiválnap” típusú
 * napokból számolódik — nincs külön tárolva, ami elcsúszhatna.
 */
class Festival
{
    /** @return Collection<int, FestivalDay> dátum szerint rendezve */
    public static function days(): Collection
    {
        return FestivalDay::orderBy('date')->get();
    }

    /** @return list<string> a programnapok azonosítói (Y-m-d) */
    public static function dayIds(): array
    {
        return self::days()->map->id()->all();
    }

    public static function isProgramDay(string $date): bool
    {
        return FestivalDay::whereDate('date', $date)->exists();
    }

    public static function from(): CarbonImmutable
    {
        return self::festivalDays()->first()->date;
    }

    public static function to(): CarbonImmutable
    {
        return self::festivalDays()->last()->date;
    }

    /** A „Mi van ma?” tartalék célja: a nyitónap, nem a lista első (előkészítő) napja. */
    public static function openingDay(): string
    {
        return self::from()->format('Y-m-d');
    }

    public static function year(): int
    {
        return self::from()->year;
    }

    /** Hajnali óra, ameddig a fesztiválnap tart (a 00:00–03:00 közötti befejezés az előző naphoz tartozik). */
    public static function dayCutHour(): int
    {
        return (int) config('forgatag.day_cut_hour');
    }

    /** @return Collection<int, FestivalDay> */
    private static function festivalDays(): Collection
    {
        $napok = FestivalDay::where('kind', FestivalDayKind::Festival)->orderBy('date')->get();
        if ($napok->isEmpty()) {
            throw new RuntimeException('Nincs egyetlen fesztiválnap sem megadva (Admin → Fesztiválnapok).');
        }

        return $napok;
    }
}
