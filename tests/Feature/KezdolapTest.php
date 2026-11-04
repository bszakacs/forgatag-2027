<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Media;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageText;
use App\Models\Sponsor;
use App\Models\SponsorTier;
use App\Support\Kezdolap;
use App\Support\LapSablon;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * A kezdőlap (PROMPT-14): a két állapot (fesztivál előtt/alatt ↔ utána) a Beállításokból, mindkét irányba; a nyitókép címe
 * szerkeszthető; a négy szándékosan kivezetett 2026-os elem sehol nincs; románul a hírek nélkül; a partner-sáv a
 * szervezők nélkül; a galéria képei külön kérésben; a látótéren kívüli képek lustán töltődnek.
 */
class KezdolapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $seed = json_decode((string) file_get_contents(base_path('seed/settings.json')), true);
        Settings::save(array_intersect_key($seed, array_flip(['festival', 'submission', 'public', 'notifications'])));
        $p = new Page(['slug' => 'home', 'title_hu' => 'Kezdőlap', 'status' => 'published']);
        $p->is_system = true;
        $p->save();
        LapSablon::szinkron();
    }

    private function allapot(string $a): void
    {
        Settings::save(['public' => ['home_state' => $a] + Settings::block('public')]);
    }

    private function kep(): Media
    {
        Storage::disk('mediatar')->put($p = uniqid() . '.jpg', 'KEP');

        return Media::create(['disk' => 'mediatar', 'name' => pathinfo($p, PATHINFO_FILENAME), 'path' => $p,
            'type' => 'image/jpeg', 'ext' => 'jpg', 'size' => 3, 'width' => 2400, 'height' => 1600]);
    }

    private function szoveg(string $kulcs, string $hu, ?string $ro = null): void
    {
        PageText::query()->where('key', $kulcs)->update(['value_hu' => $hu, 'value_ro' => $ro]);
    }

    public function test_ma_a_fesztival_utani_allapotban_indul_visszaszamlalo_es_kiemelt_sav_nelkul(): void
    {
        $this->assertSame('utana', Kezdolap::allapot(), 'A seed a mai (fesztivál utáni) állapot.');
        $html = $this->get('/')->assertOk()->getContent();
        foreach (['hero', 'news', 'numbers', 'media', 'organizers'] as $sz) {
            $this->assertStringContainsString("data-section=\"{$sz}\"", $html, "hiányzik: {$sz}");
        }
        $this->assertStringContainsString('data-allapot="utana"', $html);
        $this->assertStringNotContainsString('data-section="highlight"', $html);
        $this->assertStringNotContainsString('hero-countdown', $html);
        $this->assertStringContainsString('2027. augusztus 25-29.', $html);
        $this->assertStringContainsString('között újra találkozunk!', $html);
    }

    public function test_a_kapcsolo_mindket_iranyba_mukodik(): void
    {
        $this->allapot('fesztival');
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-allapot="fesztival"', $html);
        $this->assertStringContainsString('data-section="highlight"', $html);
        $this->assertStringContainsString('data-comp="countdown"', $html);
        // A cél a Beállítások nyitónapja a megadott órában, a fesztivál időzónájával.
        $this->assertStringContainsString('data-start="2027-08-25T10:00:00+03:00"', $html);
        foreach (['Még', 'nap', 'óra', 'perc', 'Ott találkozunk!', 'Kiemelt', 'Nagyszínpadi záróbuli'] as $szo) {
            $this->assertStringContainsString($szo, $html);
        }

        $this->allapot('utana');
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-section="highlight"', $html);
        $this->assertStringNotContainsString('data-comp="countdown"', $html);
    }

    public function test_ismeretlen_allapot_vagy_hibas_ido_nem_nema(): void
    {
        $this->allapot('valami');
        $this->expectException(RuntimeException::class);
        Kezdolap::allapot();
    }

    public function test_a_nyitokep_cime_es_a_szamok_szerkeszthetok(): void
    {
        $this->szoveg('home_hero_title_a', '5 nap,');
        $this->szoveg('home_hero_title_b', ' több 100 program');
        $this->szoveg('home_stats_value_partners', '120+');
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<span class="accent" data-hs="home_hero_title_a">5 nap,</span>', $html);
        $this->assertStringContainsString('több 100 program', $html);
        $this->assertStringContainsString('data-target="120" data-suffix="+">120+', $html);
        $this->assertStringContainsString('data-target="10000" data-suffix="+">10' . "\u{00A0}" . '000+', $html);

        // Számmal nem kezdődő érték: hiba, nem néma 0.
        $this->expectException(RuntimeException::class);
        Kezdolap::szam('sok', 'home_stats_value_programs');
    }

    public function test_a_negy_kivezetett_elem_sehol_nincs(): void
    {
        $this->allapot('fesztival');
        $html = $this->get('/')->getContent();
        $fajlok = ['css/kezdolap.css', 'css/kezdolap-szerver.css', 'css/kezdolap-kartyak.css', 'css/galeria.css', 'css/video.css',
            'css/tamogatok.css', 'js/kezdolap.js', 'js/galeria.js', 'js/video.js'];
        $kod = $html . implode('', array_map(fn ($f) => (string) file_get_contents(public_path($f)), $fajlok))
            . (string) file_get_contents(app_path('Support/ThemeEditorSpec.php'))
            . (string) file_get_contents(base_path('seed/tema/tema.json'));
        foreach (['<canvas', 'particle', 'részecsk', 'Pörgess', 'sorsol', 'marquee', 'aurora'] as $tiltott) {
            $this->assertStringNotContainsStringIgnoringCase($tiltott, $kod, "Kivezetett elem került vissza: {$tiltott}");
        }
    }

    public function test_romanul_a_hirek_nelkul_es_roman_szoveggel(): void
    {
        NewsItem::create(['title_hu' => 'Friss hír', 'excerpt_hu' => 'K', 'body_hu' => '<p>Sz</p>', 'status' => 'published', 'published_on' => now()->toDateString()]);
        $this->szoveg('home_numbers_title_a', 'A Forgatag', 'Vâltoarea');
        $this->szoveg('home_numbers_title_b', 'számokban', 'în cifre');
        $this->assertStringContainsString('Friss hír', $this->get('/')->getContent());

        $html = $this->get('/ro')->assertOk()->getContent();
        $this->assertStringContainsString('<html lang="ro"', $html);
        $this->assertStringNotContainsString('data-section="news"', $html);
        $this->assertStringNotContainsString('Friss hír', $html);
        $this->assertStringContainsString('în cifre', $html);
        $this->assertStringContainsString('href="/ro/rolunk"', $html);
    }

    public function test_a_partner_sav_a_szervezok_nelkul_a_legutobbi_evbol(): void
    {
        $szervezok = SponsorTier::create(['name' => 'Szervezők', 'sort' => 1, 'year' => 2026]);
        $fo = SponsorTier::create(['name' => 'Főtámogatók', 'sort' => 2, 'year' => 2026]);
        Sponsor::create(['name' => 'Valaki a szervezők közül', 'year' => 2026, 'sponsor_tier_id' => $szervezok->id]);
        Sponsor::create(['name' => 'Marosvásárhelyért  Egyesület', 'year' => 2026, 'sponsor_tier_id' => $fo->id]);
        Sponsor::create(['name' => 'B Cég', 'year' => 2026, 'sponsor_tier_id' => $fo->id, 'sort' => 2, 'url' => 'https://b.test', 'logo_media_id' => $this->kep()->id]);
        Sponsor::create(['name' => 'A Cég', 'year' => 2026, 'sponsor_tier_id' => $fo->id, 'sort' => 1]);
        Sponsor::create(['name' => 'Régi Cég', 'year' => 2025, 'sponsor_tier_id' => $fo->id]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-autoscroll="1"', $html);
        $this->assertStringNotContainsString('Valaki a szervezők közül', $html);
        $this->assertStringNotContainsString('Régi Cég', $html);
        $this->assertMatchesRegularExpression('#<span class="tmg-name">A Cég</span>.*<a class="tmg-item" href="https://b.test"[^>]*><img[^>]+alt="B Cég"[^>]+loading="lazy"#s', $html);
        // A szervező egyesület név szerint kimarad (a szóköz/kis-nagybetű nem számít), a logója fent marad.
        $this->assertSame(1, substr_count($html, 'Marosvásárhelyért Egyesület'));
    }

    public function test_tamogato_nelkul_a_sav_teljesen_kimarad(): void
    {
        $html = $this->get('/')->getContent();
        $this->assertStringNotContainsString('sp-rail-blk', $html);
        $this->assertStringNotContainsString('data-rail="sponsorRail"', $html);
    }

    public function test_a_galeria_kepei_kulon_kerelemben_jonnek(): void
    {
        $g = Gallery::create(['year' => 2019, 'title_hu' => 'Forgatag 2019', 'sort' => 1]);
        $g->media()->attach([$this->kep()->id => ['sort' => 1], $this->kep()->id => ['sort' => 2]]);

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('data-year="2019"', $html);
        $this->assertStringContainsString('data-adat="/galeria-adat.json"', $html);
        $this->assertStringNotContainsString('w=1600', $html, 'A nagy képek címe nem kerülhet a lapba.');

        $adat = $this->getJson('/galeria-adat.json')->assertOk()->json();
        $this->assertSame('7. Vásárhelyi Forgatag', $adat['2019']['edition']);
        $this->assertCount(2, $adat['2019']['images']);
        $this->assertStringContainsString('w=1600', $adat['2019']['images'][0]['f']);
        $this->assertSame([1600, 1067], [$adat['2019']['images'][0]['w'], $adat['2019']['images'][0]['h']]);
        $this->assertSame('Ediția a 7-a', $this->getJson('/ro/galeria-adat.json')->json('2019.edition'));
    }

    public function test_az_aftermovie_sav_a_videoarchivum_elso_videos_blokkja(): void
    {
        $va = Page::create(['slug' => 'videoarchivum', 'title_hu' => 'Videó archívum', 'status' => 'published']);
        PageBlock::create(['page_id' => $va->id, 'sort' => 2, 'type' => 'videos', 'videos' => [['title_hu' => 'Másik', 'youtube_id' => 'MASIK']]]);
        PageBlock::create(['page_id' => $va->id, 'sort' => 1, 'type' => 'videos', 'videos' => [['title_hu' => 'Aftermovie 2025', 'title_ro' => 'Aftermovie Vâltoare 2025', 'youtube_id' => 'AFTER25']]]);

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('data-yt="AFTER25"', $html);
        $this->assertStringNotContainsString('MASIK', $html);
        $this->assertStringContainsString('id="vidLb"', $html);
        $this->assertStringContainsString('Aftermovie Vâltoare 2025', $this->get('/ro')->getContent());
    }

    public function test_a_kepek_a_megjelenes_meretere_vagva_es_a_latoteren_kivul_lustan(): void
    {
        $this->allapot('fesztival');
        foreach (['home_hero_slide_1', 'home_hero_slide_2', 'home_highlight_1_img'] as $k) {
            PageText::query()->where('key', $k)->update(['media_id' => $this->kep()->id]);
        }
        NewsItem::create(['title_hu' => 'Hír', 'excerpt_hu' => 'K', 'body_hu' => '<p>Sz</p>', 'status' => 'published',
            'published_on' => now()->toDateString(), 'cover_media_id' => $this->kep()->id]);
        $html = $this->get('/')->getContent();

        // Az első dia: telefonon a kijelző arányára vágott kisebb kép, asztalon 1920 széles; előre kérve.
        $this->assertMatchesRegularExpression('#<link rel="preload" as="image" href="[^"]+w=750&amp;h=1400&amp;fit=crop[^"]*" media="\(max-width:720px\)"#', $html);
        $this->assertMatchesRegularExpression('#<source media="\(max-width:720px\)" srcset="[^"]+w=750#', $html);
        // A többi dia csak a betöltés után (data-kep), nem a lap kérésével.
        $this->assertMatchesRegularExpression('#<div class="slide" data-img="home_hero_slide_2" data-kep="[^"]+w=1920#', $html);
        // Minden más kép a megjelenés arányán, lustán.
        $this->assertMatchesRegularExpression('#<img class="kep-kitolt" src="[^"]+w=720&amp;h=450&amp;fit=crop[^"]*"[^>]+loading="lazy"#', $html);
        $this->assertMatchesRegularExpression('#<img src="[^"]+w=800&amp;h=800&amp;fit=crop[^"]*"[^>]+loading="lazy"#', $html);
    }

    public function test_a_publikus_kezdolapon_nincs_filament_css(): void
    {
        $html = $this->get('/')->getContent();
        preg_match_all('/<(?:link|script)[^>]+(?:href|src)="([^"]+)"/', $html, $m);
        foreach ($m[1] as $forras) {
            $this->assertDoesNotMatchRegularExpression('#filament|admin-tema|tailwind|/build/|livewire#i', $forras, $forras);
        }
    }

    /**
     * 2026-11-04-i hiba: a firkák a lapon voltak (az elemszám stimmelt), de EGY SEM rajzolódott ki — a keret a motívum saját
     * viewBox-át kapta, a <use> pedig a rajzot a keret 0,0 pontjára teszi, vagyis a látható részen kívülre. Mindkét
     * firka-építő (a közös réteg és a kezdőlap) „0 0 szélesség magasság” keretet adjon.
     */
    public function test_a_firka_keret_a_motivum_meretet_kapja_nem_a_koordinatait(): void
    {
        foreach (['js/mozgas.js', 'js/kezdolap.js'] as $f) {
            $js = (string) file_get_contents(public_path($f));
            $this->assertMatchesRegularExpression("/0 0 ' \\+ v\\[2\\] \\+ ' ' \\+ v\\[3\\]/", $js, "{$f}: a keret nem „0 0 sz m”");
            $this->assertDoesNotMatchRegularExpression('/viewBox="\'\s*\+\s*s\.getAttribute\(\'viewBox\'\)/', $js, "{$f}: a motívum viewBox-a a keretre került");
        }
    }
}
