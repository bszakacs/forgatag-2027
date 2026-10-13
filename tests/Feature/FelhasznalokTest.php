<?php

namespace Tests\Feature;

use App\Filament\Resources\ProgramManagers\Pages\CreateProgramManager;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Mail\SzovegesLevel;
use App\Models\Category;
use App\Models\ProgramManager;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * „Felhasználók” menücsoport: admin-felhasználók + programgazdák, üdvözlő levél, elfelejtett jelszó.
 */
class FelhasznalokTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
        Settings::save([
            'festival' => ['start_date' => '2027-08-25', 'end_date' => '2027-08-29', 'prep_days' => 2,
                'opening_day' => '2027-08-25', 'name_hu' => 'Vásárhelyi Forgatag', 'name_ro' => 'Vâltoarea Mureșeană', 'day_cut_hour' => 3],
            'submission' => ['open' => true, 'closed_message' => 'Zárva.', 'deadline' => null],
            'notifications' => ['topics' => ['program_submission' => ['koordinator@forgatag.test']]],
        ]);
        Mail::fake();
        Category::create(['slug' => 'borkert', 'name_hu' => 'Borkert', 'sort' => 1]);
        Category::create(['slug' => 'tekaforgatag', 'name_hu' => 'TékaForgatag', 'sort' => 2]);
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@forgatag.test', 'password' => 'admin-jelszo-hosszu']);
    }

    /** @return array{0: SzovegesLevel, 1: string} az elküldött levél és a törzse */
    private function level(string $cimzett): array
    {
        $talalt = Mail::sent(SzovegesLevel::class, fn ($m) => $m->hasTo($cimzett))->last();
        $this->assertNotNull($talalt, "Nem ment levél ide: {$cimzett}");

        return [$talalt, $talalt->szoveg];
    }

    private function letrehoz(array $adat)
    {
        $this->actingAs($this->admin);

        return Livewire::test(CreateProgramManager::class)
            ->fillForm($adat)
            ->call('create');
    }

    public function test_a_ket_lap_a_felhasznalok_csoportban_el(): void
    {
        $this->actingAs($this->admin);
        $this->get('/admin/users')->assertOk()->assertSee('Admin-felhasználók')->assertSee('Programgazdák')->assertSee('Felhasználók');
        $this->get('/admin/program-managers')->assertOk();
    }

    public function test_ures_jelszonal_general_es_udvozlo_levelet_kuld(): void
    {
        $this->letrehoz([
            'name' => 'Teleki Téka', 'email' => 'Teka@Example.test', 'active' => true,
            'categories' => Category::pluck('id')->all(),
        ])->assertHasNoFormErrors()->assertNotified('A programgazda létrejött');

        $beku = ProgramManager::sole();
        $this->assertSame('teka@example.test', $beku->email);
        $this->assertCount(2, $beku->categories);

        [$level, $szoveg] = $this->level('teka@example.test');
        $this->assertSame('Vásárhelyi Forgatag – programgazda hozzáférés', $level->subject);
        $this->assertTrue($level->hasReplyTo('koordinator@forgatag.test'));
        $this->assertStringContainsString('E-mail (belépési azonosító): teka@example.test', $szoveg);
        $this->assertStringContainsString('/bekuldes/borkert', $szoveg);
        $this->assertStringContainsString('/bekuldes/tekaforgatag', $szoveg);

        // A levélben lévő jelszó valóban működik — és olvashatóan sehol nincs tárolva.
        $this->assertMatchesRegularExpression('/^Jelszó: ([2-9A-Za-z]{12})$/m', $szoveg);
        preg_match('/^Jelszó: (\S+)$/m', $szoveg, $m);
        $this->assertTrue(Hash::check($m[1], $beku->password));
        $this->assertNotSame($m[1], $beku->getRawOriginal('password'));
    }

    public function test_kezzel_megadott_jelszo_is_a_levelbe_kerul(): void
    {
        $this->letrehoz(['name' => 'Más', 'email' => 'mas@example.test', 'password' => 'sajat-jelszo-1', 'active' => true])
            ->assertHasNoFormErrors();
        [, $szoveg] = $this->level('mas@example.test');
        $this->assertStringContainsString('Jelszó: sajat-jelszo-1', $szoveg);
        $this->assertStringContainsString('/bekuldes/belepes', $szoveg); // kategória nélkül: a belépőlap
    }

    public function test_levelhibanal_a_koordinator_latja_a_jelszot(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP nem elérhető'));
        $this->letrehoz(['name' => 'Hibás', 'email' => 'hibas@example.test', 'password' => 'atadando-jelszo', 'active' => true])
            ->assertHasNoFormErrors()
            ->assertNotified('A fiók létrejött, de az üdvözlő levél NEM ment ki');
        $this->assertSame(1, ProgramManager::count());
    }

    public function test_foglalt_email_hibat_ad(): void
    {
        ProgramManager::create(['name' => 'Első', 'email' => 'egy@example.test', 'password' => 'x-jelszo-hosszu', 'active' => true]);
        $this->letrehoz(['name' => 'Második', 'email' => 'egy@example.test', 'active' => true])
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_elfelejtett_jelszo_teljes_folyamat(): void
    {
        $beku = ProgramManager::create(['name' => 'Téka', 'email' => 'teka@example.test', 'password' => 'regi-jelszo-12', 'active' => true]);

        $this->get('/bekuldes/belepes')->assertSee('Elfelejtett jelszó?');
        $this->post('/bekuldes/jelszo', ['email' => 'TEKA@example.test'])->assertSessionHas('siker');
        [$level, $szoveg] = $this->level('teka@example.test');
        $this->assertStringContainsString('Új jelszó beállítása', $level->subject);
        preg_match('#/bekuldes/jelszo/uj/([^?\s]+)\?email=#', $szoveg, $m);
        $this->assertNotEmpty($m, 'Nincs link a levélben');
        $token = $m[1];

        $this->get("/bekuldes/jelszo/uj/{$token}?email=teka%40example.test")->assertOk()->assertSee('Új jelszó');

        // Rövid / eltérő jelszó → érthető hiba.
        $this->post('/bekuldes/jelszo/uj', ['token' => $token, 'email' => 'teka@example.test', 'password' => 'rovid', 'password_confirmation' => 'rovid'])
            ->assertSessionHasErrors(['password' => 'A jelszó legalább 10 karakter legyen.']);
        $this->post('/bekuldes/jelszo/uj', ['token' => $token, 'email' => 'teka@example.test', 'password' => 'uj-jelszo-hosszu', 'password_confirmation' => 'masik-jelszo'])
            ->assertSessionHasErrors(['password' => 'A két jelszó nem egyezik.']);

        $this->post('/bekuldes/jelszo/uj', ['token' => $token, 'email' => 'teka@example.test', 'password' => 'uj-jelszo-hosszu', 'password_confirmation' => 'uj-jelszo-hosszu'])
            ->assertRedirect('/bekuldes/belepes')->assertSessionHas('siker');
        $this->assertTrue(Hash::check('uj-jelszo-hosszu', $beku->fresh()->password));

        // A link egyszer használható.
        $this->post('/bekuldes/jelszo/uj', ['token' => $token, 'email' => 'teka@example.test', 'password' => 'harmadik-jelszo', 'password_confirmation' => 'harmadik-jelszo'])
            ->assertSessionHasErrors('password');

        // Az új jelszóval be lehet lépni.
        $this->post('/bekuldes/belepes', ['email' => 'teka@example.test', 'password' => 'uj-jelszo-hosszu'])->assertRedirect();
        $this->assertAuthenticatedAs($beku->fresh(), 'bekuldo');
    }

    public function test_ismeretlen_vagy_kikapcsolt_cim_ugyanazt_a_valaszt_kapja_level_nelkul(): void
    {
        ProgramManager::create(['name' => 'Ki', 'email' => 'ki@example.test', 'password' => 'x-jelszo-hosszu', 'active' => false]);
        foreach (['nincs@example.test', 'ki@example.test'] as $cim) {
            $this->post('/bekuldes/jelszo', ['email' => $cim])
                ->assertSessionHas('siker', \App\Http\Controllers\Bekuldes\JelszoController::KULDVE);
        }
        Mail::assertNothingSent();
    }

    public function test_admin_sajat_magat_nem_torolheti(): void
    {
        $masik = User::create(['name' => 'Másik', 'email' => 'masik@forgatag.test', 'password' => 'masik-jelszo-hosszu']);
        $this->actingAs($this->admin);
        Livewire::test(EditUser::class, ['record' => $this->admin->getKey()])->assertActionHidden('delete');
        Livewire::test(EditUser::class, ['record' => $masik->getKey()])->assertActionVisible('delete');
    }

    public function test_programgazda_nem_eri_el_az_admin_lapokat(): void
    {
        $beku = ProgramManager::create(['name' => 'Téka', 'email' => 'teka@example.test', 'password' => 'regi-jelszo-12', 'active' => true]);
        $this->actingAs($beku, 'bekuldo')->get('/admin/program-managers')->assertRedirect('/admin/login');
    }

    public function test_az_ideiglenes_parancs_kivezetve(): void
    {
        $this->assertArrayNotHasKey('forgatag:programgazda', Artisan::all());
        $this->assertStringNotContainsString('programgazda', file_get_contents(base_path('.cpanel.yml')));
    }
}
