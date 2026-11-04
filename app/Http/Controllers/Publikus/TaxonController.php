<?php

namespace App\Http\Controllers\Publikus;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProgramItem;
use App\Models\Venue;
use App\Support\Nyelv;
use App\Support\ProgramKartya;
use App\Support\ProgramKereso;
use App\Support\ProgramSzures;
use App\Support\Terkep;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Helyszínek, kategóriák és a térkép lapja (PROMPT-15) — a 2026-os TaxonListView, VenueDetailView, TaxonDetailView
 * átirata (adat: itt; jelölés: resources/views/taxon/*). A /terkep a programkereső rajzolt Vár/Város térképe önállóan
 * (tulajdonosi döntés, 2026-11-04: a 2026-os OSM-es /terkep GPS nélkül üres volt, a rendszer valódi mechanizmusa a rajzolt
 * térkép — a jelölő a helyszín lapjára visz).
 */
class TaxonController extends Controller
{
    public function helyszinek(): View
    {
        $db = $this->programok()->groupBy('venue_id')->map->count();
        $lista = ProgramKartya::abc(Venue::query()->get(), fn (Venue $v) => ProgramKartya::helyszinNev($v))
            ->map(fn (Venue $v) => ['href' => Nyelv::ut('/helyszin/' . $v->slug), 'nev' => ProgramKartya::helyszinNev($v), 'db' => $db[$v->id] ?? 0]);

        return view('taxon.lista', ['cim' => __('publikus.b_helyszinek'), 'kat' => false, 'lista' => $lista]);
    }

    public function kategoriak(): View
    {
        // 2026: egy program az ELSŐDLEGES és a TOVÁBBI kategóriái alatt is számít (az összeg nagyobb lehet a programszámnál).
        $db = [];
        foreach ($this->programok() as $p) {
            foreach ($this->kategoriaIdk($p) as $id) {
                $db[$id] = ($db[$id] ?? 0) + 1;
            }
        }
        $nev = fn (Category $k) => Nyelv::valaszt($k->name_hu, $k->name_ro);
        $lista = ProgramKartya::abc(Category::query()->get(), $nev)
            ->map(fn (Category $k) => ['href' => Nyelv::ut('/kategoria/' . $k->slug), 'nev' => $nev($k), 'db' => $db[$k->id] ?? 0]);

        return view('taxon.lista', ['cim' => __('publikus.b_kategoriak'), 'kat' => true, 'lista' => $lista]);
    }

    /**
     * A helyszín és a kategória lapja a PROGRAMKERESŐ lekérdezésével és soraival (tulajdonosi szabály, 2026-11-04): a lap
     * pontosan azt mutatja, amit a kereső erre a szűrésre (minden nap) — és a „Vissza a programkeresőhöz” ide visz.
     */
    public function helyszin(string $slug): View
    {
        $v = Venue::query()->with('parent')->where('slug', $slug)->firstOrFail();
        $kereso = new ProgramSzures(ProgramSzures::MIND, [], [], [$v->slug]);

        return view('taxon.helyszin', [
            'v' => $v,
            'gyerekek' => ProgramKartya::abc(Venue::query()->where('parent_id', $v->id)->get(), fn (Venue $c) => ProgramKartya::helyszinNev($c)),
            'jelolo' => Terkep::jeloloje($v),
            'kereso' => $kereso,
            'sorok' => (new ProgramKereso($kereso))->talalatok()['sorok'],
            'egyNap' => false,
            'ures' => __('publikus.b_nincs_helyszin'),
        ]);
    }

    public function kategoria(string $slug): View
    {
        $k = Category::query()->where('slug', $slug)->firstOrFail();
        $kereso = new ProgramSzures(ProgramSzures::MIND, [], [$k->slug]);

        return view('taxon.kategoria', [
            'k' => $k,
            'kereso' => $kereso,
            'sorok' => (new ProgramKereso($kereso))->talalatok()['sorok'],
            'egyNap' => false,
            'ures' => __('publikus.b_nincs_kategoria'),
        ]);
    }

    public function terkep(): View
    {
        $terkep = Terkep::adat(Nyelv::aktualis());
        // A jelölők programszáma: minden közzétett program, a szülőnél a termeivel együtt (mint a keresőben szűrés nélkül).
        $db = [];
        foreach ($this->programok() as $p) {
            if ($p->venue) {
                $db[$p->venue->slug] = ($db[$p->venue->slug] ?? 0) + 1;
                if ($p->venue->parent) {
                    $db[$p->venue->parent->slug] = ($db[$p->venue->parent->slug] ?? 0) + 1;
                }
            }
        }

        return view('taxon.terkep', ['terkep' => $terkep, 'terkepAllapot' => Terkep::allapot($db, [])]);
    }

    /** @return Collection<int, ProgramItem> a közzétett programok — a listák és a térkép darabszámaihoz */
    private function programok(): Collection
    {
        return ProgramItem::query()->publicVisible()->with(['venue.parent', 'extraCategories'])->get();
    }

    /** @return list<int> */
    private function kategoriaIdk(ProgramItem $p): array
    {
        return array_values(array_unique(array_filter([$p->category_id, ...$p->extraCategories->pluck('id')->all()])));
    }
}
