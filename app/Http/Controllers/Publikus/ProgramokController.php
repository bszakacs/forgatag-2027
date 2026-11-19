<?php

namespace App\Http\Controllers\Publikus;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\ProgramItem;
use App\Support\Festival;
use App\Support\LapSzovegek;
use App\Support\Nyelv;
use App\Support\ProgramKereso;
use App\Support\ProgramSor;
use App\Support\ProgramSzures;
use App\Support\Terkep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A programkereső (`/programok`), a napi nézet (`/nap/<dátum>`), a „Mi van ma?” (`/ma`) és a program lapja
 * (`/program/<slug>`). A lekérdezés EGY helyen: App\Support\ProgramKereso; a szűrés címsora: ProgramSzures.
 *
 * A szerver MINDIG a teljes lapot adja (mint a hírek lapozója): JavaScript nélkül a szűrő-űrlap rendes GET, a
 * htmx pedig a válaszból csak a találati részt emeli ki (hx-select) — a térkép helye ezen KÍVÜL van, hogy a
 * csere ne semmisítse meg az eseménykezelőit.
 */
class ProgramokController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $nyers = (string) $request->server('QUERY_STRING');
        $sz = ProgramSzures::felcimsorbol($nyers);
        $htmx = $request->header('HX-Request') === 'true';

        // Egy szűrésnek EGY címe van: a JS nélküli űrlap (üres mezők, „+” a szóközben) a tiszta alakra ugrik.
        if (! $htmx && ! $sz->kanonikus($nyers)) {
            return redirect($sz->url(), 302);
        }

        $lap = $this->lap();
        $szovegek = new LapSzovegek($lap, Nyelv::aktualis());
        $felugro = $this->felugro($sz);

        // A felugró ablak nyitása/zárása CSAK az ablakot kéri (a kártya és a bezáró gomb htmx-célja: #pkModal).
        // A „Kedvenc programjaim” lapja ugyanezt az ablakot kéri (X-Kedvencek): ott a címsor nem változik, a bezárás helyben
        // zár, és a „Mentés” rejtett (a lapon a kártya szíve ment — 2026: program-modal.js `mentes: false`).
        if ($htmx && $request->header('HX-Target') === 'pkModal') {
            $kedvencLap = $request->header('X-Kedvencek') === '1';
            $valasz = response()->view('programok._felugro', ['szures' => $sz, 'sz' => $szovegek, 'felugro' => $felugro, 'ny' => Nyelv::aktualis(), 'kedvencLap' => $kedvencLap]);

            $valasz->header('Vary', 'HX-Request, HX-Target, X-Kedvencek');

            return $kedvencLap ? $valasz : $valasz->header('HX-Push-Url', $sz->url());
        }

        $kereso = new ProgramKereso($sz);
        $t = $kereso->talalatok();
        $darabok = $kereso->darabok();

        $valasz = response()->view('programok.index', [
            'lap' => $lap,
            'sz' => $szovegek,
            'szures' => $sz,
            't' => $t,
            'darabok' => $darabok,
            // A térkép EGYSZER rajzolódik (a htmx-válaszból nem cserélődik, ott el sem készül); a szűrésre a
            // #pkTerkepAdat adat-elemből frissül.
            'terkep' => $htmx ? null : Terkep::adat(Nyelv::aktualis()),
            'terkepAllapot' => Terkep::allapot($darabok['helyszin'], $sz->helyszinek),
            'kiemelt' => $kereso->kiemeltek(),
            'lazitas' => $t['osszes'] === 0 && ! $t['most'] ? $kereso->lazitas() : null,
            'felugro' => $felugro,
            'napok' => $this->napok(),
            'kategoriak' => ProgramKereso::kategoriak(),
            'helyszinek' => ProgramKereso::helyszinek(),
            'vanProgram' => ProgramKereso::programosNapok() !== [],
        ]);

        // A htmx a címsorba a TISZTA címet írja (nem az űrlap nyers mezőit, üres értékekkel).
        return $htmx ? $valasz->header('HX-Push-Url', $sz->url()) : $valasz;
    }

    /**
     * A napi nézet — a közös programlista-lap (programok/lista) a KERESŐ lekérdezésével és soraival (tulajdonosi szabály,
     * 2026-11-04): egy nap sorai napszakonként, vagy az „Állandó” fülön (`allando`) a legalább két KÜLÖNBÖZŐ napon futó
     * programok, programonként egyszer (az első napjuknál). A „Vissza a programkeresőhöz” ugyanarra a napra visz.
     */
    public function nap(string $datum): Response
    {
        $allando = $datum === 'allando';
        abort_unless($allando || Festival::isProgramDay($datum), 404);
        $kereso = new ProgramSzures($allando ? ProgramSzures::MIND : $datum);
        $sorok = (new ProgramKereso($kereso))->talalatok()['sorok'];
        if ($allando) {
            $napjai = $sorok->groupBy(fn (ProgramSor $s) => $s->program()->id)->map(fn ($g) => $g->map(fn ($s) => $s->nap())->unique()->count());
            $sorok = $sorok->filter(fn (ProgramSor $s) => $napjai[$s->program()->id] >= 2)->unique(fn (ProgramSor $s) => $s->program()->id)->values();
        }

        return response()->view('programok.nap', [
            'datum' => $datum,
            'napok' => Festival::days(),
            'kereso' => $kereso,
            'sorok' => $sorok,
            'egyNap' => ! $allando,
            'ures' => __('publikus.b_nincs_napon'),
        ]);
    }

    /** „Mi van ma?”: a mai fesztiválnap (hajnali vágással) — ha ma nincs fesztivál, a NYITÓNAP (nem a lista első, előkészítő napja). */
    public function ma(): RedirectResponse
    {
        $ma = Festival::festivalMoment()['nap'];

        return redirect(Nyelv::ut('/nap/' . (Festival::isProgramDay($ma) ? $ma : Festival::openingDay())), 302);
    }

    public function show(string $slug): Response
    {
        $program = ProgramItem::query()->publicVisible()->where('slug', $slug)
            ->with(['category', 'extraCategories', 'venue.parent', 'cover', 'translations', 'occurrences'])
            ->firstOrFail();

        return response()->view('programok.show', ['program' => $program, 'sorok' => $this->sorok($program)]);
    }

    /**
     * NAPTÁRBA (`/program/<slug>.ics`, PROMPT-21): a program MINDEN fesztiválnapi időpontja egy fájlban (a program lapjának
     * gombja); `?idopont=<id>`-vel CSAK az az egy (a felugró ablak gombja — a nézett időpont, mint 2026-ban). Lemondott
     * program nem kerül naptárba (a gomb sem látszik). Az időpontok: App\Support\Naptar.
     */
    public function naptar(Request $request, string $slug): Response
    {
        $program = ProgramItem::query()->publicVisible()->where('slug', $slug)
            ->with(['category', 'venue.parent', 'cover', 'translations', 'occurrences'])->firstOrFail();
        abort_if($program->status === \App\Enums\ProgramStatus::Cancelled, 404);
        $sorok = $this->sorok($program);
        if ($request->filled('idopont')) {
            $sorok = $sorok->filter(fn (ProgramSor $s) => $s->idopont->id === (int) $request->query('idopont'))->values();
        }
        abort_if($sorok->isEmpty(), 404);

        return \App\Support\Naptar::valasz(\App\Support\Naptar::ics($sorok), 'forgatag-' . $program->slug . '.ics');
    }

    /** A program fesztiválnapi időpontjai sorként, időrendben (a lap és a naptárfájl közös forrása). */
    private function sorok(ProgramItem $program): \Illuminate\Support\Collection
    {
        return $program->occurrences
            ->filter(fn ($o) => Festival::isProgramDay($o->date->format('Y-m-d')))
            ->each(fn ($o) => $o->setRelation('programItem', $program))
            ->map(fn ($o) => new ProgramSor($o))
            ->sortBy(fn (ProgramSor $s) => $s->nap() . sprintf('%05d', $s->kezd))->values();
    }

    /**
     * A felugró ablak programja és az időpontja: a kattintott (`idopont`), különben a kiválasztott nap első időpontja,
     * különben a program első fesztiválnapi időpontja.
     *
     * @return ?array{program: ProgramItem, sor: ?ProgramSor}
     */
    private function felugro(ProgramSzures $sz): ?array
    {
        if ($sz->program === null) {
            return null;
        }
        $program = ProgramItem::query()->publicVisible()->where('slug', $sz->program)
            ->with(['category', 'venue', 'cover', 'translations', 'occurrences'])->firstOrFail();
        $idopontok = $program->occurrences->filter(fn ($o) => Festival::isProgramDay($o->date->format('Y-m-d')))
            ->each(fn ($o) => $o->setRelation('programItem', $program));
        $o = $idopontok->firstWhere('id', $sz->idopont)
            ?? $idopontok->first(fn ($o) => $o->date->format('Y-m-d') === $sz->datum())
            ?? $idopontok->first();

        return ['program' => $program, 'sor' => $o ? new ProgramSor($o) : null];
    }

    private function lap(): Page
    {
        return Page::query()->where('slug', 'programok')->where('status', ContentStatus::Published)->firstOrFail();
    }

    /**
     * A napválasztó napjai: a fesztivál MINDEN napja (az előkészítők halványan), amelyre van publikus program.
     *
     * @return list<array{id: string, date: \Carbon\CarbonImmutable, prep: bool}>
     */
    private function napok(): array
    {
        $van = ProgramKereso::programosNapok();

        return array_values(array_filter(Festival::days(), fn ($d) => in_array($d['id'], $van, true)));
    }
}
