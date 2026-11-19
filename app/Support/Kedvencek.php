<?php

namespace App\Support;

use App\Enums\ProgramStatus;
use App\Models\FavoriteList;
use App\Models\ProgramOccurrence;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KEDVENCEK — EGY helyen (PROMPT-21): a lista tárolása és a lap adatai.
 *
 * AZONOSÍTÁS BELÉPÉS NÉLKÜL: a lista egy véletlen kulcshoz tartozik (32 hexa karakter, a megosztható link része), személyes
 * adat nincs. A böngésző a kulcsot és a program-azonosítókat a saját tárolójában tartja; a szerver a közös igazság.
 *
 * A VÁLTOZÁS MŰVELET, NEM A TELJES LISTA (2026-ban a kliens a teljes listát küldte): két eszköz ugyanazon a listán —
 * ha mindkettő a saját teljes listáját írná vissza, az egyik a másik mentését csendben eltüntetné. Így a szerver a
 * „+ program” / „− program” műveleteket sorban, zárolt sorra alkalmazza, és a friss listát adja vissza.
 */
final class Kedvencek
{
    /** Felső korlát egy listára (2026: 300) — ennyi program bőven elég, és a sor nem nőhet a végtelenig. */
    public const MAX = 300;

    /** Program-azonosítók tisztítása: pozitív egész, ismétlés nélkül, legfeljebb MAX darab, az eredeti sorrendben. */
    public static function tisztit(mixed $ids): array
    {
        $ki = [];
        foreach (is_array($ids) ? $ids : [] as $x) {
            $n = filter_var($x, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($n !== false && ! in_array($n, $ki, true)) {
                $ki[] = $n;
                if (count($ki) >= self::MAX) {
                    break;
                }
            }
        }

        return $ki;
    }

    public static function ervenyesKulcs(string $kulcs): bool
    {
        return (bool) preg_match('/^[0-9a-f]{32}$/', $kulcs);
    }

    public static function letrehoz(array $ids): FavoriteList
    {
        return FavoriteList::create(['token' => bin2hex(random_bytes(16)), 'program_ids' => self::tisztit($ids)]);
    }

    public static function keres(string $kulcs): ?FavoriteList
    {
        return self::ervenyesKulcs($kulcs) ? FavoriteList::query()->where('token', $kulcs)->first() : null;
    }

    /**
     * Műveletek alkalmazása sorban: [[programId, true|false], …] (true = hozzáad, false = levesz). Zárolt sorral, tranzakcióban.
     *
     * @return ?list<int> a friss lista, vagy null, ha nincs ilyen lista
     */
    public static function alkalmaz(string $kulcs, array $muveletek): ?array
    {
        if (! self::ervenyesKulcs($kulcs)) {
            return null;
        }

        return DB::transaction(function () use ($kulcs, $muveletek) {
            $lista = FavoriteList::query()->where('token', $kulcs)->lockForUpdate()->first();
            if ($lista === null) {
                return null;
            }
            $ids = self::tisztit($lista->program_ids);
            foreach (array_slice($muveletek, 0, self::MAX) as $m) {
                $id = filter_var(is_array($m) ? ($m[0] ?? null) : null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($id === false) {
                    continue;
                }
                $ids = array_values(array_filter($ids, fn ($x) => $x !== $id));
                if (! empty($m[1]) && count($ids) < self::MAX) {
                    $ids[] = $id;
                }
            }
            $lista->program_ids = $ids;
            $lista->save();

            return $ids;
        });
    }

    /**
     * A mentett programok MINDEN fesztiválnapi időpontja, a kereső sorainak alakjában (ProgramSor), napok szerint
     * csoportosítva. A nap sorai: időrendben, az „egész nap nyitva” a végén (2026: kedvenceim/script.js render()).
     *
     * @return Collection<string, Collection<int, ProgramSor>> nap (Y-m-d) → sorok
     */
    public static function napok(array $ids): Collection
    {
        $ids = self::tisztit($ids);
        if ($ids === []) {
            return collect();
        }
        $idopontok = ProgramOccurrence::query()
            ->select('program_occurrences.*')
            ->join('program_items as p', 'p.id', '=', 'program_occurrences.program_item_id')
            ->whereIn('p.status', ProgramStatus::publicValues())
            ->whereIn('p.id', $ids)
            ->whereIn('program_occurrences.date', Festival::dayIds())
            ->orderBy('program_occurrences.date')->orderBy('program_occurrences.id')
            ->get();
        $idopontok->load(['programItem' => fn ($q) => $q->with(['category', 'venue.parent', 'cover', 'translations'])]);

        return $idopontok->map(fn (ProgramOccurrence $o) => new ProgramSor($o))
            ->groupBy(fn (ProgramSor $s) => $s->nap())
            ->sortKeys()
            ->map(fn (Collection $g) => $g->sortBy(fn (ProgramSor $s) => [$s->egeszNap() ? 1 : 0, $s->kezd, $s->idopont->id])->values());
    }

    /**
     * ÁTFEDÉS (2026: utkozes): ugyanazon a napon időben átfedő két tétel — az „egész nap nyitva” és a vég nélküli kimarad.
     * Figyelmeztetés, NEM tiltás.
     *
     * @param  Collection<int, ProgramSor>  $napiak
     * @return Collection<int, ProgramSor> a $sor-ral átfedő sorok
     */
    public static function atfedesek(ProgramSor $sor, Collection $napiak): Collection
    {
        if ($sor->egeszNap() || $sor->veg === null) {
            return collect();
        }

        return $napiak->filter(fn (ProgramSor $q) => $q !== $sor && ! $q->egeszNap() && $q->veg !== null
            && $sor->kezd < $q->veg && $q->kezd < $sor->veg)->values();
    }

    /**
     * Az „indulásnak” ajánlott programok az üres listához (2026: ajanlo): a napi kiemelések, különben az első programok;
     * legfeljebb 3, programonként egy (az első) időpont.
     *
     * @return Collection<int, ProgramSor>
     */
    public static function ajanlott(): Collection
    {
        $kiemelt = DB::table('program_highlights')->orderBy('date')->orderBy('sort')->orderBy('id')->pluck('program_item_id')->unique()->all();
        $q = ProgramOccurrence::query()
            ->select('program_occurrences.*')
            ->join('program_items as p', 'p.id', '=', 'program_occurrences.program_item_id')
            ->whereIn('p.status', ProgramStatus::publicValues())
            ->whereIn('program_occurrences.date', Festival::dayIds())
            ->orderBy('program_occurrences.date')->orderBy('program_occurrences.start_time')->orderBy('program_occurrences.id');
        $idopontok = (clone $q)->whereIn('p.id', $kiemelt ?: [0])->get()->unique('program_item_id')
            ->sortBy(fn ($o) => array_search($o->program_item_id, $kiemelt, true))->take(3)->values();
        if ($idopontok->isEmpty()) {
            $idopontok = $q->get()->unique('program_item_id')->take(3)->values();
        }
        $idopontok->load(['programItem' => fn ($q) => $q->with(['category', 'venue.parent', 'cover', 'translations'])]);

        return $idopontok->map(fn (ProgramOccurrence $o) => new ProgramSor($o));
    }
}
