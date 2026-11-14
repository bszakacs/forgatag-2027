<?php

namespace Tests\Feature;

use App\Models\Craftsperson;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageText;
use App\Models\User;
use App\Support\KezmuvesImport;
use App\Support\LapSablon;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A maradék publikus lapok (PROMPT-17): a 2026-os jelölés betűre, és MINDEN látható szöveg kulcs (9. adósság).
 * A próba a tulajdonosé: az admin felől minden szöveg kicserélve → a lapon nem maradhat változatlan szó.
 */
class MaradekLapokTest extends TestCase
{
    use RefreshDatabase;

    /** A lapok, amelyek ebben a körben épültek: slug → a sablon fájlja. */
    private const LAPOK = ['fesztivalrend' => 'Fesztiválrend', 'kezmuvesek' => 'Kézműves vásár'];

    protected function setUp(): void
    {
        parent::setUp();
        $seed = json_decode((string) file_get_contents(base_path('seed/settings.json')), true);
        Settings::save(array_intersect_key($seed, array_flip(['festival', 'submission', 'public', 'notifications'])));
        foreach (self::LAPOK as $slug => $cim) {
            $p = new Page(['slug' => $slug, 'title_hu' => $cim, 'title_ro' => $cim . ' RO', 'status' => 'published']);
            $p->is_system = true;
            $p->save();
        }
        LapSablon::szinkron();
    }

