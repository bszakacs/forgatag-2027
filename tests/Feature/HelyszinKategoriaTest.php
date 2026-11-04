<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ProgramItem;
use App\Models\Venue;
use App\Support\HelyszinTerkepImport;
use App\Support\ProgramTesztImport;
use App\Support\Settings;
use App\Support\Theme;
use App\Support\ThemeCss;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PROMPT-15: a 8 pótolt helyszín és a tesztprogram-pótlás; a helyszín- és kategórialapok, a /terkep (a rajzolt térkép „lap”
 * módban), a napi nézet és a program lapja a 2026-os Base-jelöléssel; a fotó nélküli kártya sarok-firkája és az Arculat
 * kapcsolója; a fejléc keresője.
 */
class HelyszinKategoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $seed = json_decode((string) file_get_contents(base_path('seed/settings.json')), true);
        Settings::save(array_intersect_key($seed, array_flip(['festival', 'submission', 'public', 'notifications'])));
    }

    /** @return array{0: Venue, 1: Venue, 2: Category, 3: Category} */
    private function adat(): array
    {
        $var = Venue::create(['slug' => 'var', 'name' => 'Vár', 'name_ro' => 'Cetate']);
        $terem = Venue::create(['slug' => 'var-terem', 'name' => 'Vár, Lovardaterem', 'parent_id' => $var->id]);
        $var->forceFill(['map_zone' => 'city', 'map_x' => 70.26, 'map_y' => 26.78, 'map_no' => 25])->save();
        $koncert = Category::create(['slug' => 'koncert', 'name_hu' => 'Koncert', 'name_ro' => 'Concert', 'sort' => 1]);
        $szinhaz = Category::create(['slug' => 'szinhaz', 'name_hu' => 'Színház', 'sort' => 2]);

        return [$var, $terem, $koncert, $szinhaz];
    }

    private function program(string $cim, ?Venue $v, ?Category $k, array $napok, array $tobb = []): ProgramItem
    {
        $p = ProgramItem::create(['title' => $cim, 'description' => "Első bekezdés.\n\nMásodik bekezdés.", 'status' => 'published',
            'category_id' => $k?->id, 'venue_id' => $v?->id] + $tobb);
        foreach ($napok as $n) {
            $p->occurrences()->create(['date' => $n, 'start_time' => '18:00', 'end_time' => '19:30']);
        }

        return $p;
    }

    public function test_a_8_helyszin_potlasa_a_roman_nev_es_a_terkep_ismetelhetoen(): void
    {
        Venue::create(['slug' => 'var', 'name' => 'Vár']);
        $naplo = [];
        (new HelyszinTerkepImport)->run(function ($s) use (&$naplo) { $naplo[] = $s; });

        foreach (HelyszinTerkepImport::POTLANDO as $slug) {
            $this->assertTrue(Venue::where('slug', $slug)->exists(), "hiányzik: {$slug}");
        }
        $cs = Venue::where('slug', 'var-csicsergo-1')->first();
        $this->assertSame('Cetate, Curtea "Csicsergő" 1', $cs->name_ro);
        $this->assertSame('var', $cs->parent->slug);
        $this->assertSame(7, (int) $cs->map_no);
        $foci = Venue::where('slug', 'fociakademia')->first();
        $this->assertTrue((bool) $foci->map_edge, 'a Fociakadémia a térkép szélén (irányjelvénnyel), mint 2026-ban');
        $this->assertSame('Cetate', Venue::where('slug', 'var')->value('name_ro'));

        // Másodszor nem hoz létre semmit, és a kézzel átírt román nevet nem írja felül.
        Venue::where('slug', 'var')->update(['name_ro' => 'Cetatea']);
        (new HelyszinTerkepImport)->run(fn () => null);
        $this->assertSame(9, Venue::count());
        $this->assertSame('Cetatea', Venue::where('slug', 'var')->value('name_ro'));
    }

    public function test_a_tesztprogram_potlas_egyszer_hozza_a_potolt_helyszinek_programjait(): void
    {
        Storage::fake('local');
        Venue::create(['slug' => 'var', 'name' => 'Vár']);
        (new HelyszinTerkepImport)->run(fn () => null);
        Storage::disk('local')->put(ProgramTesztImport::JELOLES, '{}');
        $this->program('Egy korábban behozott', Venue::first(), null, ['2027-08-26'])->forceFill(['is_test' => true])->save();

        (new ProgramTesztImport)->run(fn () => null);
        $varhato = collect(json_decode((string) file_get_contents(base_path(ProgramTesztImport::CSOMAG)), true)['programok'])
            ->whereIn('venue', HelyszinTerkepImport::POTLANDO)->count();
        $this->assertGreaterThan(0, $varhato);
        $this->assertSame($varhato + 1, ProgramItem::count());
        $this->assertSame(0, ProgramItem::where('is_test', false)->count(), 'mind TESZT jelöléssel');

        (new ProgramTesztImport)->run(fn () => null);
        $this->assertSame($varhato + 1, ProgramItem::count(), 'a pótlás egyszeri');
    }

    public function test_torolt_tesztadat_utan_a_potlas_nem_hoz_semmit(): void
    {
        Storage::fake('local');
        Venue::create(['slug' => 'var', 'name' => 'Vár']);
        (new HelyszinTerkepImport)->run(fn () => null);
        Storage::disk('local')->put(ProgramTesztImport::JELOLES, '{}');

        (new ProgramTesztImport)->run(fn () => null);
        $this->assertSame(0, ProgramItem::count());
    }

    public function test_helyszinlista_es_a_helyszin_lapja_a_termeivel_es_a_terkepszammal(): void
    {
        [$var, $terem, $koncert] = $this->adat();
        $this->program('A várbeli koncert', $var, $koncert, ['2027-08-27']);
        $this->program('A termi előadás', $terem, $koncert, ['2027-08-28']);

        $lista = $this->get('/helyszinek')->assertOk()->getContent();
        $this->assertStringContainsString('<a class="taxon-card" href="/helyszin/var">Vár<small>1 program</small></a>', $lista);
        $this->assertStringContainsString('alap.css', $lista);

        $html = $this->get('/helyszin/var')->assertOk()->getContent();
        $this->assertStringContainsString('A várbeli koncert', $html);
        $this->assertStringContainsString('A termi előadás', $html, 'a szülő lapja a termei programjait is mutatja');
        $this->assertStringContainsString('<a class="chip cat" href="/helyszin/var-terem">', $html);
        $this->assertMatchesRegularExpression('#<span class="hk-szam"[^>]*>25</span>\s*<a href="/terkep\?helyszin=var">#', $html);
        // a terem saját pozíció nélkül a szülő jelölőjét kapja
        $this->assertStringContainsString('href="/terkep?helyszin=var"', $this->get('/helyszin/var-terem')->getContent());
        $this->assertStringContainsString('Cetate', $this->get('/ro/helyszin/var')->getContent());
        $this->get('/helyszin/nincs-ilyen')->assertNotFound();
    }

    public function test_kategoriak_a_tovabbi_kategoriaval_is(): void
    {
        [$var, , $koncert, $szinhaz] = $this->adat();
        $p = $this->program('Zenés színház', $var, $szinhaz, ['2027-08-27']);
        $p->extraCategories()->attach($koncert->id);

        $this->assertStringContainsString('Koncert<small>1 program</small>', $this->get('/kategoriak')->assertOk()->getContent());
        $html = $this->get('/kategoria/koncert')->assertOk()->getContent();
        $this->assertStringContainsString('Zenés színház', $html);
        $this->assertStringContainsString('<h2>Koncert</h2>', $html);
        $this->assertStringContainsString('Concert', $this->get('/ro/kategoria/koncert')->getContent());
    }

    public function test_a_foto_nelkuli_kartya_sarok_firkaja_es_a_kapcsoloja(): void
    {
        [$var, , $koncert] = $this->adat();
        $this->program('Kép nélküli program', $var, $koncert, ['2027-08-27']);

        $html = $this->get('/helyszin/var')->getContent();
        $this->assertStringContainsString('class="pc-noimg"', $html);
        $this->assertStringContainsString('<svg class="pc-doodle"', $html);
        $this->assertStringContainsString('<span class="pc-groupname">Koncert</span>', $html);

        // Az Arculat „Fotó nélküli kártyákon” kapcsolója: kikapcsolva a téma CSS-e rejti — most már VAN eleme, amire hat.
        $d = Theme::data();
        $d['doodles']['zones']['noPhotoCards'] = false;
        $this->assertStringContainsString('.pc-doodle{display:none}', ThemeCss::generate($d));
        $d['doodles']['zones']['noPhotoCards'] = true;
        $this->assertStringNotContainsString('.pc-doodle{display:none}', ThemeCss::generate($d));
    }

    public function test_a_terkep_lap_a_rajzolt_terkep_lap_modban(): void
    {
        [$var] = $this->adat();
        $html = $this->get('/terkep')->assertOk()->getContent();
        $this->assertStringContainsString('data-mod="lap" data-helyszin-ut="/helyszin/"', $html);
        $this->assertStringContainsString('data-venue="var"', $html);
        $this->assertStringContainsString('id="pkTerkepAdat"', $html);
        $this->assertStringContainsString('terkeprajz/varosterkep-2000.webp', $html);
        $this->assertFileDoesNotExist(public_path('terkep'), 'a public/terkep mappa elnyelné a /terkep címet');
        $this->assertStringContainsString('data-helyszin-ut="/ro/helyszin/"', $this->get('/ro/terkep')->assertOk()->getContent());
    }

    public function test_a_napi_nezet_a_2026_os_jelolessel_es_az_allando_ful(): void
    {
        [$var, , $koncert] = $this->adat();
        $this->program('Egynapos', $var, $koncert, ['2027-08-27']);
        $this->program('Többnapos kiállítás', $var, $koncert, ['2027-08-26', '2027-08-27']);

        $html = $this->get('/nap/2027-08-27')->assertOk()->getContent();
        $this->assertStringContainsString('<h2 class="section-title">Napi program</h2>', $html);
        $this->assertSame(8, substr_count($html, 'data-day=') + 1, '7 programnap + „Állandó”');
        $this->assertStringContainsString('class="rail-row filterable"', $html);
        $this->assertStringContainsString('<div class="rail-time"><span>18:00</span><small>19:30</small></div>', $html);
        $this->assertStringContainsString('id="venue-filter"', $html);
        $this->assertStringContainsString('<span class="rail-sub">· aug. 27. · péntek</span>', $html);

        $allando = $this->get('/nap/allando')->assertOk()->getContent();
        $this->assertStringContainsString('Többnapos kiállítás', $allando);
        $this->assertStringNotContainsString('Egynapos', $allando);
        $this->get('/nap/2027-09-30')->assertNotFound();
    }

    public function test_a_program_lapja_a_2026_os_jelolessel(): void
    {
        [$var, , $koncert, $szinhaz] = $this->adat();
        $p = $this->program('Nagy koncert', $var, $koncert, ['2027-08-27', '2027-08-28'], ['organizer_names' => 'Szervező Kft.',
            'extra_fields' => ['performer_name' => 'Zenekar', 'duration_min' => 90, 'pre_registration' => true, 'booking_link' => 'nem-link']]);
        $p->extraCategories()->attach($szinhaz->id);

        $html = $this->get('/program/' . $p->slug)->assertOk()->getContent();
        $this->assertStringContainsString('<a class="article-back" href="/programok?program=' . $p->slug . '">', $html);
        $this->assertMatchesRegularExpression('#<nav class="pd-crumb"[^>]*>\s*<a href="/kategoriak">Program</a> › <a href="/kategoria/koncert">Koncert</a> › <a href="/kategoria/szinhaz">#', $html);
        $this->assertStringContainsString('<span class="pd-pill">Koncert</span><span class="pd-pill pd-pill-extra">Színház</span>', $html);
        $this->assertStringContainsString('<p class="lead">Első bekezdés.</p>', $html);
        $this->assertStringContainsString('<dd>Zenekar</dd>', $html);
        $this->assertStringContainsString('<dd>90 perc</dd>', $html);
        $this->assertStringContainsString('<dd>✓</dd>', $html);
        $this->assertStringNotContainsString('nem-link', $html, 'nem http-s link nem jelenik meg (2026: extraItems)');
        $this->assertMatchesRegularExpression('#<button class="pd-ghost" id="pd-ics" type="button" disabled>#', $html);
        $this->assertStringContainsString('2 időpont kerül a naptárba', $html);
    }

    public function test_a_fejlec_keresoje_a_tema_kapcsolojaval_a_programkeresobe_visz(): void
    {
        $this->adat();
        $this->assertStringNotContainsString('class="search"', $this->get('/helyszinek')->getContent());

        $d = Theme::data();
        $d['header']['showSearch'] = true;
        Theme::publish($d, null, 'kereső be');
        $html = $this->get('/helyszinek')->getContent();
        $this->assertMatchesRegularExpression('#<form class="search" role="search" action="/programok" method="get">.*<input type="search" name="q"#s', $html);
        $this->assertStringContainsString('action="/ro/programok"', $this->get('/ro/helyszinek')->getContent());
    }
}
