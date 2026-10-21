<?php

namespace Tests\Feature;

use App\Models\ThemeVersion;
use App\Support\Theme;
use App\Support\ThemeCss;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/**
 * A téma-alap (PROMPT-05) ellenőrző listája. A TestCase a valódi kezdő témát tölti be
 * (seed/tema/) hamis arculati tárba — ugyanazon az úton, mint a telepítés.
 */
class TemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_kezdo_tema_a_2026_os_utolso_publikalas_minden_mezojevel(): void
    {
        $forras = json_decode((string) file_get_contents(base_path('seed/tema/tema.json')), true);
        $d = Theme::data();

        $this->assertSame(1, ThemeVersion::count());
        foreach (['colors', 'glow', 'type', 'shape', 'space', 'motion', 'social', 'analytics', 'logo'] as $blokk) {
            $this->assertSame($forras[$blokk], $d[$blokk], "A(z) {$blokk} blokk nem jött át változatlanul.");
        }
        // A „Komponensek” megszűnt: a MA jelvény a színek közt, az üveghatás a fejlécben, a chat-gomb sehol.
        $this->assertArrayNotHasKey('components', $d);
        $this->assertSame('#FFAE4D', $d['colors']['badgeMaBg']['dark']);
        $this->assertSame(16, $d['header']['navbarBlur']);
        $this->assertStringNotContainsString('chatButton', json_encode($d));
    }

    public function test_logo_favicon_es_a_hat_firka_valodi_feltoltott_fajl(): void
    {
        $disk = Storage::disk(Theme::DISK);
        foreach (Theme::ASSET_KEYS as $k) {
            $ut = Theme::get("assets.{$k}");
            $this->assertMatchesRegularExpression('#^fajlok/[a-z-]+-[0-9a-f]{12}\.png$#', $ut, $k);
            $this->assertTrue($disk->exists($ut), "{$k}: a fájl nincs a tárban");
        }
        $this->assertSame(
            hash_file('sha256', base_path('seed/tema/fajlok/favicon.png')),
            hash('sha256', $disk->get(Theme::get('assets.favicon'))),
        );
        $this->assertCount(Theme::MOTIF_SLOTS, Theme::get('doodles.motifs'));
        foreach (range(0, Theme::MOTIF_SLOTS - 1) as $i) {
            $this->assertStringStartsWith('<svg', Theme::motifSvg($i));
        }
    }

    public function test_nincs_nas_ra_mutato_hivatkozas_se_az_adatban_se_a_css_ben(): void
    {
        $css = Storage::disk(Theme::DISK)->get(ThemeVersion::latest('id')->first()->css_file);
        $adat = json_encode(Theme::data(), JSON_UNESCAPED_SLASHES);
        foreach (Theme::TILTOTT_CIMEK as $cim) {
            $this->assertStringNotContainsString($cim, $css);
            $this->assertStringNotContainsString($cim, $adat);
        }
        $this->assertStringNotContainsString('://', $css, 'A téma-CSS-ben ne legyen teljes cím.');
    }

    public function test_nas_cim_nem_publikalhato(): void
    {
        $d = Theme::data();
        $d['assets']['favicon'] = 'https://admin.forgatag.ro/assets/65329520-cedf-4f86-b9ca-9f70e20deb7c';

        $this->expectException(RuntimeException::class);
        Theme::publish($d, null, 'próba');
    }

    public function test_a_css_fajlneve_a_tartalom_lenyomata_es_valtozaskor_valtozik(): void
    {
        $regi = ThemeVersion::latest('id')->first()->css_file;
        $this->assertMatchesRegularExpression('/^tema-[0-9a-f]{12}\.css$/', $regi);
        $this->assertSame('tema-' . substr(hash('sha256', ThemeCss::generate(Theme::data())), 0, 12) . '.css', $regi);

        $d = Theme::data();
        $d['colors']['bg']['dark'] = '#123456';
        $uj = Theme::publish($d, null, 'háttérszín-próba');

        $this->assertNotSame($regi, $uj->css_file);
        $this->assertStringContainsString('--bg:#123456', Storage::disk(Theme::DISK)->get($uj->css_file));
        $this->assertStringEndsWith('/arculat/' . $uj->css_file, Theme::cssUrl());
        $this->assertTrue(Storage::disk(Theme::DISK)->exists($regi), 'A régi fájl maradjon (gyorsítótárazott lapok).');
    }

    public function test_ugyanabbol_az_adatbol_ugyanaz_a_fajlnev(): void
    {
        $regi = ThemeVersion::latest('id')->first()->css_file;
        $this->assertSame($regi, Theme::publish(Theme::data(), null, 'újrapublikálás')->css_file);
    }

    public function test_hianyzo_kotelezo_mezo_hibaval_all_meg_nincs_beepitett_tartalek(): void
    {
        $d = Theme::data();
        unset($d['colors']['fg'], $d['logo']['headerSize']);

        try {
            Theme::publish($d, null, 'hiányos');
            $this->fail('A hiányos téma publikálódott.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('colors.fg', $e->getMessage());
            $this->assertStringContainsString('logo.headerSize', $e->getMessage());
        }
        $this->assertSame(1, ThemeVersion::count(), 'Hibás publikálás nem hozhat létre verziót.');
    }

    public function test_hianyzo_firka_vagy_logo_nem_publikalhato(): void
    {
        $d = Theme::data();
        $d['doodles']['motifs'][5] = '';
        $this->expectException(RuntimeException::class);
        Theme::publish($d, null, 'üres firkahely');
    }

    public function test_szkriptes_svg_nem_toltheto_fel(): void
    {
        $ut = tempnam(sys_get_temp_dir(), 'svg') . '.svg';
        file_put_contents($ut, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->expectException(RuntimeException::class);
        Theme::storeAsset($ut, 'firka-1');
    }

    public function test_a_verzio_append_only(): void
    {
        $v = ThemeVersion::first();
        $this->expectException(LogicException::class);
        $v->update(['comment' => 'átírva']);
    }

    public function test_a_bekuldo_lap_a_generalt_tema_css_t_es_a_tema_faviconjat_hasznalja(): void
    {
        $v = ThemeVersion::latest('id')->first();
        $this->get('/bekuldes/belepes')
            ->assertOk()
            ->assertSee('/arculat/' . $v->css_file, false)
            ->assertSee('/arculat/' . $v->data['assets']['favicon'], false)
            ->assertDontSee('tema.css', false)
            ->assertSee('<meta name="theme-color" content="#322D55">', false);
    }

    public function test_tema_nelkul_a_lap_hibaval_all_meg(): void
    {
        DB::table('theme_versions')->delete();
        Theme::flush();

        $this->get('/bekuldes/belepes')->assertStatus(500);
    }
}
