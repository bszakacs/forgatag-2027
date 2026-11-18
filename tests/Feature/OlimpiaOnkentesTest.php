<?php

namespace Tests\Feature;

use App\Enums\OlympicsStatus;
use App\Enums\UserRole;
use App\Enums\VolunteerStatus;
use App\Filament\Pages\Beallitasok;
use App\Models\OlympicsTeam;
use App\Models\User;
use App\Models\VolunteerApplication;
use App\Services\OlimpiaJelentkezes;
use App\Support\LapSablon;
use App\Support\Settings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PROMPT-20: a két publikus jelentkező űrlap — Családi Olimpia és önkéntes. Az ellenőrző lista (PROMPT-20, 5. pont):
 * atomi színfoglalás az ADATBÁZISBAN, várólista + utólagos sorrend-javítás, visszalépéskor felszabaduló szín, félig kitöltött tag,
 * 16 alatt a két fájl kötelező, a fájlok a ZÁRT tárban, zárt végpont, méz-bödön, gyakorisági korlát, és a jog (kiskorúak adatai).
 */
class OlimpiaOnkentesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $seed = json_decode((string) file_get_contents(base_path('seed/settings.json')), true);
        $seed['olympics']['open'] = true;
        $seed['olympics']['event_date'] = '2027-08-28';
        $seed['volunteer']['open'] = true;
        $seed['volunteer']['days'] = [
            ['date' => '2027-08-24', 'note' => 'nulladik nap'], ['date' => '2027-08-25', 'note' => ''], ['date' => '2027-08-30', 'note' => 'utómunkálatok'],
        ];
        $seed['notifications']['topics']['olympics'] = ['olimpia@forgatag.test'];
        $seed['notifications']['topics']['volunteer'] = ['onkentes@forgatag.test'];
        Settings::save(array_filter($seed, fn ($k) => ! str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY));
        LapSablon::szinkron(); // az „olimpia” és „onkentes” rendszerlap a szövegeivel
        Storage::fake('local');
        Mail::fake();
    }

    /** @return array<string, string> */
    private function csapat(string $nev = 'Kék Bálnák', string $szin = 'kek'): array
    {
        $d = ['team_name' => $nev, 'captain_phone' => '+40 700 123 456', 'captain_email' => 'kapitany@example.test', 'color' => $szin, 'declaration' => '1'];
        for ($i = 1; $i <= 5; $i++) {
            $d["member{$i}_name"] = "Tag {$i}";
            $d["member{$i}_age"] = (string) (5 + $i * 6);
        }

        return $d;
    }

    private function olimpia(array $d)
    {
        return $this->postJson('/olimpia', $d);
    }

    private function szin(string $kulcs, string $nev = 'Előző csapat', int $ev = 2027): OlympicsTeam
    {
        return OlympicsTeam::create(['year' => $ev, 'team_name' => $nev, 'color_key' => $kulcs, 'color_name' => ucfirst($kulcs),
            'members' => [], 'captain_phone' => '1', 'captain_email' => 'a@b.c', 'declaration' => true, 'declaration_text' => 'x',
            'status' => OlympicsStatus::Applied]);
    }

    // ------------------------------------------------------------------ OLIMPIA

    public function test_a_szin_egyedisege_az_adatbazis_megszoritasa(): void
    {
        $this->szin('piros');
        $this->szin('piros', 'Tavalyi', 2026); // más évben ugyanaz a szín szabad
        $this->expectException(UniqueConstraintViolationException::class);
        $this->szin('piros', 'Második');
    }

    public function test_utkozeskor_emberi_uzenet_friss_szinlista_es_nem_ment(): void
    {
        $this->szin('piros');
        $v = $this->olimpia($this->csapat('Késők', 'piros'))->assertStatus(409)
            ->assertJsonPath('colorTaken', true)
            ->assertJsonPath('errors.0', 'A(z) „Piros" színt az előbb elvitte egy másik csapat. Válassz másik színt — a beírt adatok megmaradnak.');
        $this->assertStringContainsString('elfogyott', $v->json('colorsHtml'));
        $this->assertSame(1, OlympicsTeam::count());
        Mail::assertNothingSent();
    }

    public function test_sikeres_jelentkezes_visszaigazolas_es_koordinatori_level(): void
    {
        $this->olimpia($this->csapat())->assertOk()->assertJsonPath('ok', true)->assertJsonPath('waitlist', false);
        $t = OlympicsTeam::sole();
        $this->assertSame('kek', $t->color_key);
        $this->assertSame('Kék', $t->color_name);
        $this->assertSame(OlympicsStatus::Applied, $t->status);
        $this->assertStringStartsWith('Felelősségvállalási nyilatkozat:', $t->declaration_text); // a bizonyíték a rekordon
        Mail::assertSent(Mailable::class, fn (Mailable $m) => $m->hasTo('kapitany@example.test') && str_contains($m->subject, 'Megkaptuk a jelentkezéseteket'));
        Mail::assertSent(Mailable::class, fn (Mailable $m) => $m->hasTo('olimpia@forgatag.test') && str_contains($m->subject, 'Új olimpia-jelentkezés'));
    }

    public function test_a_letszamkorlat_utan_varolista(): void
    {
        Settings::save(['olympics' => ['max_teams' => 2] + Settings::block('olympics')]);
        $this->olimpia($this->csapat('Egy', 'kek'))->assertJsonPath('waitlist', false);
        $this->olimpia($this->csapat('Kettő', 'zold'))->assertJsonPath('waitlist', false);
        $this->olimpia($this->csapat('Három', 'lila'))->assertOk()->assertJsonPath('waitlist', true)
            ->assertJsonPath('message', 'A jelentkezést megkaptuk, de a 2 induló hely már betelt — a csapat VÁRÓLISTÁRA került. Ha valaki visszalép, a sorban következő csapatot értesítjük.');
        $this->assertSame(OlympicsStatus::Waitlist, OlympicsTeam::where('team_name', 'Három')->first()->status);
        $this->get('/olimpia')->assertSee('A 2 induló hely már betelt.');
    }

    public function test_versenyzo_jelentkezesek_sorrendje_utolag_helyreall(): void
    {
        Settings::save(['olympics' => ['max_teams' => 2] + Settings::block('olympics')]);
        // Két jelentkezés „egyszerre” számolt (mindkettő applied-et kapott), plusz egy kézzel előléptetett.
        $a = $this->szin('kek', 'A');
        $b = $this->szin('zold', 'B');
        $kezi = $this->szin('lila', 'Kézi');
        $kezi->update(['status_manual' => true]);
        $c = $this->szin('piros', 'C');

        $this->assertSame(1, OlimpiaJelentkezes::helyreTesz(2027));
        $this->assertSame(OlympicsStatus::Applied, $a->fresh()->status);
        $this->assertSame(OlympicsStatus::Applied, $b->fresh()->status);
        $this->assertSame(OlympicsStatus::Applied, $kezi->fresh()->status, 'a kézzel állítotthoz nem nyúl');
        $this->assertSame(OlympicsStatus::Waitlist, $c->fresh()->status, 'a KÉSŐBBI kerül várólistára');
        $this->assertSame(0, OlimpiaJelentkezes::helyreTesz(2027), 'nem léptet elő, és másodszorra nincs mit javítani');
    }

    public function test_visszalepeskor_a_szin_felszabadul(): void
    {
        $t = $this->szin('piros');
        $t->update(['status' => OlympicsStatus::Withdrawn]);
        $this->assertNull($t->fresh()->color_key);
        $this->assertSame('Piros', $t->fresh()->color_name); // látszik, mi volt
        $this->olimpia($this->csapat('Új piros', 'piros'))->assertOk();
    }

    public function test_felig_kitoltott_csapattag_hiba(): void
    {
        $d = $this->csapat();
        $d['member6_name'] = 'Hatodik';
        $d['member7_age'] = '9';
        $this->olimpia($d)->assertStatus(422)->assertJsonPath('errors', [
            '6. csapattag: ha megadod a nevet, az életkort is add meg.',
            '7. csapattag: ha megadod az életkort, a nevet is add meg.',
        ]);
        $d = $this->csapat();
        unset($d['member3_age']);
        $this->olimpia($d)->assertStatus(422)->assertJsonPath('errors', ['3. csapattag: add meg az életkort (szám).']);
        $this->assertSame(0, OlympicsTeam::count());
    }

    public function test_zarva_a_vegpont_is_nemet_mond(): void
    {
        Settings::save(['olympics' => ['open' => false] + Settings::block('olympics')]);
        $this->olimpia($this->csapat())->assertForbidden()->assertJsonPath('closed', true)
            ->assertJsonPath('errors.0', 'A Családi Olimpia jelentkezés jelenleg zárva van.');
        $this->get('/olimpia')->assertOk()->assertSee('Jelentkezés lezárult')->assertDontSee('id="form"', false);
        $this->assertSame(0, OlympicsTeam::count());
    }

    public function test_mez_bodon_latszolagos_siker_de_nincs_mentes(): void
    {
        $this->olimpia($this->csapat() + ['website' => 'http://spam.example'])->assertOk()->assertJsonPath('ok', true);
        $this->assertSame(0, OlympicsTeam::count());
        Mail::assertNothingSent();
    }

    public function test_gyakorisagi_korlat(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->olimpia(['team_name' => ''])->assertStatus(422);
        }
        $this->olimpia($this->csapat())->assertStatus(429)->assertJsonPath('errors.0', 'Túl sok beküldés rövid idő alatt. Próbáld pár perc múlva.');
        $this->assertSame(0, OlympicsTeam::count());
    }

    public function test_a_bevezeto_a_beallitasokbol_szamol(): void
    {
        $this->get('/olimpia')->assertOk()
            ->assertSee('2027. augusztus 28-án, szombaton')
            ->assertSee('Legtöbb 6 csapatot', false)
            ->assertSee('Felelősségvállalási nyilatkozat:')
            ->assertSee('name="website"', false);
    }

    // ------------------------------------------------------------------ ÖNKÉNTES

    /** @return array<string, mixed> */
    private function onkentes(int $kor = 20): array
    {
        return ['full_name' => 'Próba Péter', 'age' => (string) $kor, 'email' => 'peter@example.test', 'was_volunteer' => 'no',
            'motivation' => 'Szeretek segíteni.', 'available_days' => ['2027-08-24'], 'areas' => ['Infó pont'], 'has_license' => 'no',
            'health_notes' => 'Pollenallergia', 'emergency_name' => 'Anya', 'emergency_phone' => '+40 700 000 001', 'privacy_consent' => '1'];
    }

    private function pdf(string $nev = 'hozzajarulas.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nev, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    }

    public function test_16_alatt_a_ket_fajl_kotelezo(): void
    {
        $this->post('/onkentes', $this->onkentes(14), ['Accept' => 'application/json'])->assertStatus(422)
            ->assertJsonPath('errors', ['A(z) szülői beleegyezés feltöltése kötelező.', 'A(z) motivációs levél feltöltése kötelező.']);
        $this->post('/onkentes', $this->onkentes(14) + ['parental_file' => $this->pdf()], ['Accept' => 'application/json'])->assertStatus(422)
            ->assertJsonPath('errors', ['A(z) motivációs levél feltöltése kötelező.']);
        $this->assertSame(0, VolunteerApplication::count());
    }

    public function test_a_fajlok_a_zart_tarba_kerulnek_es_a_tipus_a_tartalombol_dol_el(): void
    {
        Storage::fake('mediatar');
        // „PDF” kiterjesztés, de a tartalma HTML → elutasítva
        $hamis = UploadedFile::fake()->createWithContent('gonosz.pdf', '<html><script>alert(1)</script></html>');
        $this->post('/onkentes', $this->onkentes(14) + ['parental_file' => $hamis, 'motivation_file' => $this->pdf('b.pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('errors', ['A(z) szülői beleegyezés csak PDF, JPG vagy PNG lehet.']);

        $this->post('/onkentes', $this->onkentes(14) + ['parental_file' => $this->pdf(), 'motivation_file' => $this->pdf('levél.pdf')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('ok', true);
        $a = VolunteerApplication::sole();
        $this->assertStringStartsWith('onkentesek/2027/', $a->parental_consent_path);
        $this->assertStringNotContainsString('hozzajarulas', $a->parental_consent_path); // a nevét mi adjuk
        Storage::disk('local')->assertExists([$a->parental_consent_path, $a->motivation_letter_path]);
        $this->assertSame([], Storage::disk('mediatar')->allFiles()); // a nyilvános médiatárba SEMMI
        $this->assertSame('application/pdf', $a->parental_consent_mime);
        $this->assertSame(VolunteerStatus::New, $a->status);
        $this->assertSame(\App\Support\Onkentes::HOZZAJARULAS, $a->privacy_text);
        // A dokumentum webről nem érhető el (a lemez nyilvános címe sincs) — csak az admin útvonalán.
        $this->get('/storage/' . $a->parental_consent_path)->assertStatus(403);
        Mail::assertSent(Mailable::class, fn (Mailable $m) => $m->hasTo('onkentes@forgatag.test') && ! str_contains($m->szoveg, 'Pollenallergia'));

        // törléskor a fájlok is mennek
        $a->delete();
        Storage::disk('local')->assertMissing($a->parental_consent_path);
    }

    public function test_16_felett_opcionalis_de_a_formatum_akkor_is_ellenorzott(): void
    {
        $this->post('/onkentes', $this->onkentes(17), ['Accept' => 'application/json'])->assertOk();
        $rossz = UploadedFile::fake()->createWithContent('x.pdf', 'nem pdf');
        $this->post('/onkentes', $this->onkentes(17) + ['motivation_file' => $rossz], ['Accept' => 'application/json'])->assertStatus(422)
            ->assertJsonPath('errors', ['A(z) motivációs levél csak PDF, JPG vagy PNG lehet.']);
    }

    public function test_a_napok_a_sajat_listabol_nem_a_programnapokbol(): void
    {
        // a 2027-08-27 programnap, de NEM önkéntes-nap ebben a listában
        $this->post('/onkentes', ['available_days' => ['2027-08-27']] + $this->onkentes(), ['Accept' => 'application/json'])->assertStatus(422)
            ->assertJsonPath('errors', ['Válassz legalább egy napot, amikor tudsz jönni.']);
        $this->get('/onkentes')->assertOk()->assertSee('aug. 24. (kedd – nulladik nap)')->assertSee('aug. 30. (hétfő – utómunkálatok)')
            ->assertDontSee('aug. 27.')->assertSee('2027. augusztus 24–30. között keresünk');
    }

    public function test_onkentes_zarva_es_mez_bodon(): void
    {
        $this->post('/onkentes', $this->onkentes() + ['website' => 'x'], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('ok', true);
        $this->assertSame(0, VolunteerApplication::count());
        Settings::save(['volunteer' => ['open' => false] + Settings::block('volunteer')]);
        $this->post('/onkentes', $this->onkentes(), ['Accept' => 'application/json'])->assertForbidden()->assertJsonPath('closed', true);
        $this->get('/onkentes')->assertSee('Az önkéntes jelentkezés lezárult.');
        $this->assertSame(0, VolunteerApplication::count());
        Mail::assertNothingSent();
    }

    // ------------------------------------------------------------------ ADMIN — JOG

    private function mint(UserRole $r): static
    {
        return $this->actingAs(User::create(['name' => $r->label(), 'email' => $r->value . '@forgatag.test',
            'password' => 'titok-jelszo-123', 'role' => $r]), 'web');
    }

    private function jelentkezo(): VolunteerApplication
    {
        $this->post('/onkentes', $this->onkentes(14) + ['parental_file' => $this->pdf(), 'motivation_file' => $this->pdf('b.pdf')], ['Accept' => 'application/json'])->assertOk();

        return VolunteerApplication::sole();
    }

    /** @return array<string, array{0: UserRole}> */
    public static function onkentestNemLatok(): array
    {
        return ['programkoordinátor' => [UserRole::Coordinator], 'kategóriagazda' => [UserRole::CategoryOwner], 'fordító' => [UserRole::Translator],
            'csak olvasó' => [UserRole::ReadOnly], 'olimpia-koordinátor' => [UserRole::OlympicsCoordinator], 'arculat' => [UserRole::ThemeEditor]];
    }

    // Szerepkörönként KÜLÖN futás (a munkamenet-őr egy teszten belüli felhasználóváltást kiléptetésnek venne).
    #[DataProvider('onkentestNemLatok')]
    public function test_az_onkentes_adatot_mas_nem_latja_kozvetlen_cimmel_sem(UserRole $r): void
    {
        $a = $this->jelentkezo();
        $this->mint($r);
        $this->get('/admin/onkentesek')->assertForbidden();
        $this->get("/admin/onkentesek/{$a->id}")->assertForbidden();
        $this->get("/admin/onkentes-dokumentum/{$a->id}/szuloi")->assertNotFound();
    }

    public function test_az_onkentes_koordinator_latja_es_megnyitja_a_dokumentumot(): void
    {
        $a = $this->jelentkezo();
        $this->mint(UserRole::VolunteerCoordinator);
        $this->get('/admin/onkentesek')->assertOk()->assertSee('Próba Péter');
        $this->get("/admin/onkentesek/{$a->id}")->assertOk()->assertSee('16 év alatti')->assertSee('Pollenallergia');
        $v = $this->get("/admin/onkentes-dokumentum/{$a->id}/szuloi")->assertOk();
        $this->assertSame('nosniff', $v->headers->get('X-Content-Type-Options'));
        $this->get('/admin/olimpia')->assertForbidden();   // csak az önkéntesekhez fér hozzá
        $this->get('/admin/programok')->assertForbidden();
    }

    /** @return array<string, array{0: UserRole}> */
    public static function olimpiatNemLatok(): array
    {
        return ['kategóriagazda' => [UserRole::CategoryOwner], 'fordító' => [UserRole::Translator], 'csak olvasó' => [UserRole::ReadOnly],
            'önkéntes-koordinátor' => [UserRole::VolunteerCoordinator]];
    }

    #[DataProvider('olimpiatNemLatok')]
    public function test_az_olimpiat_mas_nem_latja_kozvetlen_cimmel_sem(UserRole $r): void
    {
        $t = $this->szin('kek');
        $this->mint($r);
        $this->get('/admin/olimpia')->assertForbidden();
        $this->get("/admin/olimpia/{$t->id}")->assertForbidden();
    }

    public function test_az_olimpia_koordinator_kezeli_a_csapatokat(): void
    {
        $t = $this->szin('kek', 'Kék csapat');
        $this->mint(UserRole::OlympicsCoordinator);
        $this->get('/admin/olimpia')->assertOk()->assertSee('Kék csapat')->assertSee('induló hely: 1 / 6');
        $this->get("/admin/olimpia/{$t->id}")->assertOk()->assertSee('Visszalépett');
        $this->get('/admin/onkentesek')->assertForbidden();
        $this->get('/admin/programok')->assertForbidden();
    }

    // ------------------------------------------------------------------ BEÁLLÍTÁSOK

    public function test_ures_koordinatori_cim_pirosan_jelezve(): void
    {
        $this->mint(UserRole::SuperAdmin);
        Livewire::test(Beallitasok::class)
            ->assertSee('Családi Olimpia')->assertSee('Önkéntesek')
            ->assertDontSee('nincs koordinátori cím — senki nem kap levelet az új jelentkezésekről')
            ->set('data.notifications.topics.olympics', '')
            ->set('data.notifications.topics.volunteer', '')
            ->assertSee('FIGYELEM: nincs koordinátori cím — senki nem kap levelet az új jelentkezésekről')
            ->assertSee('FIGYELEM: nincs koordinátori cím — senki nem kap levelet az új önkéntes-jelentkezésekről');
    }

    public function test_nyitni_csak_napokkal_es_szinekkel_lehet(): void
    {
        $this->mint(UserRole::SuperAdmin);
        Livewire::test(Beallitasok::class)
            ->set('data.volunteer.days', [])
            ->set('data.olympics.event_date', null)
            ->call('save')
            ->assertHasFormErrors(['volunteer.open', 'olympics.open']);
    }

    public function test_a_kitolto_gomb_a_fesztivalnapokbol_tolti_ki_es_utana_nyithato(): void
    {
        $this->mint(UserRole::SuperAdmin);
        Livewire::test(Beallitasok::class)
            ->set('data.volunteer.days', [])
            ->callAction(\Filament\Actions\Testing\TestAction::make('onkentesNapokKitoltes')->schemaComponent('onkentes_napok_gombok', 'form'))
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(
            ['aug. 24. (kedd – nulladik nap)', 'aug. 25. (szerda)', 'aug. 26. (csütörtök)', 'aug. 27. (péntek)', 'aug. 28. (szombat)', 'aug. 29. (vasárnap)', 'aug. 30. (hétfő – utómunkálatok)'],
            array_column(\App\Support\Onkentes::napok(), 'label'),
        );
    }
}
