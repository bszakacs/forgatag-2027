<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Aloldalak\Pages\EditPage;
use App\Http\Controllers\Publikus\HirekController;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\PageText;
use App\Models\User;
use App\Support\HtmlSzuro;
use App\Support\LapSablon;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A publikus váz (PROMPT-09): keret + menü az adatbázisból, Rólunk (minden szöveg szerkeszthető), hírek htmx-
 * lapozással (JavaScript nélkül is), két nyelv magyar tartalékkal, Hamarosan mód, és hogy a Filament CSS-e
 * nem kerül a publikus lapra.
 */
class PublikusVazTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $seed = json_decode((string) file_get_contents(base_path('seed/settings.json')), true);
        Settings::save(array_intersect_key($seed, array_flip(['festival', 'submission', 'public', 'notifications'])));
        foreach (['rolunk' => 'Rólunk', 'hirek' => 'Hírek'] as $slug => $cim) {
            $p = new Page(['slug' => $slug, 'title_hu' => $cim, 'status' => 'published']);
            $p->is_system = true;
            $p->save();
        }
        // A kezdőképek a hamis médiatár-tárba kerülnek (TestCase: Storage::fake('mediatar')).
        LapSablon::szinkron();
    }

    private function hir(int $i, array $tobb = []): NewsItem
    {
        return NewsItem::create($tobb + ['title_hu' => "Hír {$i}", 'excerpt_hu' => "Kivonat {$i}", 'body_hu' => "<p>Szöveg {$i}</p>",
            'status' => 'published', 'published_on' => now()->subDays($i)->toDateString()]);
    }

    private function szoveg(string $kulcs): PageText
    {
        return PageText::query()->where('key', $kulcs)->firstOrFail();
    }

    // ---- Szerkeszthető szövegek (9. adósság) ----

    public function test_a_rolunk_minden_szovege_az_adatbazisbol_jon(): void
    {
        $this->get('/rolunk')->assertOk()->assertSee('Vannak hagyományok, amelyeket örököl az ember');

        $this->szoveg('about_story_p1')->update(['value_hu' => 'Átírt bevezető az adminból.']);
        $this->szoveg('about_related_url2')->update(['value_hu' => 'https://pelda.forgatag.test/']);
        $this->get('/rolunk')->assertOk()->assertSee('Átírt bevezető az adminból.')
            ->assertDontSee('Vannak hagyományok, amelyeket örököl az ember')
            ->assertSee('https://pelda.forgatag.test/', false);
    }

    public function test_a_sablon_minden_kulcsa_le_van_irva_es_nincs_beegetett_szoveg_a_lapon(): void
    {
        $sablon = (string) file_get_contents(resource_path('views/oldal/rolunk.blade.php'));
        preg_match_all('/\$sz->(?:szoveg|kep|link)\(\'([a-z0-9_]+)\'\)/', $sablon, $m);
        preg_match_all('/\$sz->(?:szoveg|kep|link)\("([a-z0-9_]+)\{\$i\}"\)/', $sablon, $m2);
        $leirt = array_keys(LapSablon::mezok('rolunk'));
        foreach ($m[1] as $kulcs) {
            $this->assertContains($kulcs, $leirt, "A sablon a(z) {$kulcs} kulcsot kéri, de nincs a LapSablon-ban.");
        }
        $this->assertNotEmpty($m2[1], 'A számozott kulcsok mintája nem talált semmit — a teszt elavult.');

        // Ami a HTML-ben szöveg, az az adatbázisból jön: minden szöveges érték kicserélve → a régi sehol sem marad.
        PageText::query()->whereNotNull('value_hu')->get()->each(fn (PageText $t) => $t->update(['value_hu' => "X{$t->id}X"]));
        $html = $this->get('/rolunk')->assertOk()->getContent();
        $main = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) preg_replace('#.*<main id="main">|</main>.*#s', '', $html))));
        $maradek = trim((string) preg_replace('/X\d+X|×/u', '', $main));
        $this->assertSame('', trim(preg_replace('/[\s–\-·()]+/u', '', $maradek)), "Beégetett szöveg maradt a lapon: {$maradek}");
    }

    public function test_hianyzo_kulcsnal_a_lap_hibaval_all_meg_nincs_csendes_tartalek(): void
    {
        $this->szoveg('about_hero_line1')->delete();
        $this->withoutExceptionHandling();
        $this->expectExceptionMessage('about_hero_line1');
        $this->get('/rolunk');
    }

    public function test_a_szinkron_ismetelheto_kitoltott_erteket_nem_ir_felul_es_a_holt_kulcsot_torli(): void
    {
        $lap = Page::query()->where('slug', 'rolunk')->first();
        $this->szoveg('about_story_p2')->update(['value_hu' => 'Saját szöveg']);
        PageText::create(['page_id' => $lap->id, 'key' => 'about_regi_kulcs', 'value_hu' => 'holt']);
        $db = PageText::count();

        LapSablon::szinkron();

        $this->assertSame('Saját szöveg', $this->szoveg('about_story_p2')->value_hu);
        $this->assertNull(PageText::query()->where('key', 'about_regi_kulcs')->first());
        $this->assertSame($db - 1, PageText::count());
        $this->assertNotNull($this->szoveg('about_organizer_logo1')->media_id, 'A kezdőkép a médiatárba került.');
    }

    public function test_az_admin_a_kulcs_helyett_emberi_feliratot_mutat(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@forgatag.test', 'password' => 'hosszu-jelszo-12', 'role' => UserRole::SuperAdmin]);
        $this->actingAs($admin);
        Livewire::test(EditPage::class, ['record' => Page::query()->where('slug', 'rolunk')->first()->getRouteKey()])
            ->assertOk()->assertSee('Történet — bevezető bekezdés')->assertSee('Kapcsolódók — webshop címe');
    }

    // ---- Két nyelv ----

    public function test_romanul_a_hianyzo_forditas_helyen_a_magyar_latszik_a_csoport_egyutt_esik_vissza(): void
    {
        $this->szoveg('about_story_p1')->update(['value_ro' => null]);
        $this->szoveg('about_related_title')->update(['value_ro' => 'Familia Vâltorii']);
        $this->szoveg('about_related_title_accent')->update(['value_ro' => null, 'value_hu' => 'KIEMELT-HU']);

        $this->get('/ro/rolunk')->assertOk()
            ->assertSee('Vannak hagyományok, amelyeket örököl az ember') // fordítás nélkül: magyar
            ->assertSee('Familia Vâltorii')
            ->assertDontSee('KIEMELT-HU') // a cím le van fordítva → a szándékosan üres rész üres marad
            ->assertSee('<html lang="ro"', false);
    }

    public function test_a_menu_az_adatbazisbol_jon_nyelvenkent_rejtve(): void
    {
        $m = Menu::create(['slug' => 'main', 'title_hu' => 'Főmenü', 'sort' => 1]);
        MenuItem::create(['menu_id' => $m->id, 'sort' => 1, 'label_hu' => 'Csak-magyar-pont', 'type' => 'link', 'url' => '/valami', 'show_ro' => false]);
        $sz = MenuItem::create(['menu_id' => $m->id, 'sort' => 2, 'label_hu' => 'Szülő', 'label_ro' => 'Părinte', 'type' => 'link', 'url' => '/szulo']);
        MenuItem::create(['menu_id' => $m->id, 'parent_id' => $sz->id, 'sort' => 1, 'label_hu' => 'Gyerek-pont', 'type' => 'link', 'url' => '/gyerek']);

        $this->get('/rolunk')->assertSee('Csak-magyar-pont')->assertSee('Szülő')->assertSee('href="/gyerek"', false);
        $this->get('/ro/rolunk')->assertDontSee('Csak-magyar-pont')->assertSee('Părinte')
            ->assertSee('Gyerek-pont') // nincs román felirat → magyar
            ->assertSee('href="/ro/gyerek"', false);
    }

    // ---- Hírek + htmx-lapozás ----

    public function test_a_lapozas_a_cimsorban_van_es_javascript_nelkul_is_mukodik(): void
    {
        for ($i = 1; $i <= HirekController::LAPMERET + 2; $i++) {
            $this->hir($i);
        }

        $elso = $this->get('/hirek')->assertOk();
        $this->assertSame(HirekController::LAPMERET, substr_count($elso->getContent(), 'class="ncard"'));
        // Rendes link (JS nélkül rendes oldalbetöltés) + htmx attribútumként, saját JavaScript nélkül.
        $elso->assertSee('href="/hirek?oldal=2"', false)->assertSee('hx-boost="true"', false)->assertSee('hx-select="#hirlista"', false);

        $masodik = $this->get('/hirek?oldal=2')->assertOk()
            ->assertSee('<title>Hírek – 2. oldal', false)
            ->assertSee('<link rel="canonical" href="' . url('/hirek?oldal=2') . '">', false);
        $this->assertSame(2, substr_count($masodik->getContent(), 'class="ncard"'));

        // htmx-kérésre is a teljes lap jön (a hx-select emeli ki a listát; a „vissza” gomb is ezt kapja).
        $this->get('/hirek?oldal=2', ['HX-Request' => 'true'])->assertOk()->assertSee('id="hirlista"', false)->assertSee('<title>', false);

        $this->get('/hirek?oldal=3')->assertNotFound();
        $this->get('/hirek?oldal=1')->assertRedirect('/hirek');
        $this->get('/hirek?oldal=abc')->assertRedirect('/hirek');
    }

    public function test_csak_a_kozzetett_es_mar_megjelent_hir_latszik(): void
    {
        $this->hir(1, ['title_hu' => 'Látható hír']);
        $this->hir(2, ['title_hu' => 'Vázlat hír', 'status' => 'draft']);
        $this->hir(3, ['title_hu' => 'Jövőbeli hír', 'published_on' => now()->addDays(3)->toDateString()]);

        $this->get('/hirek')->assertSee('Látható hír')->assertDontSee('Vázlat hír')->assertDontSee('Jövőbeli hír');
        $this->get('/hirek/vazlat-hir')->assertNotFound();
        $this->get('/hirek/latható-hir')->assertNotFound();
        $this->get('/hirek/lathato-hir')->assertOk()->assertSee('Szöveg 1');
    }

    public function test_a_hir_html_je_szurve_jelenik_meg_es_romanul_a_magyarra_esik_vissza(): void
    {
        $this->hir(1, ['title_hu' => 'Veszélyes', 'body_hu' => '<p onclick="x()">Rendes <strong>szöveg</strong></p><script>alert(1)</script><a href="javascript:alert(1)">link</a>',
            'body_ro' => '<p></p>']);

        $html = $this->get('/ro/hirek/veszelyes')->assertOk()->getContent();
        $this->assertStringContainsString('Rendes <strong>szöveg</strong>', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertSame('', HtmlSzuro::tisztit('<iframe src="https://rossz.test"></iframe>'));
    }

    // ---- A Filament CSS-e nincs a publikus lapon ----

    public function test_a_publikus_lapon_nincs_filament_es_tailwind_css(): void
    {
        $this->hir(1);
        foreach (['/rolunk', '/ro/rolunk', '/hirek', '/hirek/hir-1', '/hamarosan'] as $ut) {
            $html = $this->get($ut)->assertOk()->getContent();
            preg_match_all('/<(?:link|script)[^>]+(?:href|src)="([^"]+)"/', $html, $m);
            foreach ($m[1] as $forras) {
                $this->assertDoesNotMatchRegularExpression('#filament|admin-tema|tailwind|/build/|livewire#i', $forras, "{$ut}: {$forras}");
            }
            $this->assertStringContainsString('/arculat/tema-', $html, "{$ut}: a téma-CSS hiányzik");
        }
    }

    // ---- Hamarosan mód ----

    public function test_a_hamarosan_mod_a_publikus_lapokat_takarja_az_admint_es_a_bekuldest_nem(): void
    {
        Settings::save(['public' => ['coming_soon' => true] + Settings::block('public')]);

        $this->get('/rolunk')->assertOk()->assertSee('Készül az új honlapunk')->assertDontSee('Vannak hagyományok');
        $this->get('/ro/hirek')->assertOk()->assertSee('În curând');
        $this->get('/bekuldes/belepes')->assertOk()->assertDontSee('Készül az új honlapunk');
        $this->get('/admin/login')->assertOk();

        // A belépett admin a valódi lapot látja, figyelmeztető sávval.
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@forgatag.test', 'password' => 'hosszu-jelszo-12', 'role' => UserRole::SuperAdmin]);
        $this->actingAs($admin)->get('/rolunk')->assertOk()->assertSee('Vannak hagyományok')->assertSee('class="admin-sav"', false);
    }

    public function test_kikapcsolt_modban_csak_a_gyoker_hamarosan_amig_nincs_kezdolap(): void
    {
        $this->get('/')->assertOk()->assertSee('Hamarosan');
        $this->get('/rolunk')->assertOk()->assertSee('Vannak hagyományok');
    }

    public function test_a_ket_nyelvi_fajl_kulcsai_egyeznek(): void
    {
        $this->assertSame(array_keys(require lang_path('hu/publikus.php')), array_keys(require lang_path('ro/publikus.php')));
    }
}
