<?php

namespace App\Support;

use App\Enums\ProgramStatus;
use App\Models\Category;
use App\Models\ProgramOccurrence;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A programkereső LEKÉRDEZÉSE — EGY helyen. A szűrés az adatbázisban fut (2026-ban a böngésző egy előre
 * legyártott adatcsomagban szűrt), a programkereső, a napi nézet és a „Mi van ma?” mind ezt használja.
 * A LÁTHATÓ viselkedés a 2026-os (`programkereso/script.js`, CLAUDE.md 2/e):
 *
 * · Csak a publikus státuszú program (ProgramStatus::publicValues) és csak FESZTIVÁLNAPRA eső időpont látszik.
 * · Egy időpont = egy sor; minden találat EGY lapon (2026-ban sem volt lapozás). A sorrend a FESZTIVÁLNAP skáláján
 *   (hajnali vágás, Festival::scaledMinutes): a 00:30-as kezdés a nap VÉGÉRE kerül. A dátum a beküldött nap.
 * · Napszak: a kezdés szerint (12:00 / 18:00 határ); az „egész nap nyitva” tétel MINDEN napszakban átmegy.
 * · Helyszín-hierarchia: a szülő helyszín kiválasztása a termeit is hozza.
 * · Kategória: az elsődleges ÉS a további kategóriák is számítanak; a program akkor is EGYSZER szerepel.
 * · Keresés: cím (román fordítással), szervező, fellépő, helyszín és kategória neve — a 2026-os keresési kulcs; a
 *   leírásban NEM keres (2026-ban sem).
 * · A szűrőlisták darabszámai „a többi szűrő mellett” számolnak (a saját dimenziót kihagyva): a nulla halványít,
 *   de semmi nem tűnik el (2026-os tulajdonosi döntés).
 */
final class ProgramKereso
{
    /** A „Most zajlik” nézetben a „Következik” csoport hossza (2026: 12). */
    public const KOVETKEZIK = 12;

    public function __construct(private readonly ProgramSzures $sz) {}

    /**
     * A találatok. „Most zajlik” módban két csoport (zajlik / következik), egyébként a nap (vagy minden nap) teljes listája.
     *
     * @return array{osszes: int, sorok: Collection<int, ProgramSor>, most: ?array{zajlik: Collection<int, ProgramSor>, kovetkezik: Collection<int, ProgramSor>, fesztivalon: bool, elotte: bool}}
     */
    public function talalatok(): array
    {
        if ($this->sz->most()) {
            $p = Festival::festivalMoment();
            $fesztivalon = Festival::isProgramDay($p['nap']);
            $napi = $fesztivalon ? $this->sorok($this->alap('*', $p['nap'])->get()) : collect();
            $zajlik = $napi->filter(fn (ProgramSor $s) => $s->zajlik($p['perc']))->values();
            $kovetkezik = $napi->filter(fn (ProgramSor $s) => $s->kezd > $p['perc'])->take(self::KOVETKEZIK)->values();

            return [
                'osszes' => $zajlik->count() + $kovetkezik->count(),
                'sorok' => $zajlik->concat($kovetkezik),
                'most' => [
                    'zajlik' => $zajlik, 'kovetkezik' => $kovetkezik, 'fesztivalon' => $fesztivalon,
                    'elotte' => $p['nap'] < (Festival::dayIds()[0] ?? $p['nap']),
                ],
            ];
        }

        $sorok = $this->sorok($this->alap('*', $this->sz->datum())->get());

        return ['osszes' => $sorok->count(), 'sorok' => $sorok, 'most' => null];
    }

    /** Egy nap minden időpontja, szűrés nélkül (a napi nézet). @return Collection<int, ProgramSor> */
    public static function napi(string $nap): Collection
    {
        $k = new self(new ProgramSzures($nap));

        return $k->sorok($k->alap('*', $nap)->get());
    }

