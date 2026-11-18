<?php

namespace Tests\Feature;

use App\Filament\Pages\Beallitasok;
use App\Models\SecretSetting;
use App\Models\User;
use App\Services\Forditas\GepiForditas;
use App\Support\Titkok;
use Filament\Actions\Testing\TestAction;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Titkos beállítások (App\Support\Titkok): titkosítva az adatbázisban, CSAK szuperadmin, írható de nem olvasható,
 * egyértelmű elsőbbség (admin > .env), és ez a felületen látszik.
 */
class TitkokTest extends TestCase
{
    use RefreshDatabase;

    private const KULCS = 'sk-ant-TITKOS-proba-1234567890';

    private function user(string $szerep, string $email): User
    {
        return User::create(['name' => ucfirst($szerep), 'email' => $email, 'password' => 'jelszo-hosszu-1', 'role' => $szerep]);
    }

    public function test_titkositva_tarolja_es_soha_nem_adja_ki(): void
    {
        $sa = $this->user('superadmin', 'sa@forgatag.test');
        Titkok::beallit('anthropic_api_key', self::KULCS, $sa);

        $nyers = DB::table('secret_settings')->where('key', 'anthropic_api_key')->value('value');
        $this->assertStringNotContainsString('TITKOS', $nyers);              // az adatbázisban (és a mentésben) titkosítva
        $this->assertSame(self::KULCS, Crypt::decryptString($nyers));         // a Laravel beépített titkosításával
        $this->assertSame(self::KULCS, Titkok::ertek('anthropic_api_key'));
        $this->assertArrayNotHasKey('value', SecretSetting::first()->toArray()); // JSON-ba / tömbbe sem kerül ki
        $this->assertStringNotContainsString(self::KULCS, json_encode(Titkok::allapot('anthropic_api_key')));

        // A felületen: az állapot igen, az érték SEHOL (a HTML-ben és a Livewire-állapotban sem).
        $this->actingAs($sa, 'web');
        $lap = Livewire::test(Beallitasok::class)
            ->assertSee('Titkos beállítások (csak szuperadmin)')
            ->assertSee('EZ VAN HASZNÁLATBAN')
            ->assertSee('(Superadmin)');
        $this->assertStringNotContainsString('TITKOS', $lap->html());
        $this->assertStringNotContainsString('TITKOS', json_encode($lap->instance()->data));
    }

    public function test_csak_a_szuperadmin_latja_es_allithatja(): void
    {
        $koord = $this->user('coordinator', 'k@forgatag.test');
        $this->actingAs($koord, 'web');
        Livewire::test(Beallitasok::class)
            ->assertDontSee('Titkos beállítások (csak szuperadmin)')
            ->assertDontSee('Claude API-kulcs (gépi fordítás)');

        foreach (['coordinator', 'theme_editor', 'translator', 'category_owner'] as $i => $szerep) {
            $u = $szerep === 'coordinator' ? $koord : $this->user($szerep, "u{$i}@forgatag.test");
            try {
                Titkok::beallit('anthropic_api_key', 'x', $u);
                $this->fail("{$szerep} beállíthatta a titkot.");
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $this->assertSame(0, SecretSetting::count());
    }

    public function test_szuperadmin_a_feluleten_megadja_cserel_torol(): void
    {
        config(['forditas.kulcs' => 'env-kulcs']);
        $sa = $this->user('superadmin', 'sa@forgatag.test');
        $this->actingAs($sa, 'web');

        Livewire::test(Beallitasok::class)
            ->assertSee('a szerveri .env értéke (ANTHROPIC_API_KEY) van használatban')
            ->callAction(TestAction::make('titok_anthropic_api_key')->schemaComponent('titok_anthropic_api_key_gombok', 'form'), ['ertek' => self::KULCS])
            ->assertHasNoErrors();
        $this->assertSame(self::KULCS, Titkok::ertek('anthropic_api_key'));
        $this->assertSame('Superadmin', SecretSetting::first()->updated_by);

        Livewire::test(Beallitasok::class)
            ->callAction(TestAction::make('titok_anthropic_api_key_torles')->schemaComponent('titok_anthropic_api_key_gombok', 'form'));
        $this->assertSame('env-kulcs', Titkok::ertek('anthropic_api_key'));
    }

    public function test_az_admin_ertek_nyer_a_env_folott_es_ez_latszik(): void
    {
        $sa = $this->user('superadmin', 'sa@forgatag.test');
        config(['forditas.kulcs' => null]);
        $this->assertNull(Titkok::ertek('anthropic_api_key'));
        $this->assertNull(Titkok::forras('anthropic_api_key'));
        $this->assertFalse(GepiForditas::vanKulcs());
        $this->assertStringContainsString('NINCS BEÁLLÍTVA', Titkok::allapot('anthropic_api_key')['szoveg']);

        config(['forditas.kulcs' => 'env-kulcs']);
        $this->assertSame('env-kulcs', Titkok::ertek('anthropic_api_key'));
        $this->assertSame('env', Titkok::forras('anthropic_api_key'));
        $this->assertTrue(GepiForditas::vanKulcs());

        Titkok::beallit('anthropic_api_key', self::KULCS, $sa);
        $this->assertSame(self::KULCS, Titkok::ertek('anthropic_api_key'));   // az admin NYER
        $a = Titkok::allapot('anthropic_api_key');
        $this->assertSame('admin', $a['forras']);
        $this->assertStringContainsString('A szerveri .env-ben is van érték (ANTHROPIC_API_KEY), de az adminban megadott nyer', $a['szoveg']);

        Titkok::torol('anthropic_api_key', $sa);
        $this->assertSame('env-kulcs', Titkok::ertek('anthropic_api_key'));   // törlés után a .env él
    }

    public function test_visszafejthetetlen_ertek_latszik_es_nem_esik_vissza_csendben(): void
    {
        config(['forditas.kulcs' => 'env-kulcs']);
        $mas = new Encrypter(random_bytes(32), 'aes-256-cbc');
        DB::table('secret_settings')->insert(['key' => 'anthropic_api_key', 'value' => $mas->encryptString('regi'),
            'updated_by' => 'X', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertStringContainsString('NEM fejthető vissza', (string) Titkok::allapot('anthropic_api_key')['hiba']);
        $this->expectException(RuntimeException::class);
        Titkok::ertek('anthropic_api_key');
    }

    public function test_ures_ertek_nem_menthet(): void
    {
        $this->expectException(RuntimeException::class);
        Titkok::beallit('anthropic_api_key', '   ', $this->user('superadmin', 'sa@forgatag.test'));
    }
}
