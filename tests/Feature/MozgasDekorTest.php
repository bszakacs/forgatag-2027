<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\ProgramItem;
use App\Models\Venue;
use App\Support\LapSablon;
use App\Support\Settings;
use App\Support\Theme;
use App\Support\ThemeCss;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A mozgás- és dekor-réteg (PROMPT-13): minden publikus lap megkapja; a téma minden mozgás-/firka-kapcsolója valódi
 * CSS-t ad; a „Minimális” fokozat és a rendszer „kevesebb mozgás” kérése leállítja; a 12 firka-motívum EGY közös
 * készletben van a lapon; a Rólunk fonala a 2026-os jelöléssel; a 6 motívumos téma migrációja.
 */
class MozgasDekorTest extends TestCase
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
        LapSablon::szinkron();
    }

    /** @return list<string> a próbált publikus lapok (minden eddig megépült laptípus) */
    private function lapok(): array
    {
        NewsItem::create(['title_hu' => 'Hír 1', 'excerpt_hu' => 'K', 'body_hu' => '<p>Sz</p>', 'status' => 'published', 'published_on' => now()->toDateString()]);
        Page::create(['slug' => 'proba-lap', 'title_hu' => 'Próba lap', 'status' => 'published']);
        $k = Category::create(['slug' => 'koncert', 'name_hu' => 'Koncert', 'sort' => 1]);
        $v = Venue::create(['slug' => 'var', 'name' => 'Vár']);
        $p = ProgramItem::create(['title' => 'Koncert A', 'description' => 'L', 'status' => 'published', 'category_id' => $k->id, 'venue_id' => $v->id]);
        $p->occurrences()->create(['date' => '2027-08-27', 'start_time' => '18:00']);

        return ['/rolunk', '/hirek', '/hirek/hir-1', '/proba-lap', '/programok', '/nap/2027-08-27', '/program/' . $p->slug];
    }

    public function test_minden_publikus_lap_megkapja_a_reteget(): void
    {
        foreach ($this->lapok() as $ut) {
            $html = $this->get($ut)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('#<script src="[^"]*/js/mozgas\.js\?v=[0-9a-f]+" defer>#', $html, "{$ut}: a mozgás-réteg hiányzik");
            // A felúszás rejtése a <head>-ben dől el, és CSAK ha a rendszer nem kért kevesebb mozgást.
            $this->assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)')", $html, $ut);
            $this->assertStringNotContainsString('data-motion="minimal"', $html, "{$ut}: a gyári téma „Teljes”");
        }
    }

    public function test_minimalis_fokozatnal_a_keret_jelzi_es_a_tema_mindent_leallit(): void
    {
        $d = Theme::data();
        $d['motion']['level'] = 'minimal';
        Theme::publish($d, null, 'teszt');
        $html = $this->get('/rolunk')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<html lang="hu"\s+data-motion="minimal"\s*>/', $html);
        $css = ThemeCss::generate($d);
        $this->assertStringContainsString('animation-duration:.001s!important', $css);
        $this->assertStringContainsString('[data-decor] .decor{animation:none!important}', $css);
    }

    public function test_a_kevesebb_mozgas_kerese_mindent_lealit_a_kozos_cssben(): void
    {
        $css = (string) file_get_contents(public_path('css/publikus.css'));
        $this->assertStringContainsString('@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}', $css);
        // A felúszás rejtése a „mozgas” osztályhoz kötött → kevesebb mozgásnál (és JS nélkül) semmi nem marad rejtve.
        $this->assertStringContainsString('.mozgas .reveal{opacity:0', $css);
        $this->assertDoesNotMatchRegularExpression('/(^|\})\.reveal\{opacity:0/', $css);
        $js = (string) file_get_contents(public_path('js/mozgas.js'));
        $this->assertStringContainsString("if (RM || !MOTIVUMOK.length", $js, 'kevesebb mozgásnál a szórt firkák fel sem épülnek');
    }

    public function test_a_firka_kapcsolok_mind_valodi_szabalyt_adnak(): void
    {
        $gyari = Theme::data();
        $alap = ThemeCss::generate($gyari);
        $eset = [
            ['doodles.density', 'none', '[data-decor]{display:none}'],
            ['doodles.density', 'subtle', '[data-decor="ambient"] .decor:nth-child(2n){display:none}'],
            ['doodles.zones.hero', false, '[data-decor="hero"]{display:none}'],
            ['doodles.zones.noPhotoCards', false, '.pc-doodle{display:none}'],
            ['motion.doodleFloat', false, '[data-decor] .decor{animation:none!important}'],
            ['motion.level', 'subtle', '[data-decor] .decor{animation:none!important}'],
            ['doodles.opacity', 0.3, '--doodle-opacity:0.3'],
            ['doodles.palette.blue', '#123456', '--doodle-blue:#123456'],
            ['motion.dur1', 0.3, '--dur-1:0.3s'],
            ['motion.dur2', 0.9, '--dur-2:0.9s'],
            ['motion.pulseDur', 3, '--pulse-dur:3s'],
        ];
        foreach ($eset as [$ut, $ertek, $vart]) {
            $d = $gyari;
            data_set($d, $ut, $ertek);
            $css = ThemeCss::generate($d);
            $this->assertStringContainsString($vart, $css, $ut);
            $this->assertStringNotContainsString($vart, $alap, "{$ut}: a gyári témában nem lehet benne");
        }
    }

    public function test_a_programkeresoben_a_szort_firkak_retege_a_kozos_keszletbol(): void
    {
        $this->lapok();
        $html = $this->get('/programok')->assertOk()->getContent();
        $this->assertStringContainsString('<div class="fgl-decor" id="pageDecor" data-decor="ambient" data-szoras="oldal" aria-hidden="true"></div>', $html);
        // A 12 motívum EGYSZER van a lapon (szimbólumként), a firkák csak hivatkoznak rá.
        $this->assertSame(1, substr_count($html, 'class="fg-motivumok"'));
        $this->assertSame(Theme::MOTIF_SLOTS, preg_match_all('/<symbol id="fgm-\d+" viewBox="[\d. -]+">/', $html));
        $this->assertStringNotContainsString('<script', Theme::motifSprite());
        // A színezés a témából: a motívumok kitöltése var(--doodle-…), beégetett hexa nélkül.
        $this->assertDoesNotMatchRegularExpression('/fill="#[0-9a-f]{3,6}"/i', Theme::motifSprite());
    }

    public function test_a_rolunk_fonala_es_feluszasa_a_2026_os_jelolessel(): void
    {
        $html = $this->get('/rolunk')->assertOk()->getContent();
        foreach (['id="thread"', 'id="thread-track"', 'id="thread-prog"', 'id="thread-marker"'] as $j) {
            $this->assertStringContainsString($j, $html);
        }
        $this->assertStringNotContainsString('thread-decor', $html, 'a fonál mentén 2026-ban nem volt firka (tulajdonosi döntés)');
        $this->assertSame(5, substr_count($html, 'class="srow') - substr_count($html, 'class="srow rev') + substr_count($html, 'class="srow rev'));
        $this->assertGreaterThanOrEqual(9, substr_count($html, 'reveal'), 'nyitócím + 5 sor + 2 címsor + logók + kártyák');
        $this->assertMatchesRegularExpression('#<script src="[^"]*/js/rolunk\.js\?v=[0-9a-f]+" defer>#', $html);
    }

    public function test_a_hatmotivumos_tema_migracioja_12_helyre_egeszit_ki(): void
    {
        $d = Theme::data();
        $d['doodles']['motifs'] = array_slice($d['doodles']['motifs'], 0, 6);
        // Régi (6 helyes) állapot közvetlenül a táblába — a mai publikálás már nem engedné.
        DB::table('theme_versions')->insert(['data' => json_encode($d), 'css_file' => 'x.css', 'published_by' => 'regi', 'created_at' => now()]);
        DB::table('theme_drafts')->insert(['data' => json_encode($d), 'base_version_id' => 1, 'saved_by' => 'regi', 'updated_at' => now()]);
        Theme::flush();

        $m = require database_path('migrations/2027_01_12_000100_theme_12_motifs.php');
        $m->up();
        Theme::flush();

        $uj = Theme::get('doodles.motifs');
        $this->assertCount(12, $uj);
        $this->assertSame(array_slice($d['doodles']['motifs'], 0, 6), array_slice($uj, 0, 6), 'a meglévő 6 helyhez nem nyúl');
        $this->assertStringStartsWith('fajlok/firka-12-', $uj[11]);
        $this->assertNull(Theme::draft(), 'a publikálás a piszkozatot is lezárja');
        $m->up(); // ismételve nem keletkezik új verzió
        $this->assertSame(1, DB::table('theme_versions')->where('published_by', 'migracio')->count());
    }
}
