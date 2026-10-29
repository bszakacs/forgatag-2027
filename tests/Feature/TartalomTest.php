<?php

namespace Tests\Feature;

use App\Enums\ProgramSource;
use App\Enums\ReviewAction;
use App\Enums\UserRole;
use App\Filament\Pages\Menuk;
use App\Filament\Widgets\Figyelmeztetesek;
use App\Models\Category;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\ProgramItem;
use App\Models\User;
use App\Models\Venue;
use App\Services\ProgramReview;
use App\Support\MenuTree;
use App\Support\SubmissionForm;
use App\Support\TartalomImport;
use App\Support\Tesztadat;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * A tartalmi gyűjtemények (PROMPT-08): menüfa (saját szerkesztő), aloldalak, hírek, tesztadat egy lépésben,
 * a 2026-os behozatal, jog (kategóriagazda a közvetlen címen is kívül marad), és az 5. adósság őre.
 */
class TartalomTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Filament::setCurrentPanel('admin');
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@forgatag.test', 'password' => 'hosszu-jelszo-12', 'role' => UserRole::SuperAdmin]);
    }

    private function menu(): Menu
    {
        return Menu::create(['slug' => 'main', 'title_hu' => 'Főmenü', 'sort' => 1]);
    }

    private function pont(Menu $m, string $cim, ?MenuItem $szulo = null, int $sort = 1): MenuItem
    {
        return MenuItem::create(['menu_id' => $m->id, 'parent_id' => $szulo?->id, 'sort' => $sort, 'label_hu' => $cim, 'type' => 'link', 'url' => '/' . $cim]);
    }

    // ---- 5. adósság: a beküldő űrlap kategória-hivatkozásai ----

    public function test_az_urlap_leirasa_csak_letezo_kategoriara_hivatkozik(): void
    {
        $h = fopen(base_path('seed/categories.csv'), 'r');
        fgetcsv($h, escape: '');
        $seed = [];
        while (($sor = fgetcsv($h, escape: '')) !== false) {
            $seed[] = $sor[0];
        }
        fclose($h);

        $this->assertCount(23, $seed, 'A 2026-os végleges lista 23 kategória.');
        $this->assertSame([], array_values(array_diff(array_keys(SubmissionForm::extraFieldsBySlug()), $seed)),
            'Az extra mezők leírása nem létező kategória-slugra hivatkozik.');
    }

    public function test_nem_letezo_slug_nem_marad_nema_a_nyitolapon_latszik(): void
    {
        Category::create(['slug' => 'koncertforgatag', 'name_hu' => 'Koncertforgatag']);
        $this->assertContains('duma-ter', SubmissionForm::unknownSlugs());
        $this->assertNotContains('koncertforgatag', SubmissionForm::unknownSlugs());

        $this->actingAs($this->admin);
        $this->assertTrue(Figyelmeztetesek::canView());
        $this->get('/admin')->assertOk()->assertSee('A beküldő űrlap nem létező kategóriára hivatkozik')->assertSee('duma-ter');
    }

    // ---- Menüfa ----

    public function test_menufa_mentese_es_melysegkorlat(): void
    {
        $m = $this->menu();
        $a = $this->pont($m, 'a', null, 1);
        $b = $this->pont($m, 'b', null, 2);
        $c = $this->pont($m, 'c', null, 3);

        MenuTree::save($m, [['id' => $c->id, 'children' => [['id' => $a->id, 'children' => []]]], ['id' => $b->id, 'children' => []]]);
        $this->assertSame([['id' => $c->id, 'children' => [['id' => $a->id, 'children' => []]]], ['id' => $b->id, 'children' => []]], MenuTree::tree($m));

        // Harmadik szint: elutasítva, és semmi nem íródik ki félig.
        try {
            MenuTree::save($m, [['id' => $c->id, 'children' => [['id' => $a->id, 'children' => [['id' => $b->id]]]]]]);
            $this->fail('A 3. szintet el kellett volna utasítani.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('legfeljebb 2 szintű', $e->getMessage());
        }
        $this->assertNull($b->fresh()->parent_id);

        // Hiányzó / idegen menüpont: elutasítva.
        $idegen = $this->pont(Menu::create(['slug' => 'archive', 'title_hu' => 'Archívum']), 'x');
        $this->expectException(InvalidArgumentException::class);
        MenuTree::save($m, [['id' => $idegen->id], ['id' => $a->id], ['id' => $b->id], ['id' => $c->id]]);
    }

    public function test_nyil_gombok_fel_le_beljebb_kijjebb(): void
    {
        $m = $this->menu();
        $a = $this->pont($m, 'a', null, 1);
        $b = $this->pont($m, 'b', null, 2);
        $c = $this->pont($m, 'c', null, 3);
        $ids = fn () => MenuTree::tree($m);

        MenuTree::move($m, $c->id, 'fel');            // a c b
        MenuTree::move($m, $b->id, 'beljebb');        // a c[b]
        $this->assertSame([['id' => $a->id, 'children' => []], ['id' => $c->id, 'children' => [['id' => $b->id, 'children' => []]]]], $ids());
        MenuTree::move($m, $b->id, 'kijjebb');        // a c b
        $this->assertSame([$a->id, $c->id, $b->id], array_column($ids(), 'id'));
        MenuTree::move($m, $a->id, 'le');             // c a b
        $this->assertSame([$c->id, $a->id, $b->id], array_column($ids(), 'id'));

        $this->expectException(InvalidArgumentException::class);
        MenuTree::move($m, $c->id, 'fel'); // már az első
    }

    public function test_menu_szerkeszto_nyelvenkenti_elrejtes_es_huzas(): void
    {
        $m = $this->menu();
        $a = $this->pont($m, 'a', null, 1);
        $b = $this->pont($m, 'b', null, 2);
        $this->actingAs($this->admin);

        Livewire::test(Menuk::class)
            ->assertSee('a')->assertSee('b')
            ->call('nyelv', $a->id, 'ro')
            ->call('mentFa', [['id' => $b->id, 'children' => [['id' => $a->id, 'children' => []]]]]);

        $a->refresh();
        $this->assertFalse($a->show_ro);
        $this->assertTrue($a->show_hu);
        $this->assertSame($b->id, $a->parent_id);

        // Hibás fa: emberi üzenet, nem kivétel a felhasználó arcába.
        Livewire::test(Menuk::class)->call('mentFa', [['id' => 999999]])->assertNotified();
    }

    // ---- Aloldalak ----

    public function test_rendszerlap_nem_torolheto_es_a_slugja_nem_irhato_at(): void
    {
        $lap = new Page(['slug' => 'galeria', 'title_hu' => 'Galéria', 'status' => 'published']);
        $lap->is_system = true;
        $lap->save();

        $this->assertFalse($this->admin->can('delete', $lap));
        $this->expectException(RuntimeException::class);
        $lap->update(['slug' => 'kepek']);
    }

    // ---- Tesztadat ----

    public function test_tesztadat_egy_lepesben_torolheto_a_valodi_marad(): void
    {
        $valodi = NewsItem::create(['title_hu' => 'Valódi hír', 'status' => 'published', 'published_on' => '2027-01-01']);
        $teszt = new NewsItem(['title_hu' => 'Tavalyi hír', 'status' => 'published', 'published_on' => '2026-08-01']);
        $teszt->is_test = true;
        $teszt->save();

        $kat = Category::create(['slug' => 'borkert', 'name_hu' => 'Borkert']);
        $var = Venue::create(['slug' => 'var', 'name' => 'Vár']);
        Storage::disk('local')->put('bekuldesek/t.jpg', 'KEP');
        $tp = ProgramItem::create(['title' => 'Teszt program', 'description' => 'x', 'category_id' => $kat->id, 'venue_id' => $var->id,
            'source' => ProgramSource::PublicForm, 'photo_path' => 'bekuldesek/t.jpg']);
        $tp->is_test = true;
        $tp->save();
        $tp->occurrences()->create(['date' => '2027-08-26', 'start_time' => '18:00']);
        ProgramReview::log($tp, ReviewAction::Submitted); // append-only napló — a törlés ne akadjon el rajta
        $vp = ProgramItem::create(['title' => 'Valódi program', 'description' => 'x', 'category_id' => $kat->id, 'venue_id' => $var->id,
            'source' => ProgramSource::PublicForm]);

        $this->assertSame(['hír' => 1, 'program' => 1], Tesztadat::counts());
        $this->actingAs($this->admin);
        $this->get('/admin')->assertSee('Tesztadat van az adatbázisban (2 db)');

        $this->assertSame(['hír' => 1, 'program' => 1], Tesztadat::purge());
        $this->assertSame(0, Tesztadat::total());
        $this->assertModelExists($valodi);
        $this->assertModelExists($vp);
        $this->assertModelMissing($teszt);
        $this->assertSame(0, ProgramItem::withoutGlobalScopes()->whereKey($tp->id)->count());
        Storage::disk('local')->assertMissing('bekuldesek/t.jpg');
        $this->get('/admin')->assertDontSee('Tesztadat van az adatbázisban');
    }

    // ---- A 2026-os behozatal ----

    public function test_a_2026_os_tartalom_behozatala_kepekkel_es_egyszer(): void
    {
        $csomag = json_decode(file_get_contents(base_path(TartalomImport::LEIRAS)), true);
        $this->assertSame(['menü' => 2, 'menüpont' => 21, 'lap' => 13], array_intersect_key($csomag['szamok'], array_flip(['menü', 'menüpont', 'lap'])));
        $this->assertSame(26, $csomag['szamok']['videó']);
        $this->assertGreaterThanOrEqual(3, $csomag['szamok']['teszthír']);
        $this->assertLessThanOrEqual(5, $csomag['szamok']['teszthír']);

        // Hiányzó kép: semmi nem íródik.
        try {
            (new TartalomImport)->run(fn () => null);
            $this->fail('Hiányzó képnél hibával kellett volna megállnia.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('nincs a médiatárban', $e->getMessage());
        }
        $this->assertSame(0, Page::count());

        // A 2026-os képek „a médiatárban” (legacy_id-vel), mint a médiatár-behozatal után.
        $kell = collect($csomag['lapok'])->flatMap(fn ($l) => [$l['hero'], ...array_column($l['szovegek'], 'kep')])
            ->merge(array_column($csomag['hirek'], 'cover'))->filter()->unique();
        foreach ($kell as $legacy) {
            Media::create(['disk' => 'mediatar', 'name' => $legacy, 'path' => "{$legacy}.jpg", 'type' => 'image/jpeg', 'ext' => 'jpg',
                'origin' => 'import2026', 'legacy_id' => $legacy]);
        }

        $eredmeny = (new TartalomImport)->run(fn () => null);
        $this->assertSame(['lap' => 13, 'menüpont' => 21, 'teszthír' => $csomag['szamok']['teszthír']], $eredmeny);
        $this->assertSame(2, Menu::count());
        $this->assertSame(16, \App\Models\PageText::whereNotNull('media_id')->count());
        $video = Page::where('slug', 'videoarchivum')->first()->blocks;
        $this->assertSame(26, $video->sum(fn ($b) => count($b->videos)));
        $this->assertFalse($video->firstWhere('title_hu', 'Vásárhely színe-java')->show_ro, 'A 2026-ban csak magyarul látszó videócsoport román oldalon rejtett.');
        $hirek = MenuItem::where('label_hu', 'Hírek')->first();
        $this->assertTrue($hirek->show_hu);
        $this->assertFalse($hirek->show_ro);
        $this->assertSame(2, MenuItem::whereNull('parent_id')->where('type', 'group')->count(), 'Programok, Médiatár: csak almenü-fejléc.');
        $this->assertSame($csomag['szamok']['teszthír'], NewsItem::where('is_test', true)->count());

        // A használt kép nem törölhető (a hír borítója, az aloldal képe).
        $borito = NewsItem::first()->cover;
        $this->assertNotNull($borito->torlesAkadaly());

        // Tesztadat törlése után egy újabb telepítés sem hozza vissza a teszthíreket.
        Tesztadat::purge();
        (new TartalomImport)->run(fn () => null);
        $this->assertSame(0, NewsItem::count());
    }

    // ---- A szövegszerkesztő (WYSIWYG, HTML-ben tárol) ----

    public function test_markdown_atalakitas_html_re_biztonsagosan_es_ismetelhetoen(): void
    {
        $html = \App\Support\TartalomHtml::markdownbol("Első **bekezdés**,\nfolytatás.\n\n- egy\n- kettő\n\n[rossz](javascript:alert(1)) <script>x()</script>");
        $this->assertStringContainsString('<p>Első <strong>bekezdés</strong>,', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        // Ami már HTML, ahhoz nem nyúl (a migráció és a behozatal ismételhető).
        $this->assertSame($html, \App\Support\TartalomHtml::markdownbol($html));
        $this->assertNull(\App\Support\TartalomHtml::markdownbol('  '));
    }

    public function test_hir_szerkeszto_wysiwyg_html_gombbal(): void
    {
        $hir = NewsItem::create(['title_hu' => 'Hír', 'status' => 'draft', 'published_on' => '2027-01-10', 'body_hu' => '<p>Régi szöveg</p>']);
        $this->actingAs($this->admin);

        Livewire::test(\App\Filament\Resources\NewsItems\Pages\EditNewsItem::class, ['record' => $hir->getRouteKey()])
            ->assertFormFieldExists('body_hu')
            ->assertSeeHtml('rich-editor')
            ->fillForm(['body_hu' => '<p>Új <strong>szöveg</strong></p>'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertStringContainsString('<strong>szöveg</strong>', $hir->fresh()->body_hu);
    }

    // ---- Jog: a kategóriagazda kívül marad (4. adósság: közvetlen címmel is) ----

    public function test_kategoriagazda_nem_fer_a_tartalomhoz_kozvetlen_cimmel_sem(): void
    {
        $hir = NewsItem::create(['title_hu' => 'Hír', 'status' => 'draft']);
        $lap = Page::create(['slug' => 'proba', 'title_hu' => 'Próba', 'status' => 'draft']);
        $kg = User::create(['name' => 'Kategóriagazda', 'email' => 'kg@forgatag.test', 'password' => 'hosszu-jelszo-12', 'role' => UserRole::CategoryOwner]);
        $kg->categories()->attach(Category::create(['slug' => 'borkert', 'name_hu' => 'Borkert']));
        $this->actingAs($kg);

        foreach (['/admin/hirek', '/admin/hirek/create', "/admin/hirek/{$hir->id}/edit", '/admin/aloldalak', "/admin/aloldalak/{$lap->id}/edit", '/admin/menu'] as $cim) {
            $this->get($cim)->assertForbidden();
        }
        // A nyitólap figyelmeztetéseit sem látja (nem tud tenni ellenük).
        $this->assertFalse(Figyelmeztetesek::canView());
    }

    public function test_koordinator_eleri_a_tartalom_lapjait(): void
    {
        $k = User::create(['name' => 'Koord', 'email' => 'k@forgatag.test', 'password' => 'hosszu-jelszo-12', 'role' => UserRole::Coordinator]);
        $this->actingAs($k);
        foreach (['/admin/hirek', '/admin/hirek/create', '/admin/aloldalak', '/admin/aloldalak/create', '/admin/menu', '/admin/galeriak', '/admin/tamogatok'] as $cim) {
            $this->get($cim)->assertOk();
        }
    }
}