    /** Mely napokra van egyáltalán (publikus) program — a napválasztó ezeket mutatja. @return list<string> */
    public static function programosNapok(): array
    {
        return (new self(new ProgramSzures(ProgramSzures::MIND)))->alap('*', null)
            ->reorder()->select('program_occurrences.date')->distinct()->orderBy('program_occurrences.date')
            ->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->unique()->values()->all();
    }

    /**
     * A NAPI KIEMELT SÁV: a koordinátor sorrendjében. A nap a kiválasztott nap; nap nélkül (keresés, „Most zajlik”,
     * minden nap) a 2026-os kezdőnap — fesztivál alatt a mai, előtte a nyitónap, utána az utolsó nap. Csak a ma is
     * közzétett és AZON A NAPON időponttal bíró program jelenik meg (a kártyán a nap első időpontja).
     *
     * @return array{nap: string, sorok: Collection<int, ProgramSor>}
     */
    public function kiemeltek(): array
    {
        $nap = $this->sz->datum() ?? self::kezdoDatum();
        $idopontok = ProgramOccurrence::query()
            ->join('program_items as p', 'p.id', '=', 'program_occurrences.program_item_id')
            ->join('program_highlights as h', function ($j) {
                $j->on('h.program_item_id', '=', 'p.id')->on('h.date', '=', 'program_occurrences.date');
            })
            ->whereIn('p.status', ProgramStatus::publicValues())
            ->where('program_occurrences.date', $nap)
            ->orderBy('h.sort')->orderBy('h.id')->orderBy('program_occurrences.start_time')
            ->select('program_occurrences.*')->get()
            ->unique('program_item_id')->values();

        return ['nap' => $nap, 'sorok' => $this->sorok($idopontok)];
    }

    /** A 2026-os kezdőnap DÁTUMA (a „Most” helyett a mai nap). */
    public static function kezdoDatum(): string
    {
        $alap = ProgramSzures::alapNap();

        return $alap === ProgramSzures::MOST ? Festival::festivalMoment()['nap'] : $alap;
    }

    /**
     * Üres találatnál a 2026-os javaslat: melyik szűrő elengedésével marad a legtöbb program.
     *
     * @return ?array{mi: string, db: int} mi: helyszin / kategoria / napszak
     */
    public function lazitas(): ?array
    {
        $nap = $this->sz->most() ? Festival::festivalMoment()['nap'] : $this->sz->datum();
        $legjobb = null;
        foreach (['helyszin' => $this->sz->helyszinek, 'kategoria' => $this->sz->kategoriak, 'napszak' => $this->sz->napszakok] as $mi => $aktiv) {
            if (! $aktiv) {
                continue;
            }
            $db = $this->alap($mi, $nap)->count();
            if ($legjobb === null || $db > $legjobb['db']) {
                $legjobb = ['mi' => $mi, 'db' => $db];
            }
        }

        return $legjobb !== null && $legjobb['db'] > 0 ? $legjobb : null;
    }

    /**
     * Darabszámok a szűrőlistákhoz: kategória-slug → db, helyszín-slug → db (a szülő a termeivel együtt).
     *
     * @return array{kategoria: array<string, int>, helyszin: array<string, int>}
     */
    public function darabok(): array
    {
        $nap = $this->sz->most() ? Festival::festivalMoment()['nap'] : $this->sz->datum();

        // Kategória: elsődleges + további (a további csak ha nem azonos az elsődlegessel — egy program egyszer).
        $kat = [];
        foreach ((clone $this->alap('kategoria', $nap))->reorder()
            ->select(DB::raw('p.category_id as k'), DB::raw('count(*) as db'))->groupBy('p.category_id')->get() as $r) {
            $kat[$r->k] = ($kat[$r->k] ?? 0) + (int) $r->db;
        }
        foreach ((clone $this->alap('kategoria', $nap))->reorder()
            ->join('category_program_item as cpi', 'cpi.program_item_id', '=', 'p.id')
            ->whereColumn('cpi.category_id', '!=', 'p.category_id')
            ->select(DB::raw('cpi.category_id as k'), DB::raw('count(*) as db'))->groupBy('cpi.category_id')->get() as $r) {
            $kat[$r->k] = ($kat[$r->k] ?? 0) + (int) $r->db;
        }

        $hely = [];
        foreach ((clone $this->alap('helyszin', $nap))->reorder()
            ->select(DB::raw('p.venue_id as v'), DB::raw('count(*) as db'))->groupBy('p.venue_id')->get() as $r) {
            $hely[$r->v] = (int) $r->db;
        }
        $venues = self::helyszinek();
        $helyDb = [];
        foreach ($venues as $v) {
            $helyDb[$v->slug] = ($hely[$v->id] ?? 0) + $venues->where('parent_id', $v->id)->sum(fn ($g) => $hely[$g->id] ?? 0);
        }

        return [
            'kategoria' => self::kategoriak()->mapWithKeys(fn ($c) => [$c->slug => $kat[$c->id] ?? 0])->all(),
            'helyszin' => $helyDb,
        ];
    }

