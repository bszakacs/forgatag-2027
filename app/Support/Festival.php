<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * A fesztiválnapok EGYETLEN forrása: seed/festival-days.json. Nincs adatbázis-másolata
 * (egy forrás szabály) — minden olvasó ezen az osztályon át éri el.
 * Hiányzó vagy hibás fájlnál hibával áll meg (néma hiba tilos).
 */
class Festival
{
    private static ?array $data = null;

    private static function data(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $path = base_path('seed/festival-days.json');
        if (! is_readable($path)) {
            throw new RuntimeException("Hiányzik a fesztiválnapok forrása: {$path}");
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || empty($data['days']) || empty($data['festival']['from']) || empty($data['festival']['to'])) {
            throw new RuntimeException("Hibás a fesztiválnapok forrása (days / festival.from / festival.to hiányzik): {$path}");
        }

        return self::$data = $data;
    }

    public static function year(): int
    {
        return (int) self::data()['year'];
    }

    public static function from(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::data()['festival']['from']);
    }

    public static function to(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::data()['festival']['to']);
    }

    /** A „Mi van ma?” tartalék célja: a nyitónap, nem a lista első (esetleg előkészítő) napja. */
    public static function openingDay(): string
    {
        return self::data()['festival']['from'];
    }

    /** Hajnali óra, ameddig a fesztiválnap tart (a 00:00–03:00 közötti befejezés az előző naphoz tartozik). */
    public static function dayCutHour(): int
    {
        return (int) (self::data()['dayCutHour'] ?? 3);
    }

    public static function label(string $locale = 'hu'): string
    {
        return self::data()['festival']['label'][$locale] ?? self::data()['festival']['label']['hu'];
    }

    /**
     * @return list<array{id: string, weekday: array<string,string>, label: array<string,string>, prep: bool}>
     */
    public static function days(): array
    {
        $from = self::data()['festival']['from'];

        return array_map(
            fn (array $day) => $day + ['prep' => $day['id'] < $from],
            self::data()['days'],
        );
    }

    /** @return list<string> a napok azonosítói (Y-m-d) */
    public static function dayIds(): array
    {
        return array_column(self::data()['days'], 'id');
    }

    public static function isProgramDay(string $date): bool
    {
        return in_array($date, self::dayIds(), true);
    }
}