    /** A <main> látható szövege: az ikonok rajza (svg) nem szöveg. */
    private function lathatoSzoveg(string $html): string
    {
        $main = (string) preg_replace('#.*<main id="main"[^>]*>|</main>.*#s', '', $html);
        $main = (string) preg_replace('#<svg\b.*?</svg>#s', '', $main);

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($main))));
    }

    public function test_a_sablon_es_a_leiras_kulcsai_pontosan_egyeznek(): void
    {
        foreach (array_keys(self::LAPOK) as $slug) {
            $sablon = (string) file_get_contents(resource_path("views/oldal/{$slug}.blade.php"));
            preg_match_all('/\$sz->(?:szoveg|kep|link)\(\'([a-z0-9_]+)\'\)/', $sablon, $m);
            $leirt = array_keys(LapSablon::mezok($slug));
            $this->assertSame([], array_values(array_diff($m[1], $leirt)), "{$slug}: a sablon olyan kulcsot kér, ami nincs leírva.");
            $this->assertSame([], array_values(array_diff($leirt, $m[1])), "{$slug}: leírt, de a lapon nem használt kulcs (holt adat).");
            $this->assertSame(count($m[1]), count(array_unique($m[1])), "{$slug}: egy kulcs kétszer a lapon.");
        }
    }

    public function test_minden_szoveg_kicserelve_semmi_nem_marad_magyarul_es_romanul(): void
    {
        // A kézműves-lap kártyája a Kézművesek adatából épül — a neve és a bemutatkozása is „kicserélt” szöveg.
        Craftsperson::create(['name' => 'X1X', 'description_hu' => 'X2X', 'description_ro' => 'R2R']);
        foreach (array_keys(self::LAPOK) as $slug) {
            $lap = Page::query()->where('slug', $slug)->firstOrFail();
            PageText::query()->where('page_id', $lap->id)->get()
                ->each(fn (PageText $t) => $t->update(['value_hu' => "X{$t->id}X", 'value_ro' => "R{$t->id}R"]));
            foreach (['' => '/X\d+X/', '/ro' => '/R\d+R|X1X/'] as $elotag => $minta) { // a név nyelvfüggetlen
                $szoveg = $this->lathatoSzoveg($this->get("{$elotag}/{$slug}")->assertOk()->getContent());
                $this->assertNotSame('', $szoveg);
                $maradek = trim((string) preg_replace('/[\s–\-·()!?.,]+/u', '', (string) preg_replace($minta, '', $szoveg)));
                $this->assertSame('', $maradek, "{$elotag}/{$slug}: beégetett szöveg maradt a lapon: {$maradek}");
            }
        }
    }

    public function test_a_fesztivalrend_2026_os_szovegei_es_jelolese(): void
    {
        $hu = $this->get('/fesztivalrend')->assertOk();
        // 2026-ban beégetett (most kulcs) és 2026-ban is szerkeszthető szöveg egyaránt az adatbázisból
        $hu->assertSee('Mit ne hozz a Forgatagra?')->assertSee('Megközelítés és parkolás')->assertSee('Gyors tudnivalók')
            ->assertSee('2027. augusztus 25–29.')->assertDontSee('2026. augusztus');
        // a 2026-os jelölés: 8 lenyíló, a nyitókép firka-rétege a téma kapcsolóinak jelölésével, a közös motívumkészlet
        $html = $hu->getContent();
        $this->assertSame(8, substr_count($html, 'class="fr-acc-head"'));
        $this->assertStringContainsString('id="frHeroDecor" data-decor="hero"', $html);
        $this->assertStringContainsString('class="fg-motivumok"', $html);
        $this->assertStringContainsString('<span class="fr-emergency">112-es</span>', $html);

        // románul a 2026-os román törzs szövege — magyar betű és a hibás „Vâltoarei” alak nélkül
        $ro = $this->lathatoSzoveg($this->get('/ro/fesztivalrend')->assertOk()->getContent());
        $this->assertStringContainsString('Ce să nu aduci la Vâltoare?', $ro);
        $this->assertDoesNotMatchRegularExpression('/[őűŐŰ]/u', $ro, 'Magyar szó maradt a román lapon.');
        $this->assertStringNotContainsString('Vâltoarei', $ro);
    }

    // ---- Kézműves vásár ----

    public function test_a_kezmuves_lap_a_2026_os_jelolessel_lapoz_es_a_rejtett_kezmuves_nem_latszik(): void
    {
        foreach (range(1, 20) as $i) {
            Craftsperson::create(['name' => sprintf('Kézműves %02d', $i), 'description_hu' => "Első bekezdés {$i}.\nMásodik bekezdés {$i}.",
                'description_ro' => $i === 1 ? "Prezentare {$i}." : null, 'sort' => $i]);
        }
        Craftsperson::create(['name' => 'Rejtett Műhely', 'visible' => false, 'sort' => 0]);

        $html = $this->get('/kezmuvesek')->assertOk()->assertSee('1–18. a 20 kézművesből')->assertSee('Kattints egy kézművesre')
            ->assertDontSee('Rejtett Műhely')->getContent();
        $this->assertSame(20, substr_count($html, '<article class="cv-card"'));
        $this->assertSame(2, substr_count($html, '<article class="cv-card" hidden'), 'a 18. utáni kártya rejtve (2026: 18/lap, a lapozó a JS-é)');
        $this->assertStringContainsString('<p>Első bekezdés 1.</p><p>Második bekezdés 1.</p>', $html, 'a bemutatkozás bekezdésekre bontva');
        $this->assertStringContainsString('id="pageDecor" data-decor="ambient"', $html);
        $this->assertStringContainsString('data-vscroll="cvmodal"', $html);

        // románul: van román bemutatkozás → az; nincs → a magyar; a hibás „Vâltoarei” alak nincs a lapon
        $ro = $this->get('/ro/kezmuvesek')->assertOk()->assertSee('Prezentare 1.')->assertSee('Első bekezdés 2.')
            ->assertSee('1–18. din 20 de meșteșugari')->getContent();
        $this->assertStringNotContainsString('Vâltoarei', $ro);

        Craftsperson::query()->delete();
        $this->get('/kezmuvesek')->assertOk()->assertSee('Még nincsenek felvett kézművesek.');
    }

    public function test_a_kivonat_a_2026_os_szabaly_szerint(): void
    {
        $this->assertSame('Rövid szöveg.', \App\Support\Kezmuvesek::kivonat("  Rövid\n szöveg.  "));
        $hosszu = str_repeat('alma körte, ', 30);
        $k = \App\Support\Kezmuvesek::kivonat($hosszu);
        $this->assertMatchesRegularExpression('/ (alma|körte)…$/u', $k, 'szóhatáron vág, a végéről az írásjelet leveszi');
        $this->assertLessThanOrEqual(171, mb_strlen($k));
    }

    public function test_a_kezmuvesek_behozatala_a_csomagbol_kepekkel_a_mediatarbol_csak_ures_tablaba(): void
    {
        $csomag = json_decode((string) file_get_contents(base_path(KezmuvesImport::LEIRAS)), true);
        $this->assertSame(67, $csomag['szamok']['kézműves']);

        // Kép nélkül (a médiatár-behozatal előtt) semmit nem ír, hibával áll meg.
        try {
            (new KezmuvesImport)->run(fn () => null);
            $this->fail('A hiányzó képnél meg kellett volna állnia.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('nincs a médiatárban', $e->getMessage());
        }
        $this->assertSame(0, Craftsperson::count());

        foreach (array_filter(array_column($csomag['kezmuvesek'], 'kep')) as $legacy) {
            Media::create(['disk' => 'mediatar', 'name' => $legacy, 'path' => "{$legacy}.jpg", 'type' => 'image/jpeg', 'ext' => 'jpg',
                'origin' => 'import2026', 'legacy_id' => $legacy]);
        }
        $this->assertSame(['behozva' => 67], (new KezmuvesImport)->run(fn () => null));
        $this->assertSame(67, Craftsperson::whereNotNull('image_media_id')->count());
        $this->assertSame(['behozva' => 0], (new KezmuvesImport)->run(fn () => null), 'másodszor: a tábla nem üres → kihagy');

        // A használt kép nem törölhető (törlés-őr), és a kategóriagazda nem éri el az admin-lapot.
        $k = Craftsperson::first();
        $this->assertContains("kézműves képe: {$k->name}", $k->image->usages());
        $kg = User::create(['name' => 'KG', 'email' => 'kg@forgatag.test', 'password' => 'hosszu-jelszo-kg', 'role' => 'category_owner']);
        $this->actingAs($kg)->get('/admin/kezmuvesek')->assertForbidden();
    }

    /** Külön futásban (egy teszten belüli felhasználóváltást az AuthenticateSession kiléptetésnek venné). */
    public function test_a_koordinator_a_tartalom_csoportban_kezeli_a_kezmuveseket(): void
    {
        Craftsperson::create(['name' => 'Próba Műhely']);
        $admin = User::create(['name' => 'Koord', 'email' => 'koord@forgatag.test', 'password' => 'hosszu-jelszo-koord', 'role' => 'coordinator']);
        $this->actingAs($admin)->get('/admin/kezmuvesek')->assertOk()->assertSee('Próba Műhely');
        $this->actingAs($admin)->get('/admin/kezmuvesek/create')->assertOk();
    }
}
