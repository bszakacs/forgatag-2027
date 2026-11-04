<?php

namespace App\Http\Controllers\Publikus;

use App\Enums\ContentStatus;
use App\Enums\TranslatableField;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\ProgramItem;
use App\Models\Venue;
use App\Support\Festival;
use App\Support\LapSzovegek;
use App\Support\Nyelv;
use App\Support\ProgramKartya;
use App\Support\ProgramKereso;
use App\Support\ProgramSor;
use App\Support\ProgramSzoveg;
use App\Support\ProgramSzures;
use App\Support\Terkep;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
        if ($htmx && $request->header('HX-Target') === 'pkModal') {
            return response()->view('programok._felugro', ['szures' => $sz, 'sz' => $szovegek, 'felugro' => $felugro, 'ny' => Nyelv::aktualis()])
                ->header('HX-Push-Url', $sz->url());
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
     * A napi nézet — a 2026-os DailyProgramView (PROMPT-15): egy nap sorai, vagy az „Állandó” fülön (`allando`) a legalább két
     * KÜLÖNBÖZŐ napon futó programok, cím szerint. A napfülek a fesztivál MINDEN programnapja (2026: FESTIVAL.days).
     */
    public function nap(string $datum): Response
    {
        $allando = $datum === 'allando';
        abort_unless($allando || Festival::isProgramDay($datum), 404);
        $sorok = $allando ? collect() : ProgramKereso::napi($datum);
        $programok = $allando
            ? ProgramKartya::abc(ProgramItem::query()->publicVisible()->with(ProgramKartya::KAPCSOLATOK)->get()
                ->filter(fn (ProgramItem $p) => ProgramKartya::idopontok($p)->map(fn ($o) => $o->date->format('Y-m-d'))->unique()->count() >= 2),
                fn (ProgramItem $p) => ProgramSzoveg::mezo($p, TranslatableField::Title)['szoveg'])
            : collect();
        // a sorok programjai a keresőszöveghez (további kategóriák) — egy lekérdezéssel, nem soronként
        (new EloquentCollection($sorok->map(fn (ProgramSor $s) => $s->program())->unique('id')->values()->all()))->loadMissing('extraCategories');

        return response()->view('programok.nap', [
            'datum' => $datum,
            'napok' => Festival::days(),
            'sorok' => $sorok,
            'programok' => $programok,
            'fohelyszinek' => ProgramKartya::abc(Venue::query()->whereNull('parent_id')->get(), fn (Venue $v) => $v->name),
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

        $sorok = $program->occurrences
            ->filter(fn ($o) => Festival::isProgramDay($o->date->format('Y-m-d')))
            ->each(fn ($o) => $o->setRelation('programItem', $program))
            ->map(fn ($o) => new ProgramSor($o))
            ->sortBy(fn (ProgramSor $s) => $s->nap() . sprintf('%05d', $s->kezd))->values();

        return response()->view('programok.show', ['program' => $program, 'sorok' => $sorok]);
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
