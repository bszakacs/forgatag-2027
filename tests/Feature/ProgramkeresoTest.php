<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\ProgramItems\Pages\ViewProgramItem;
use App\Models\Category;
use App\Models\Page;
use App\Models\ProgramItem;
use App\Models\User;
use App\Models\Venue;
use App\Support\LapSablon;
use App\Support\ProgramTesztImport;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A programkereső (PROMPT-11): szűrés az adatbázisban, a szűrés a címsorban (egy szűrés = egy cím), JS nélkül is;
 * a hajnali 3-as fesztiválnap; „Most zajlik” és „Mi van ma?”; helyszín-hierarchia; román szöveg magyar tartalékkal;
 * a program lapja; és hogy a publikus oldalt nem szűkíti egy belépett kategóriagazda joga.
 */
class ProgramkeresoTest extends TestCase
{
    use RefreshDatabase;

    private Category $koncert;

    private Category $szinhaz;

    private Venue $var;

    private Venue $borkert;

    protected function setUp(): void
    {
        parent::setUp();
        $seed = json_decode((string) file_get_contents(base_path('seed/settings.json')), true);
        Settings::save(array_intersect_key($seed, array_flip(['festival', 'submission', 'public', 'notifications'])));
        LapSablon::szinkron(); // létrehozza a „programok” rendszerlapot és a szövegeit

        $this->koncert = Category::create(['slug' => 'koncert', 'name_hu' => 'Koncert', 'name_ro' => 'Concert', 'sort' => 1]);
        $this->szinhaz = Category::create(['slug' => 'szinhaz', 'name_hu' => 'Színház', 'sort' => 2]);
        $this->var = Venue::create(['slug' => 'var', 'name' => 'Vár']);
        $this->borkert = Venue::create(['slug' => 'var-borkert', 'name' => 'Vár, Borkert', 'parent_id' => $this->var->id]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** @param list<array{0: string, 1: string, 2?: ?string}> $idopontok [dátum, kezdés, vég] */
    private function program(string $cim, array $idopontok, array $tobb = []): ProgramItem
    {
        $p = ProgramItem::create($tobb + ['title' => $cim, 'description' => "{$cim} leírása", 'status' => 'published',
            'category_id' => $this->koncert->id, 'venue_id' => $this->var->id]);
        foreach ($idopontok as $o) {
            $p->occurrences()->create(['date' => $o[0], 'start_time' => $o[1], 'end_time' => $o[2] ?? null]);
        }

        return $p;
    }

    private function talalatok(string $url): string
    {
        $html = $this->get($url)->assertOk()->getContent();
        preg_match('~<section id="pk-talalatok".*?</section>~s', $html, $m);

        return $m[0] ?? $this->fail('nincs #pk-talalatok a lapon');
    }

    public function test_az_ejfelen_atnyulo_program_a_kezdes_napjahoz_tartozik_es_a_nap_vegere_kerul(): void
    {
        $this->program('Késő esti koncert', [['2027-08-27', '23:00', '00:30']]);
        $this->program('Hajnali táncház', [['2027-08-27', '00:30', '02:00']]);
        $this->program('Délutáni mese', [['2027-08-27', '15:00', '16:00']]);

        foreach (['/programok?nap=2027-08-27', '/nap/2027-08-27'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('~Délutáni mese.*Késő esti koncert.*Hajnali táncház~s', $html, $url);
            $this->assertStringContainsString('23.00', $html);
            $this->assertStringContainsString('–0.30', $html);
            $this->assertStringContainsString('(éjjel)', $html); // a 00:30-as kezdés a nap végén, jelölve
        }
        $this->assertStringNotContainsString('Késő esti koncert', $this->talalatok('/programok?nap=2027-08-28'));
    }

    public function test_a_zaro_orat_a_beallitasok_adjak(): void
    {
        Settings::save(['festival' => ['day_cut_hour' => 5] + Settings::get('festival')]);
        $this->program('Napfelkelte-koncert', [['2027-08-27', '04:30', '05:30']]);
        $this->program('Reggeli torna', [['2027-08-27', '09:00', '10:00']]);

        // 5 órás vágásnál a 04:30 még az előző este része → a nap VÉGÉRE kerül, a 09:00 elé nem.
        $this->assertMatchesRegularExpression('~Reggeli torna.*Napfelkelte-koncert~s', $this->talalatok('/programok?nap=2027-08-27'));
    }

    public function test_a_szures_a_cimsorban_el_es_kulon_megnyitva_ugyanazt_adja(): void
    {
        $this->program('Rockkoncert', [['2027-08-26', '20:00']]);
        $this->program('Vígjáték', [['2027-08-26', '18:00']], ['category_id' => $this->szinhaz->id]);
        $this->program('Másnapi koncert', [['2027-08-27', '20:00']]);
        $tobbes = $this->program('Zenés színház', [['2027-08-26', '17:00']], ['category_id' => $this->szinhaz->id]);
        $tobbes->extraCategories()->attach($this->koncert->id); // a további kategória is számít

        $url = '/programok?nap=2027-08-26&kategoria=koncert';
        $t = $this->talalatok($url);
        $this->assertStringContainsString('Rockkoncert', $t);
        $this->assertStringContainsString('Zenés színház', $t);
        $this->assertStringNotContainsString('Vígjáték', $t);
        $this->assertStringNotContainsString('Másnapi koncert', $t);
        $this->assertSame($t, $this->talalatok($url), 'ugyanaz a cím ugyanazt adja (nincs munkamenet-állapot)');

        // Ismételt kulcs = több kategória (így küldi a JS nélküli űrlap is).
        $this->assertStringContainsString('Vígjáték', $this->talalatok('/programok?nap=2027-08-26&kategoria=koncert&kategoria=szinhaz'));
    }

    public function test_js_nelkul_az_urlap_a_tiszta_cimre_ugrik_htmx_nel_a_cimsorba_a_tiszta_cim_kerul(): void
    {
        $this->program('Rockkoncert', [['2027-08-26', '20:00']]);

        // A JS nélküli GET-űrlap üres mezőket és ismeretlen értéket is küld: egy szűrésnek egy címe van.
        $this->get('/programok?q=&nap=2027-08-26&kategoria=koncert&kategoria=nincs-ilyen&helyszin=')
            ->assertRedirect('/programok?nap=2027-08-26&kategoria=koncert');
        $this->get('/programok?oldal=1')->assertRedirect('/programok');
        $this->get('/programok?nap=2026-08-26')->assertRedirect('/programok'); // nem fesztiválnap

        $this->withHeader('HX-Request', 'true')->get('/programok?q=&nap=2027-08-26&kategoria=koncert')
            ->assertOk()->assertHeader('HX-Push-Url', '/programok?nap=2027-08-26&kategoria=koncert');
    }

    public function test_a_kezdoallapot_a_2026_os_minden_nap_egy_lapon_napszakonkent(): void
    {
        $this->program('Nyitónapi koncert', [['2027-08-25', '20:00']]);
        $this->program('Másnapi reggel', [['2027-08-26', '09:00']]);

        // A fesztivál ELŐTT: a nyitónap előre kiválasztva (nem a lista első, előkészítő napja).
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-01 12:00', 'Europe/Bucharest'));
        $t = $this->talalatok('/programok');
        $this->assertStringContainsString('Nyitónapi koncert', $t);
        $this->assertStringNotContainsString('Másnapi reggel', $t);
        // A fesztivál ALATT: „Most zajlik”; UTÁNA: az utolsó nap.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-08-26 10:00', 'Europe/Bucharest'));
        $this->assertMatchesRegularExpression('~id="pk-nap-most"[^>]*\schecked~', $this->get('/programok')->getContent());
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-09-10 10:00', 'Europe/Bucharest'));
        $this->assertMatchesRegularExpression('~id="pk-nap-2027-08-26"[^>]*\schecked~', $this->get('/programok')->getContent());

        // Minden nap: egy lapon (lapozás nincs, mint 2026-ban), nap → napszak csoportokkal.
        for ($i = 0; $i < 65; $i++) {
            $this->program("Program {$i}", [['2027-08-27', sprintf('%02d:%02d', 10 + intdiv($i, 6), ($i % 6) * 10)]]);
        }
        $t = $this->talalatok('/programok?nap=mind');
        $this->assertStringContainsString('Program 64', $t);
        $this->assertMatchesRegularExpression('~Augusztus 25\., szerda.*Este.*Augusztus 26\., csütörtök.*Délelőtt.*Augusztus 27\., péntek~su', $t);
        $this->get('/programok?oldal=2')->assertRedirect('/programok');
    }

    public function test_napszak_szuro_az_egesz_napos_mindig_atmegy_es_a_most_kizarja(): void
    {
        $this->program('Reggeli torna', [['2027-08-26', '09:00', '10:00']]);
        $this->program('Esti koncert', [['2027-08-26', '20:00', '22:00']]);
        $this->program('Éjféli buli', [['2027-08-26', '00:30', '02:00']]);
        $this->program('Egész napos kiállítás', [['2027-08-26', '10:00', '18:00']]);

        $t = $this->talalatok('/programok?nap=2027-08-26&napszak=este');
        $this->assertStringContainsString('Esti koncert', $t);
        $this->assertStringContainsString('Éjféli buli', $t);           // a vágás előtti kezdés este
        $this->assertStringContainsString('Egész napos kiállítás', $t); // az egész napos minden napszakban
        $this->assertStringNotContainsString('Reggeli torna', $t);
        $this->assertStringContainsString('Reggeli torna', $this->talalatok('/programok?nap=2027-08-26&napszak=delelott&napszak=este'));

        // A „Most” és a napszak kizárja egymást: a címből a napszak kiesik.
        $this->get('/programok?nap=most&napszak=este')->assertRedirect('/programok?nap=most');
    }

    public function test_napi_kiemelt_sav_a_koordinator_sorrendjeben_csak_azon_a_napon(): void
    {
        $a = $this->program('Első kiemelt', [['2027-08-26', '20:00']]);
        $b = $this->program('Második kiemelt', [['2027-08-26', '18:00']]);
        $mas = $this->program('Másik napi', [['2027-08-27', '18:00']]);
        $b->highlights()->create(['date' => '2027-08-26', 'sort' => 1]);
        $a->highlights()->create(['date' => '2027-08-26', 'sort' => 0]);
        $mas->highlights()->create(['date' => '2027-08-26', 'sort' => 2]); // azon a napon nincs időpontja → nem jelenik meg

        $t = $this->talalatok('/programok?nap=2027-08-26');
        $this->assertMatchesRegularExpression('~Kiemelt <span class="brush">programok</span>.*data-rail="pk-kiemelt".*Első kiemelt.*Második kiemelt~s', $t);
        $this->assertDoesNotMatchRegularExpression('~data-rail="pk-kiemelt".*Másik napi~s', $t);
        $this->assertStringNotContainsString('data-rail="pk-kiemelt"', $this->talalatok('/programok?nap=2027-08-27'));
    }

    public function test_a_listabol_felugro_ablak_nyilik_megoszthato_cimmel(): void
    {
        $p = $this->program('Vásári komédia', [['2027-08-26', '18:00', '19:30']],
            ['organizer_names' => 'Teleki Téka', 'extra_fields' => ['performer_name' => 'Kaláka', 'age_group' => '6+']]);
        $idopont = $p->occurrences()->first()->id;

        // A kártya: href a program LAPJA (JS nélkül, keresőoptimalizálás), htmx-szel a felugró ablakot kéri.
        $this->get('/programok?nap=2027-08-26')->assertOk()
            ->assertSee('href="/program/' . $p->slug . '"', false)
            ->assertSee('hx-get="/programok?nap=2027-08-26&amp;program=' . $p->slug . '&amp;idopont=' . $idopont . '"', false);

        // A megosztott cím a keresőt nyitja, rajta az ablakkal.
        $this->get('/programok?nap=2027-08-26&program=' . $p->slug)->assertOk()
            ->assertSee('id="pk-mcim"', false)->assertSee('Augusztus 26., csütörtök · 18.00–19.30')
            ->assertSee('Kaláka')->assertSee('Teleki Téka')->assertSee('6+');

        // htmx: csak az ablak jön vissza, a címsorba a tiszta cím (az időpont nélkül).
        $this->withHeaders(['HX-Request' => 'true', 'HX-Target' => 'pk-modal'])
            ->get('/programok?nap=2027-08-26&program=' . $p->slug . '&idopont=' . $idopont)
            ->assertOk()->assertHeader('HX-Push-Url', '/programok?nap=2027-08-26&program=' . $p->slug)
            ->assertSee('id="pk-modal"', false)->assertDontSee('id="pk-urlap"', false);
        // Ismeretlen program: az ablak paramétere kiesik (JS nélküli kérés — a htmx-fejléceket eldobjuk).
        $this->flushHeaders()->get('/programok?program=nincs-ilyen')->assertRedirect('/programok');
    }

    public function test_ures_talalatnal_a_2026_os_javaslat_melyik_szurot_engedd_el(): void
    {
        $this->program('Rockkoncert', [['2027-08-26', '20:00']]);
        $this->program('Vígjáték', [['2027-08-26', '18:00']], ['category_id' => $this->szinhaz->id]);

        $t = $this->talalatok('/programok?nap=2027-08-26&napszak=delelott&kategoria=koncert');
        $this->assertStringContainsString('Erre a kombinációra nincs program', $t);
        $this->assertStringContainsString('Ha elengeded a napszakot, <b>1 program</b> marad a szűrésedben.', $t);
    }

    public function test_a_napi_kiemelest_a_jovahagyo_allitja_a_program_lapjarol(): void
    {
        $p = $this->program('Nagykoncert', [['2027-08-26', '20:00'], ['2027-08-27', '20:00']]);
        $koord = User::create(['name' => 'Koord', 'email' => 'k@forgatag.test', 'password' => 'hosszu-jelszo-12', 'role' => UserRole::Coordinator]);
        $this->actingAs($koord, 'web');
        Livewire::test(ViewProgramItem::class, ['record' => $p->id])
            ->callAction('kiemeles', ['napok' => ['2027-08-27'], 'sorrend' => 3])->assertNotified('Kiemelve: 1 napon.');
        $this->assertSame([['2027-08-27', 3]], $p->highlights()->get()->map(fn ($h) => [$h->date->format('Y-m-d'), $h->sort])->all());

        $gazda = User::create(['name' => 'Gazda', 'email' => 'g@forgatag.test', 'password' => 'hosszu-jelszo-12', 'role' => UserRole::CategoryOwner]);
        $gazda->categories()->attach($this->koncert->id);
        $this->actingAs($gazda, 'web');
        Livewire::test(ViewProgramItem::class, ['record' => $p->id])->assertActionHidden('kiemeles');
    }

    public function test_a_tesztprogram_behozatal_a_helyszin_nelkulieket_kihagyja(): void
    {
        $csomag = json_decode((string) file_get_contents(base_path(ProgramTesztImport::CSOMAG)), true)['programok'];
        $varban = count(array_filter($csomag, fn ($p) => $p['venue'] === 'var'));
        $this->assertGreaterThan(0, $varban);

        Storage::fake('local'); // a „már behozva” jelölő ne a valódi tárba kerüljön
        (new ProgramTesztImport)->run(fn () => null);

        // Csak a 2027-es listában szereplő helyszínűek jönnek (itt: a „var” és a „var-borkert”), helyszín nélküli egy sem.
        $behozott = ProgramItem::query()->where('is_test', true);
        $this->assertSame(0, (clone $behozott)->whereNull('venue_id')->count());
        $this->assertGreaterThanOrEqual($varban, (clone $behozott)->count());
        $this->assertLessThan(count($csomag), (clone $behozott)->count());
    }

    public function test_a_kereses_a_leirasban_nem_keres(): void
    {
        $this->program('Bábszínház', [['2027-08-26', '10:00']], ['description' => 'Különleges zenei kíséret.']);
        $this->assertStringNotContainsString('Bábszínház', $this->talalatok('/programok?q=zenei'));
        $this->assertStringContainsString('Bábszínház', $this->talalatok('/programok?q=koncert')); // a kategória neve
    }

    public function test_a_szulo_helyszin_a_termeit_is_hozza_es_a_darabszam_a_tobbi_szuro_mellett_szamol(): void
    {
        $this->program('A Borkertben', [['2027-08-26', '20:00']], ['venue_id' => $this->borkert->id]);
        $this->program('A Vár udvarán', [['2027-08-26', '18:00']]);
        $this->program('Színház a Várban', [['2027-08-26', '19:00']], ['category_id' => $this->szinhaz->id]);

        $t = $this->talalatok('/programok?nap=mind&helyszin=var');
        $this->assertStringContainsString('A Borkertben', $t);
        $this->assertStringContainsString('A Vár udvarán', $t);
        $this->assertStringNotContainsString('A Borkertben', $this->talalatok('/programok?nap=mind&kategoria=szinhaz&helyszin=var'));

        // A kategória-lista darabszáma a helyszín-szűrő mellett, a SAJÁT dimenzióját kihagyva.
        $html = $this->get('/programok?nap=mind&kategoria=szinhaz&helyszin=var-borkert')->getContent();
        $this->assertMatchesRegularExpression('~id="pk-o-kat-koncert".*?<span class="pk-n">1</span>~s', $html);
        $this->assertMatchesRegularExpression('~id="pk-o-kat-szinhaz".*?<span class="pk-n">0</span>~s', $html);
    }

    public function test_most_zajlik_es_mi_van_ma_a_hajnali_vagassal(): void
    {
        $this->program('Éjszakai koncert', [['2027-08-27', '23:00', '02:00']]);
        $this->program('Reggeli program', [['2027-08-28', '09:00', '10:00']]);

        // Szombat hajnali 1 óra: még a PÉNTEKI fesztiválnap tart.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-08-28 01:00', 'Europe/Bucharest'));
        $t = $this->talalatok('/programok?nap=most');
        $this->assertStringContainsString('Éjszakai koncert', $t);
        $this->assertStringNotContainsString('Reggeli program', $t);
        $this->get('/ma')->assertRedirect('/nap/2027-08-27');
        $this->get('/ro/ma')->assertRedirect('/ro/nap/2027-08-27');

        // A fesztivál előtt: a „Mi van ma?” a NYITÓNAPRA visz (nem az első, előkészítő napra), a „Most” kimondja, miért üres.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-06-01 12:00', 'Europe/Bucharest'));
        $this->get('/ma')->assertRedirect('/nap/' . Settings::get('festival.opening_day'));
        $this->assertStringContainsString('Most nem zajlik program', $this->talalatok('/programok?nap=most'));
    }

    public function test_keresés_a_cimben_es_a_roman_forditasban(): void
    {
        $p = $this->program('Népzenei est', [['2027-08-26', '20:00']]);
        $p->translations()->create(['field' => 'title', 'translated_text' => 'Seară de muzică populară', 'status' => 'reviewed', 'source_text_hash' => 'x']);
        $this->program('Bábszínház', [['2027-08-26', '10:00']]);

        $this->assertStringContainsString('Népzenei est', $this->talalatok('/programok?q=zenei'));
        $this->assertStringNotContainsString('Bábszínház', $this->talalatok('/programok?q=zenei'));
        // (Ékezet-függetlenség: a MySQL utf8mb4_unicode_ci adja — a tesztek SQLite-ja nem; böngészőben mérve.)
        $this->assertStringContainsString('Népzenei est', $this->talalatok('/programok?q=muzic'));
    }

    public function test_romanul_a_legjobb_forditas_latszik_az_elavult_helyett_a_magyar(): void
    {
        $atnezett = $this->program('Népzenei est', [['2027-08-26', '20:00']]);
        $atnezett->translations()->create(['field' => 'title', 'translated_text' => 'Seară de muzică populară', 'status' => 'reviewed', 'source_text_hash' => 'x']);
        $elavult = $this->program('Bábelőadás', [['2027-08-26', '10:00']]);
        $elavult->translations()->create(['field' => 'title', 'translated_text' => 'Titlu vechi greșit', 'status' => 'outdated', 'source_text_hash' => 'x']);
        $gepi = $this->program('Kiállítás', [['2027-08-26', '11:00']]);
        $gepi->translations()->create(['field' => 'title', 'translated_text' => 'Expoziție', 'status' => 'machine_translated', 'source_text_hash' => 'x']);

        $t = $this->talalatok('/ro/programok?nap=mind');
        $this->assertStringContainsString('Seară de muzică populară', $t);
        $this->assertStringContainsString('Bábelőadás', $t);
        $this->assertStringNotContainsString('Titlu vechi greșit', $t);
        $this->get('/ro/program/' . $gepi->slug)->assertOk()->assertSee('Expoziție')->assertSee('Traducere automată');
        $this->get('/ro/program/' . $atnezett->slug)->assertOk()->assertDontSee('Traducere automată');
        $this->get('/program/' . $gepi->slug)->assertOk()->assertSee('Kiállítás')->assertDontSee('Gépi fordítás');
    }

    public function test_a_program_lapja_csak_kozzetett_programot_mutat(): void
    {
        $p = $this->program('Vásári komédia', [['2027-08-26', '18:00', '19:30'], ['2027-08-27', '18:00', '19:30']],
            ['description' => "Első bekezdés.\n\nJegyek: https://jegy.example/forgatag"]);
        $this->get('/program/' . $p->slug)->assertOk()->assertSee('Vásári komédia')->assertSee('18.00–19.30')
            ->assertSee('<a href="https://jegy.example/forgatag"', false)
            ->assertSee('/programok?helyszin=var', false);

        $vazlat = $this->program('Még nincs kint', [['2027-08-26', '18:00']], ['status' => 'draft']);
        $this->get('/program/' . $vazlat->slug)->assertNotFound();
        $this->assertStringNotContainsString('Még nincs kint', $this->talalatok('/programok?nap=mind'));
    }

    public function test_belepett_kategoriagazda_a_publikus_oldalon_minden_programot_lat(): void
    {
        $this->program('Rockkoncert', [['2027-08-26', '20:00']]);
        $this->program('Vígjáték', [['2027-08-26', '18:00']], ['category_id' => $this->szinhaz->id]);
        $gazda = User::factory()->create(['role' => UserRole::CategoryOwner]);
        $gazda->categories()->attach($this->koncert->id);

        $t = $this->actingAs($gazda, 'web')->get('/programok?nap=mind')->assertOk()->getContent();
        $this->assertStringContainsString('Vígjáték', $t);
    }

    public function test_nincs_program_eseten_kimondja_es_a_lapon_nincs_beegetett_szoveg(): void
    {
        $this->assertStringContainsString('A program készül', $this->talalatok('/programok'));

        // A lap szövegei kulcsok: a sablon minden kérése le van írva a LapSablon-ban.
        foreach (['index', '_talalatok', 'nap'] as $f) {
            preg_match_all('/\$sz->szoveg\(\'([a-z0-9_]+)\'\)/', (string) file_get_contents(resource_path("views/programok/{$f}.blade.php")), $m);
            foreach ($m[1] as $kulcs) {
                $this->assertArrayHasKey($kulcs, LapSablon::mezok('programok'), "{$f}: {$kulcs}");
            }
        }
        $this->assertTrue(Page::query()->where('slug', 'programok')->value('is_system'));
    }
}