    /** @return Collection<int, Category> a szűrőlista sorrendjében */
    public static function kategoriak(): Collection
    {
        return Category::query()->orderBy('sort')->orderBy('id')->get();
    }

    /** @return Collection<int, Venue> név szerint (a szülő után a termei a nézetben rendeződnek) */
    public static function helyszinek(): Collection
    {
        return Venue::query()->orderBy('name')->get();
    }

    /**
     * Az alap-lekérdezés a szűrőkkel. `$kihagy`: a darabszámoláskor/lazításkor a saját dimenzió nem szűr ('kategoria' /
     * 'helyszin' / 'napszak'); '*' = minden szűrő hat. A nap külön paraméter (a „Most zajlik” a mai fesztiválnapot adja).
     */
    private function alap(string $kihagy, ?string $nap): Builder
    {
        $cut = sprintf('%02d:00:00', Festival::dayCutHour());
        $q = ProgramOccurrence::query()
            ->select('program_occurrences.*')
            ->join('program_items as p', 'p.id', '=', 'program_occurrences.program_item_id')
            ->whereIn('p.status', ProgramStatus::publicValues())
            ->whereIn('program_occurrences.date', Festival::dayIds())
            ->orderBy('program_occurrences.date')
            // A vágás előtti kezdés (00:00–03:00) a nap VÉGÉRE: előbb a „nappaliak”, aztán az éjszakaiak, időrendben.
            ->orderByRaw('case when program_occurrences.start_time < ? then 1 else 0 end', [$cut])
            ->orderBy('program_occurrences.start_time')
            ->orderBy('p.title')
            ->orderBy('program_occurrences.id');

        if ($nap !== null) {
            $q->where('program_occurrences.date', $nap);
        }
        if ($kihagy !== 'napszak' && $this->sz->napszakok) {
            $this->napszakSzures($q, $cut);
        }
        if ($kihagy !== 'kategoria' && $this->sz->kategoriak) {
            $ids = self::kategoriak()->whereIn('slug', $this->sz->kategoriak)->pluck('id')->all();
            $q->where(fn (Builder $w) => $w->whereIn('p.category_id', $ids)
                ->orWhereExists(fn (QueryBuilder $e) => $e->from('category_program_item')
                    ->whereColumn('category_program_item.program_item_id', 'p.id')
                    ->whereIn('category_program_item.category_id', $ids)));
        }
        if ($kihagy !== 'helyszin' && $this->sz->helyszinek) {
            $venues = self::helyszinek();
            $ids = $venues->whereIn('slug', $this->sz->helyszinek)->pluck('id');
            $ids = $ids->merge($venues->whereIn('parent_id', $ids->all())->pluck('id'))->unique()->all();
            $q->whereIn('p.venue_id', $ids);
        }
        foreach ($this->szavak() as $szo) {
            // Minden szónak meg kell lennie valahol: cím (és román címe), szervező, fellépő (további mezők), helyszín,
            // kategória. Ékezet- és kisbetű-függetlenül: a MySQL utf8mb4_unicode_ci összevetése ezt magától adja.
            $minta = '%' . addcslashes($szo, '%_\\') . '%';
            $q->where(fn (Builder $w) => $w->where('p.title', 'like', $minta)
                ->orWhere('p.organizer_names', 'like', $minta)
                ->orWhere('p.extra_fields', 'like', $minta)
                ->orWhereExists(fn (QueryBuilder $e) => $e->from('program_item_translations as t')
                    ->whereColumn('t.program_item_id', 'p.id')->where('t.field', 'title')
                    ->where('t.translated_text', 'like', $minta))
                ->orWhereExists(fn (QueryBuilder $e) => $e->from('venues as v')->whereColumn('v.id', 'p.venue_id')
                    ->where(fn ($vv) => $vv->where('v.name', 'like', $minta)->orWhere('v.name_ro', 'like', $minta)))
                ->orWhereExists(fn (QueryBuilder $e) => $e->from('categories as c')->whereColumn('c.id', 'p.category_id')
                    ->where(fn ($cc) => $cc->where('c.name_hu', 'like', $minta)->orWhere('c.name_ro', 'like', $minta))));
        }

        return $q;
    }

