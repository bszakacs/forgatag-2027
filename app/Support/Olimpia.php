<?php

namespace App\Support;

use App\Enums\OlympicsStatus;
use App\Models\OlympicsTeam;
use Carbon\CarbonImmutable;

/**
 * A Családi Olimpia beállításai és állapota — EGY helyen (Beállítások → „Családi Olimpia” blokk, `olympics`).
 * Az űrlap, a beküldő végpont, az admin és a levelek is innen kérdeznek.
 *
 * A színek ADATBÓL jönnek (2026: `olympics_color` gyűjtemény; most a Beállítások listája). Egy színt egy évben csak EGY csapat
 * vihet el — ezt az ADATBÁZIS egyedi kulcsa őrzi (`olympics_teams` (year, color_key)), nem ez az osztály.
 */
class Olimpia
{
    /**
     * A jelentkezéskor elfogadott nyilatkozat — SZÓ SZERINT a 2025/2026-os űrlap szövege (2026: olympics-api DECLARATION_TEXT).
     * A rekordba is lementjük (a hozzájárulás bizonyítéka) — ezért NEM szerkeszthető és nem fogalmazható át.
     */
    public const NYILATKOZAT = 'Felelősségvállalási nyilatkozat: A csapatunk összes tagja megfelelő egészségi állapotban van a '
        . 'versenyen való részvételhez. Elfogadjuk, hogy a szervezők minden tőlük telhetőt megtesznek a '
        . 'biztonságunk érdekében, de a vetélkedő jellegéből adódó kockázatokat mi magunk viseljük. '
        . 'Fotó- és videóhozzájárulás: Beleegyezünk, hogy a verseny során rólunk készült fényképek és '
        . 'videófelvételek a fesztivál promóciós anyagaiban felhasználásra kerülhetnek.';

    /** 7 csapattag: 1–5 kötelező, 6–7 opcionális (2026). */
    public const TAGOK = 7;

    public const KOTELEZO_TAGOK = 5;

    public static function nyitva(): bool
    {
        return (bool) Settings::get('olympics.open', false);
    }

    public static function zartUzenet(): string
    {
        return (string) Settings::get('olympics.closed_message');
    }

    public static function maxCsapat(): int
    {
        return max(1, (int) Settings::get('olympics.max_teams', 6));
    }

    public static function ev(): int
    {
        return Festival::year();
    }

    /** @return list<array{key: string, name: string, hex: ?string, active: bool}> */
    public static function szinek(): array
    {
        return array_values(array_map(fn ($c) => [
            'key' => (string) $c['key'],
            'name' => (string) $c['name'],
            'hex' => filled($c['hex'] ?? null) ? (string) $c['hex'] : null,
            'active' => (bool) ($c['active'] ?? true),
        ], array_filter((array) Settings::get('olympics.colors', []), fn ($c) => filled($c['key'] ?? null) && filled($c['name'] ?? null))));
    }

    /** @return array{key: string, name: string, hex: ?string, active: bool}|null */
    public static function szin(string $kulcs): ?array
    {
        foreach (self::szinek() as $c) {
            if ($c['key'] === $kulcs) {
                return $c;
            }
        }

        return null;
    }

    /**
     * Az űrlap állapota a beküldés pillanatában (2026: loadState): a színek az ELVITT jelöléssel és a kapacitás.
     * Az „elvitt” az idei csapatoknál szereplő szín (a visszalépett csapat színe már üres, tehát szabad).
     *
     * @return array{szinek: list<array{key: string, name: string, hex: ?string, taken: bool, selectable: bool}>, csapatok: int, max: int, szabad: int, varolista: bool}
     */
    public static function allapot(): array
    {
        $ev = self::ev();
        $elvitt = OlympicsTeam::query()->where('year', $ev)->whereNotNull('color_key')->pluck('color_key')->all();
        $csapatok = OlympicsTeam::query()->where('year', $ev)->where('status', '!=', OlympicsStatus::Withdrawn->value)->count();
        $max = self::maxCsapat();

        return [
            'szinek' => array_map(fn ($c) => $c + [
                'taken' => in_array($c['key'], $elvitt, true),
                'selectable' => $c['active'] && ! in_array($c['key'], $elvitt, true),
            ], self::szinek()),
            'csapatok' => $csapatok,
            'max' => $max,
            'szabad' => max(0, $max - $csapatok),
            'varolista' => $csapatok >= $max,
        ];
    }

    /** Az olimpia napja (Beállítások), vagy null, ha még nincs megadva. */
    public static function nap(): ?CarbonImmutable
    {
        $d = Settings::get('olympics.event_date');

        return blank($d) ? null : CarbonImmutable::parse($d);
    }

    /**
     * Az olimpia napja a 2026-os bevezető alakjában: „2027. augusztus 28-án, szombaton”. A nap ragja a kiejtés szerint
     * (1-jén, 2-án, 3-án, 4-én … 20-án, 21-én, 22-én, 23-án …), a hét napjáé a napnév szerint.
     */
    public static function napSzoveg(?CarbonImmutable $nap = null): ?string
    {
        $nap ??= self::nap();
        if ($nap === null) {
            return null;
        }
        $n = $nap->day;
        $rag = match (true) {
            $n === 1 => 'jén',
            $n === 2 => 'án',
            $n === 10 => 'én',
            $n === 20, $n === 30 => 'án',
            default => in_array($n % 10, [3, 6, 8], true) ? 'án' : 'én',
        };
        $napnev = [1 => 'hétfőn', 'kedden', 'szerdán', 'csütörtökön', 'pénteken', 'szombaton', 'vasárnap'][$nap->dayOfWeekIso];

        return $nap->year . '. ' . $nap->locale('hu')->isoFormat('MMMM') . ' ' . $n . '-' . $rag . ', ' . $napnev;
    }
}
