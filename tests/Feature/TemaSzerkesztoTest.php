<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ThemeVersion;
use App\Models\User;
use App\Support\Settings;
use App\Support\Theme;
use App\Support\ThemeCss;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Az Arculat-szerkesztő (PROMPT-06) ellenőrző listája: jog, piszkozat/publikált szétválasztás,
 * kontraszt-kapu, előzmény-visszaállás, feltöltés, gyári, a kivezetett csoportszínek.
 */
class TemaSzerkesztoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
        Settings::save([
            'festival' => ['start_date' => '2027-08-25', 'end_date' => '2027-08-29', 'prep_days' => 2, 'opening_day' => '2027-08-25',
                'name_hu' => 'Vásárhelyi Forgatag', 'name_ro' => 'Vâltoarea Mureșeană', 'day_cut_hour' => 3],
            'submission' => ['open' => true, 'closed_message' => 'Zárva.', 'deadline' => null],
            'notifications' => ['topics' => []],
        ]);
    }

    /** A 422-es válasz emberi hibaüzenete tartalmazza-e a szöveget. */
    private function hiba($resp, string $szoveg): void
    {
        $resp->assertStatus(422);
        $this->assertStringContainsString($szoveg, (string) $resp->json('error'));
    }

    private function user(UserRole $r): User
    {
        return User::create(['name' => $r->label(), 'email' => $r->value . '@forgatag.test', 'password' => 'titok-jelszo-123', 'role' => $r]);
    }

    private function mint(UserRole $r): static
    {
        return $this->actingAs($this->user($r), 'web');
    }

    // ---------------------------------------------------------------- jog

    public function test_az_arculat_szerkeszto_csak_a_tema_lapot_eri_el(): void
    {
        $this->mint(UserRole::ThemeEditor);

        $this->get('/admin/tema')->assertOk()->assertSee('te-config', false);
        $this->get('/admin')->assertRedirect('/admin/tema');
        foreach (['/admin/programok', '/admin/users', '/admin/program-managers', '/admin/beallitasok'] as $cim) {
            $this->get($cim)->assertForbidden();
        }
        $this->get('/admin/programok/1/foto')->assertNotFound();
        $this->get('/admin/tema-api/elonezet')->assertOk();
    }

    /** @return array<string, array{0: UserRole}> */
    public static function masSzerepkorok(): array
    {
        return ['koordinátor' => [UserRole::Coordinator], 'kategóriagazda' => [UserRole::CategoryOwner],
            'fordító' => [UserRole::Translator], 'csak olvasó' => [UserRole::ReadOnly]];
    }

    // Szerepkörönként KÜLÖN futás: egy teszten belüli felhasználóváltást a munkamenet-őr
    // (AuthenticateSession) kiléptetésnek venné, és 302-t adna — az nem a jog mérése lenne.
    #[\PHPUnit\Framework\Attributes\DataProvider('masSzerepkorok')]
    public function test_mas_szerepkor_nem_eri_el_a_tema_lapot_es_vegpontjait(UserRole $r): void
    {
        {
            $this->mint($r);
            $this->get('/admin/tema')->assertForbidden();
            $this->get('/admin/tema-api/elonezet')->assertForbidden();
            $this->get('/admin/tema-api/gyari')->assertForbidden();
            $this->postJson('/admin/tema-api/publikalas', ['data' => Theme::data()])->assertForbidden();
            $this->postJson('/admin/tema-api/vazlat', ['data' => Theme::data(), 'base_version_id' => 1])->assertForbidden();
            $this->post('/admin/tema-api/feltoltes', ['path' => 'assets.logo'], ['Accept' => 'application/json'])->assertForbidden();
            $this->get('/admin')->assertOk();
        }
        $this->assertSame(1, ThemeVersion::count());
    }

    public function test_belepes_nelkul_semmi(): void
    {
        $this->get('/admin/tema')->assertRedirect('/admin/login');
        $this->postJson('/admin/tema-api/publikalas', ['data' => Theme::data()])->assertUnauthorized();
    }

    public function test_a_szuperadmin_is_eleri(): void
    {
        $this->mint(UserRole::SuperAdmin)->get('/admin/tema')->assertOk();
    }

    // ---------------------------------------------------------------- piszkozat / publikálás

    public function test_a_piszkozat_nem_latszik_kint_a_publikalas_igen_es_uj_css_nevet_ad(): void
    {
        $this->mint(UserRole::ThemeEditor);
        $regi = ThemeVersion::first();
        $d = Theme::data();
        $d['colors']['bg']['dark'] = '#2A2550';

        $this->postJson('/admin/tema-api/vazlat', ['data' => $d, 'base_version_id' => $regi->id])->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(1, DB::table('theme_drafts')->count());
        Theme::flush();
        $this->get('/bekuldes/belepes')->assertSee('/arculat/' . $regi->css_file, false);

        $r = $this->postJson('/admin/tema-api/publikalas', ['data' => $d, 'comment' => 'sötétebb háttér'])->assertOk()->json();
        $uj = ThemeVersion::latest('id')->first();
        $this->assertSame($uj->id, $r['version']['id']);
        $this->assertNotSame($regi->css_file, $uj->css_file);
        $this->assertSame(0, DB::table('theme_drafts')->count(), 'Publikálás után a piszkozat megszűnik.');
        $this->assertSame('theme_editor@forgatag.test', $uj->published_by);

        Theme::flush();
        $this->get('/bekuldes/belepes')->assertSee('/arculat/' . $uj->css_file, false)->assertDontSee('/arculat/' . $regi->css_file, false);
        $this->assertStringContainsString('--bg:#2A2550', Storage::disk(Theme::DISK)->get($uj->css_file));
    }

    public function test_a_kontraszt_or_megallitja_es_csak_irasos_felulbiralassal_engedi(): void
    {
        $this->mint(UserRole::ThemeEditor);
        $d = Theme::data();
        $d['colors']['fg']['dark'] = '#3A3560'; // szinte a háttér színe

        $this->postJson('/admin/tema-api/publikalas', ['data' => $d])
            ->assertStatus(422)->assertJsonPath('ok', false)->assertJsonPath('contrast.0.label', 'fg ↔ bg');
        $this->postJson('/admin/tema-api/publikalas', ['data' => $d, 'override' => 'rövid'])->assertStatus(422);
        $this->assertSame(1, ThemeVersion::count());

        $this->postJson('/admin/tema-api/publikalas', ['data' => $d, 'override' => 'Szándékos: csak egy órás próba, utána vissza.'])->assertOk();
        $this->assertSame('Szándékos: csak egy órás próba, utána vissza.', ThemeVersion::latest('id')->first()->contrast_override);
    }

    public function test_a_vilagos_mod_kontrasztjat_is_nezi(): void
    {
        $this->mint(UserRole::ThemeEditor);
        $d = Theme::data();
        $d['colors']['fgMuted']['light'] = '#E0E0E8';

        $this->postJson('/admin/tema-api/publikalas', ['data' => $d])->assertStatus(422)->assertJsonPath('contrast.0.mode', 'light');
    }

    public function test_az_elozmenybol_vissza_lehet_allni(): void
    {
        $this->mint(UserRole::ThemeEditor);
        $elso = ThemeVersion::first();
        $d = Theme::data();
        $d['shape']['rCard'] = 6;
        $this->postJson('/admin/tema-api/publikalas', ['data' => $d])->assertOk();

        $regiAdat = $this->getJson("/admin/tema-api/verzio/{$elso->id}")->assertOk()->json('data');
        $this->postJson('/admin/tema-api/publikalas', ['data' => $regiAdat, 'comment' => 'vissza az elsőre'])->assertOk();

        $kint = ThemeVersion::latest('id')->first();
        $this->assertSame(3, ThemeVersion::count(), 'A visszaállás is új verzió (az előzmény megmarad).');
        $this->assertSame($elso->css_file, $kint->css_file, 'Ugyanaz az adat = ugyanaz a CSS-fájl.');
        $this->assertSame(20, Theme::data()['shape']['rCard']);
    }

    public function test_az_elozmeny_lista_az_utolso_10(): void
    {
        $d = Theme::data();
        foreach (range(1, 12) as $i) {
            $d['shape']['rCard'] = $i * 2;
            Theme::publish($d, null, "próba {$i}");
        }
        $this->assertCount(10, Theme::history(10));
        $this->mint(UserRole::ThemeEditor)->get('/admin/tema')->assertOk()->assertSee('próba 12')->assertDontSee('próba 2"', false);
    }

    // ---------------------------------------------------------------- szerkezet

    public function test_kivezetett_vagy_ismeretlen_mezo_nem_mentheto_importtal_sem(): void
    {
        $this->mint(UserRole::ThemeEditor);
        $d = Theme::data();
        $d['colors']['groups'] = ['zene' => ['dark' => '#E5389E']];
        $this->hiba($this->postJson('/admin/tema-api/vazlat', ['data' => $d, 'base_version_id' => 1]), 'colors.groups');

        $d = Theme::data();
        $d['components'] = ['chatButton' => true];
        $this->hiba($this->postJson('/admin/tema-api/publikalas', ['data' => $d]), 'components');

        $d = Theme::data();
        unset($d['colors']['bg']['light']);
        $this->hiba($this->postJson('/admin/tema-api/publikalas', ['data' => $d]), 'colors.bg.light');

        $d = Theme::data();
        $d['shape']['rCard'] = 500;
        $this->hiba($this->postJson('/admin/tema-api/publikalas', ['data' => $d]), 'Kártya-lekerekítés');
    }

    public function test_a_csoportszinek_megszuntek_es_a_vilagos_blokk_kint_van(): void
    {
        $css = ThemeCss::generate(Theme::data());
        $this->assertStringNotContainsString('--grp-', $css);
        $this->assertArrayNotHasKey('groups', Theme::data()['colors']);
        $this->assertStringContainsString(':root[data-theme="light"]{color-scheme:light;--bg:#F1F1F7;', $css);
        $this->assertStringContainsString('--link:var(--accent-c)', $css);
    }

    public function test_a_bekuldo_urlapon_van_vilagos_sotet_valto(): void
    {
        $this->get('/bekuldes/belepes')->assertOk()
            ->assertSee('id="themeToggle"', false)
            ->assertSee("localStorage.getItem('forgatag-theme')", false);
    }

    // ---------------------------------------------------------------- fájlok

    public function test_feltoltes_es_gyari_visszaallitas(): void
    {
        $this->mint(UserRole::ThemeEditor);

        // Valódi PNG (a konténerben nincs GD a hamis képhez): a gyári favicon másolata.
        $tmp = tempnam(sys_get_temp_dir(), 'png');
        copy(base_path('seed/tema/fajlok/favicon.png'), $tmp);
        $png = new UploadedFile($tmp, 'uj-logo.png', 'image/png', null, true);
        $r = $this->post('/admin/tema-api/feltoltes', ['path' => 'assets.logo', 'file' => $png], ['Accept' => 'application/json'])->assertOk()->json();
        $this->assertMatchesRegularExpression('#^fajlok/logo-[0-9a-f]{12}\.png$#', $r['path']);
        $this->assertTrue(Storage::disk(Theme::DISK)->exists($r['path']));

        $svg = UploadedFile::fake()->createWithContent('f.svg', '<svg xmlns="http://www.w3.org/2000/svg"><circle r="4"/></svg>');
        $r2 = $this->post('/admin/tema-api/feltoltes', ['path' => 'doodles.motifs.2', 'file' => $svg], ['Accept' => 'application/json'])->assertOk()->json();
        $this->assertStringStartsWith('fajlok/firka-3-', $r2['path']);
        $this->assertStringStartsWith('<svg', $r2['svg']);

        $rossz = UploadedFile::fake()->createWithContent('x.svg', '<svg><script>alert(1)</script></svg>');
        $this->post('/admin/tema-api/feltoltes', ['path' => 'doodles.motifs.0', 'file' => $rossz], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post('/admin/tema-api/feltoltes', ['path' => 'doodles.motifs.0', 'file' => $png], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('ok', false);
        $this->post('/admin/tema-api/feltoltes', ['path' => 'colors.bg', 'file' => $png], ['Accept' => 'application/json'])->assertStatus(422);

        $gyari = $this->getJson('/admin/tema-api/gyari')->assertOk()->json('data');
        $this->assertSame(Theme::data()['assets'], $gyari['assets'], 'A gyári fájl ugyanaz a feltöltött fájl (azonos lenyomat).');
    }

    public function test_ures_fajl_mezo_nem_mentheto(): void
    {
        $this->mint(UserRole::ThemeEditor);
        $d = Theme::data();
        $d['assets']['favicon'] = '';
        $this->hiba($this->postJson('/admin/tema-api/vazlat', ['data' => $d, 'base_version_id' => 1]), 'assets.favicon');
    }
}