    /**
     * Napszak (2026: daypartOf + isAllday). A kezdés szerint: délelőtt a vágástól 12:00-ig, délután 18:00-ig, este 18:00
     * után és a vágás előtt (éjjel). Az „egész nap nyitva” tétel (≥ 5 óra, legkésőbb 13:00-kor indul) mindig átmegy.
     */
    private function napszakSzures(Builder $q, string $cut): void
    {
        $plusz5 = DB::getDriverName() === 'sqlite' ? "time(program_occurrences.start_time, '+5 hours')"
            : "ADDTIME(program_occurrences.start_time, '05:00:00')";
        $q->where(function (Builder $w) use ($cut, $plusz5) {
            foreach ($this->sz->napszakok as $n) {
                match ($n) {
                    'delelott' => $w->orWhere(fn ($x) => $x->where('program_occurrences.start_time', '>=', $cut)
                        ->where('program_occurrences.start_time', '<', '12:00:00')),
                    'delutan' => $w->orWhere(fn ($x) => $x->where('program_occurrences.start_time', '>=', '12:00:00')
                        ->where('program_occurrences.start_time', '<', '18:00:00')),
                    'este' => $w->orWhere(fn ($x) => $x->where('program_occurrences.start_time', '>=', '18:00:00')
                        ->orWhere('program_occurrences.start_time', '<', $cut)),
                };
            }
            $w->orWhere(fn ($x) => $x->whereNotNull('program_occurrences.end_time')
                ->where('program_occurrences.start_time', '>=', $cut)
                ->where('program_occurrences.start_time', '<=', '13:00:00')
                ->where(fn ($y) => $y->whereColumn('program_occurrences.end_time', '<', 'program_occurrences.start_time')
                    ->orWhereRaw("program_occurrences.end_time >= {$plusz5}")));
        });
    }

    /** @return list<string> a keresőszavak (legfeljebb 6, az egybetűsek nélkül) */
    private function szavak(): array
    {
        return array_slice(array_values(array_filter(
            preg_split('/\s+/u', $this->sz->q, -1, PREG_SPLIT_NO_EMPTY),
            fn ($s) => mb_strlen($s) > 1,
        )), 0, 6);
    }

    /** @param  \Illuminate\Database\Eloquent\Collection<int, ProgramOccurrence>  $idopontok  @return Collection<int, ProgramSor> */
    private function sorok($idopontok): Collection
    {
        $idopontok->load(['programItem' => fn ($q) => $q->with(['category', 'venue.parent', 'cover', 'translations'])]);

        return $idopontok->map(fn (ProgramOccurrence $o) => new ProgramSor($o))->values();
    }
}
