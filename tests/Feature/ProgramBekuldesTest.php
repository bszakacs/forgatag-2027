<?php

namespace Tests\Feature;

use App\Enums\ProgramSource;
use App\Enums\ProgramStatus;
use App\Models\Category;
use App\Models\ProgramItem;
use App\Models\ProgramManager;
use App\Models\User;
use App\Models\Venue;
use App\Support\Settings;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * A 2. fázis (programbeküldő űrlap) ellenőrző listája — PROMPT-02, 4. pont.
 */
class ProgramBekuldesTest extends TestCase
{
    use RefreshDatabase;

    private ProgramManager $beku;

    private Venue $helyszin;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
        Settings::save([
            'festival' => [
                'start_date' => '2027-08-25', 'end_date' => '2027-08-29', 'prep_days' => 2,
                'opening_day' => '2027-08-25', 'name_hu' => 'Vásárhelyi Forgatag', 'name_ro' => 'Vâltoarea Mureșeană',
                'day_cut_hour' => 3,
            ],
            'submission' => ['open' => true, 'closed_message' => 'A programbeküldés jelenleg zárva van.', 'deadline' => null, 'contact_email' => null],
            'notifications' => ['from_address' => 'program@forgatag.test', 'from_name' => 'Vásárhelyi Forgatag',
                'topics' => ['program_submission' => ['koordinator@forgatag.test']]],
        ]);
        Storage::fake('local');
        Mail::fake();

        foreach ([['borkert', 'Borkert'], ['koncertforgatag', 'Koncertforgatag'], ['tekaforgatag', 'TékaForgatag'], ['filmforgatag', 'Filmforgatag']] as $i => [$slug, $nev]) {
            Category::create(['slug' => $slug, 'name_hu' => $nev, 'sort' => $i]);
        }
        $this->helyszin = Venue::create(['slug' => 'var', 'name' => 'Vár']);
        $this->beku = ProgramManager::create(['name' => 'Teleki Téka', 'email' => 'teka@example.test', 'password' => 'titok-jelszo', 'active' => true]);
        $this->beku->categories()->attach(Category::whereIn('slug', ['borkert', 'koncertforgatag', 'tekaforgatag'])->pluck('id'));
    }

    /** Valódi (dekódolható) PNG — a GD nélkül is: nyers sorok + zlib. */
    private function png(int $w, int $h): UploadedFile
    {
        $chunk = fn (string $t, string $d) => pack('N', strlen($d)) . $t . $d . pack('N', crc32($t . $d));
        $sor = "\0" . str_repeat("\xE8\x5D\x9E", $w);
        $png = "\x89PNG\r\n\x1a\n"
            . $chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0))
            . $chunk('IDAT', gzcompress(str_repeat($sor, $h), 9))
            . $chunk('IEND', '');
        $ut = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($ut, $png);

        return new UploadedFile($ut, 'kep.png', 'image/png', null, true);
    }

    /** @return array<string, mixed> */
    private function adat(array $felul = []): array
    {
        return array_replace([
            'title' => 'Borkóstoló a várban',
            'organizer_names' => 'Teleki Téka',
            'start_time' => '18:00',
            'end_time' => '21:00',
            'schedule_note' => '',
            'recurrence_days' => ['2027-08-25', '2027-08-26'],
            'venue' => (string) $this->helyszin->id,
            'description' => str_repeat('Erdélyi borok kóstolója a vár udvarán, élőzenével. ', 3),
            'entry_type' => 'free',
            'ticket_info' => '',
            'photographer_credit' => 'Kiss Anna',
            'photo_rights_declared' => '1',
            'photo' => $this->png(1200, 800),
        ], $felul);
    }

    private function bekuld(string $slug, array $adat)
    {
        return $this->actingAs($this->beku, 'bekuldo')->post("/bekuldes/{$slug}", $adat, ['Accept' => 'application/json']);
    }

    public function test_belepes_nelkul_a_belepo_jon_es_a_vegpont_401(): void
    {
        $this->get('/bekuldes')->assertRedirect('/bekuldes/belepes');
        $this->post('/bekuldes/borkert', [], ['Accept' => 'application/json'])->assertStatus(401);
    }

    public function test_hibas_belepes_altalanos_uzenet_es_zarolas(): void
    {
        $this->post('/bekuldes/belepes', ['email' => 'nincs@ilyen.test', 'password' => 'x'])->assertSessionHasErrors(['email' => 'Hibás e-mail vagy jelszó.']);
        $this->post('/bekuldes/belepes', ['email' => 'teka@example.test', 'password' => 'rossz'])->assertSessionHasErrors(['email' => 'Hibás e-mail vagy jelszó.']);
        for ($i = 0; $i < 4; $i++) {
            $this->post('/bekuldes/belepes', ['email' => 'teka@example.test', 'password' => 'rossz']);
        }
        // Zárolva: még a JÓ jelszó sem enged be.
        $this->post('/bekuldes/belepes', ['email' => 'teka@example.test', 'password' => 'titok-jelszo'])
            ->assertSessionHasErrors(['email' => 'Túl sok sikertelen próbálkozás. Próbáld újra pár perc múlva.']);
        $this->assertGuest('bekuldo');
    }

    public function test_belepes_mukodik_es_az_adminba_nem_enged(): void
    {
        $this->post('/bekuldes/belepes', ['email' => 'TEKA@example.test', 'password' => 'titok-jelszo'])->assertRedirect();
        $this->assertAuthenticatedAs($this->beku, 'bekuldo');
        $this->assertGuest('web');
        $this->get('/admin')->assertRedirect('/admin/login');
        // Az admin belépőjén a programgazda fiókja nem létezik (külön jelszótár).
        $this->assertNull(User::where('email', 'teka@example.test')->first());
    }

    public function test_kategoria_extra_mezok_nelkul_beküldes_sikeres(): void
    {
        $this->bekuld('borkert', $this->adat())->assertOk()->assertJson(['ok' => true]);

        $p = ProgramItem::sole();
        $this->assertSame(ProgramStatus::Draft, $p->status);
        $this->assertSame(ProgramSource::PublicForm, $p->source);
        $this->assertSame($this->beku->id, $p->program_manager_id);
        $this->assertSame('teka@example.test', $p->contact_email);
        $this->assertCount(2, $p->occurrences);
        Storage::disk('local')->assertExists($p->photo_path);
        Mail::assertSent(Mailable::class, fn ($m) => $m->hasTo('koordinator@forgatag.test') && str_contains($m->subject, 'Új programbeküldés'));
        // A beküldő NEM beküldéskor kap levelet.
        Mail::assertNotSent(Mailable::class, fn ($m) => $m->hasTo('teka@example.test'));
    }

    public function test_kotelezo_extra_mezo_hianya(): void
    {
        $this->bekuld('koncertforgatag', $this->adat())->assertStatus(422)
            ->assertJsonFragment(['Hiányzó kötelező mező: Fellépő / zenekar neve.']);
        $this->bekuld('koncertforgatag', $this->adat(['extra' => ['performer_name' => 'Kicsi Hang']]))->assertOk();
        $this->assertSame(['performer_name' => 'Kicsi Hang'], ProgramItem::sole()->extra_fields);
    }

    public function test_konyvbemutato_feltetelesen_kotelezo(): void
    {
        $r = $this->bekuld('tekaforgatag', $this->adat(['extra' => ['is_book_launch' => '1', 'book_title' => 'Bolyai']]))->assertStatus(422);
        $r->assertJsonFragment(['Hiányzó kötelező mező: Szerző neve.']);
        $this->assertNotContains('Hiányzó kötelező mező: Könyv címe.', $r->json('errors'));
        // Könyvbemutató nélkül a könyv-mezők nem kötelezők.
        $this->bekuld('tekaforgatag', $this->adat(['extra' => []]))->assertOk();
    }

    public function test_ejfel_utani_vegzodes(): void
    {
        $this->bekuld('borkert', $this->adat(['start_time' => '23:00', 'end_time' => '00:30']))->assertOk();
        $this->bekuld('borkert', $this->adat(['start_time' => '23:00', 'end_time' => '04:00']))->assertStatus(422)
            ->assertJsonFragment(['Éjfél utáni befejezésnél a vége legkésőbb 03:00 lehet (a fesztiválnap hajnali 3 óráig tart).']);
        // A záró óra a Beállításokból jön — átírva a határ és a szöveg is vele mozog.
        Settings::save(['festival' => array_replace(Settings::block('festival'), ['day_cut_hour' => 5])]);
        $this->bekuld('borkert', $this->adat(['start_time' => '23:00', 'end_time' => '04:00']))->assertOk();
    }

    public function test_mas_kategoriajaba_nem_kuldhet(): void
    {
        $this->bekuld('filmforgatag', $this->adat())->assertStatus(403)
            ->assertJsonFragment(['Ebbe a kategóriába nem küldhetsz be programot. Ha szerinted ez hiba, keresd a szervezőt.']);
        $this->actingAs($this->beku, 'bekuldo')->get('/bekuldes/filmforgatag')->assertRedirect('/bekuldes');
        $this->assertSame(0, ProgramItem::count());
    }

    public function test_zart_allapotban_a_vegpont_is_nemet_mond(): void
    {
        Settings::save(['submission' => array_replace(Settings::block('submission'), ['open' => false])]);
        $this->bekuld('borkert', $this->adat())->assertStatus(403)->assertJson(['closed' => true, 'errors' => ['A programbeküldés jelenleg zárva van.']]);
        $this->assertSame(0, ProgramItem::count());
        $this->actingAs($this->beku, 'bekuldo')->get('/bekuldes')->assertSee('A programleadás lezárult');
    }

    public function test_lejart_hatarido_is_zar(): void
    {
        Settings::save(['submission' => array_replace(Settings::block('submission'), ['deadline' => '2020-01-01 00:00'])]);
        $this->bekuld('borkert', $this->adat())->assertStatus(403);
    }

    public function test_kicsi_foto_elutasitva(): void
    {
        $this->bekuld('borkert', $this->adat(['photo' => $this->png(1000, 800)]))->assertStatus(422)
            ->assertJsonFragment(['A fotó legalább 1200×800 pixel legyen (most 1000×800).']);
    }

    public function test_nem_kep_tartalom_elutasitva_akkor_is_ha_png_nevu(): void
    {
        $ut = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($ut, 'ez nem kep');
        $hamis = new UploadedFile($ut, 'kep.png', 'image/png', null, true);
        $this->bekuld('borkert', $this->adat(['photo' => $hamis]))->assertStatus(422)
            ->assertJsonFragment(['A fotó nem olvasható (érvénytelen kép).']);
    }

    public function test_feltoltesi_hiba_latszik_es_a_koordinator_jelzest_kap(): void
    {
        $hibas = new UploadedFile(tempnam(sys_get_temp_dir(), 'x'), 'kep.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);
        $this->bekuld('borkert', $this->adat(['photo' => $hibas]))->assertStatus(422)
            ->assertJsonFragment(['A fotó feltöltése nem sikerült (a szerver nem fogadta a fájlt). Próbáld újra; ha most sem megy, keresd a szervezőt.']);
        $this->assertSame(0, ProgramItem::count());
        Mail::assertSent(Mailable::class, fn ($m) => $m->hasTo('koordinator@forgatag.test') && str_contains($m->subject, 'Programfotó feltöltése nem sikerült'));
    }

    public function test_kereskorlat_feletti_foto_latszik_es_jelez(): void
    {
        $tul = (int) ini_get('post_max_size') * 1024 * 1024 + 1;
        $this->actingAs($this->beku, 'bekuldo')
            ->call('POST', '/bekuldes/borkert', [], [], [], ['CONTENT_LENGTH' => $tul, 'HTTP_ACCEPT' => 'application/json'])
            ->assertStatus(413)
            ->assertJsonFragment(['A fotó feltöltése nem sikerült: a fájl túl nagy a szervernek. A program NEM jött létre. Próbáld kisebb fájllal; ha most sem megy, keresd a szervezőt.']);
        Mail::assertSent(Mailable::class, fn ($m) => str_contains($m->subject, 'Programfotó feltöltése nem sikerült'));
    }

    public function test_tarolasi_hiba_eseten_a_program_nem_jon_letre(): void
    {
        $lemez = Mockery::mock(FilesystemAdapter::class);
        $lemez->shouldReceive('putFileAs')->andReturn(false);
        $lemez->shouldReceive('exists')->andReturn(false);
        Storage::set('local', $lemez);

        $this->bekuld('borkert', $this->adat())->assertStatus(503)
            ->assertJsonFragment(['A fotó mentése nem sikerült, ezért a program NEM jött létre. Próbáld újra pár perc múlva; ha most sem megy, keresd a szervezőt.']);
        $this->assertSame(0, ProgramItem::count());
        Mail::assertSent(Mailable::class, fn ($m) => str_contains($m->subject, 'Programfotó feltöltése nem sikerült'));
    }

    public function test_javitas_jovahagyasig_es_az_idopontok_helyben_frissulnek(): void
    {
        $this->bekuld('borkert', $this->adat(['recurrence_days' => ['2027-08-25', '2027-08-26', '2027-08-27']]))->assertOk();
        $p = ProgramItem::sole();
        $idk = $p->occurrences->mapWithKeys(fn ($o) => [$o->date->format('Y-m-d') => $o->id]);
        $regiFoto = $p->photo_path;

        // Fotó nélkül: a meglévő marad. A 27-e kimegy, a 28-a bejön, a vége 22:00 lesz.
        $adat = $this->adat(['end_time' => '22:00', 'recurrence_days' => ['2027-08-25', '2027-08-26', '2027-08-28']]);
        unset($adat['photo']);
        $this->actingAs($this->beku, 'bekuldo')->post("/bekuldes/program/{$p->id}", $adat, ['Accept' => 'application/json'])->assertOk();

        $uj = $p->fresh()->occurrences->mapWithKeys(fn ($o) => [$o->date->format('Y-m-d') => $o]);
        $this->assertSame($idk['2027-08-25'], $uj['2027-08-25']->id);
        $this->assertSame($idk['2027-08-26'], $uj['2027-08-26']->id);
        $this->assertFalse($uj->has('2027-08-27'));
        $this->assertNotContains($uj['2027-08-28']->id, $idk->all());
        $this->assertSame('22:00', substr($uj['2027-08-25']->end_time, 0, 5));
        $this->assertSame($regiFoto, $p->fresh()->photo_path);
        $this->actingAs($this->beku, 'bekuldo')->get('/bekuldes/sajat')->assertSee('Borkóstoló a várban')->assertSee('Javítás');
    }

    public function test_jovahagyas_utan_zarolt_es_a_bekuldo_levelet_kap(): void
    {
        Settings::save(['submission' => array_replace(Settings::block('submission'), ['contact_email' => 'program@forgatag.test'])]);
        $this->bekuld('borkert', $this->adat())->assertOk();
        // A koordinátori levélre adott válasz a beküldőhöz megy.
        Mail::assertSent(Mailable::class, fn ($m) => $m->hasTo('koordinator@forgatag.test') && $m->hasReplyTo('teka@example.test'));
        $p = ProgramItem::sole();
        $p->update(['status' => ProgramStatus::Published]);
        // A jóváhagyási levélre adott válasz a Beállítások kapcsolattartó címére megy (nem a noreply@-ra).
        Mail::assertSent(Mailable::class, fn ($m) => $m->hasTo('teka@example.test')
            && str_contains($m->subject, 'A programodat jóváhagytuk – Vásárhelyi Forgatag 2027')
            && $m->hasReplyTo('program@forgatag.test'));

        $adat = $this->adat();
        unset($adat['photo']);
        $this->actingAs($this->beku, 'bekuldo')->post("/bekuldes/program/{$p->id}", $adat, ['Accept' => 'application/json'])->assertStatus(409);
        $this->actingAs($this->beku, 'bekuldo')->get("/bekuldes/program/{$p->id}/javitas")->assertRedirect('/bekuldes/sajat');
    }

    public function test_mas_programjahoz_nem_fer_hozza(): void
    {
        $this->bekuld('borkert', $this->adat())->assertOk();
        $p = ProgramItem::sole();
        $mas = ProgramManager::create(['name' => 'Más', 'email' => 'mas@example.test', 'password' => 'jelszo-masik', 'active' => true]);
        $mas->categories()->attach(Category::where('slug', 'borkert')->value('id'));
        $this->actingAs($mas, 'bekuldo')->get("/bekuldes/program/{$p->id}/javitas")->assertNotFound();
        $this->actingAs($mas, 'bekuldo')->get("/bekuldes/program/{$p->id}/foto")->assertNotFound();
        $this->actingAs($mas, 'bekuldo')->post("/bekuldes/program/{$p->id}", [], ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_gyakorisagi_korlat(): void
    {
        RateLimiter::clear('bekuldes|127.0.0.1');
        for ($i = 0; $i < 30; $i++) {
            $this->bekuld('borkert', ['title' => 'x']);
        }
        $this->bekuld('borkert', ['title' => 'x'])->assertStatus(429);
    }

    public function test_levelhiba_nem_forditja_at_a_sikeres_mentest_hibaba(): void
    {
        // Mérve 2026-10-12: a levél (és a napló) hibája után a program bent volt, a beküldő mégis hibát látott.
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP nem elérhető'));
        $this->bekuld('borkert', $this->adat())->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(1, ProgramItem::count());
    }

    public function test_beallitasok_lap_figyelmeztet_ha_nincs_ertesitesi_cim(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@forgatag.test', 'password' => 'admin-jelszo']);
        $this->actingAs($admin)->get('/admin/beallitasok')->assertOk()->assertDontSee('nincs koordinátori értesítési cím');
        Settings::save(['notifications' => array_replace(Settings::block('notifications'), ['topics' => ['program_submission' => []]])]);
        $this->actingAs($admin)->get('/admin/beallitasok')->assertOk()->assertSee('nincs koordinátori értesítési cím');
    }

    public function test_ertesitesi_cim_enter_nelkul_is_mentodik(): void
    {
        // 2026-10-12, élesben: a címke-mezőbe Enter nélkül beírt cím mentéskor némán elveszett.
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@forgatag.test', 'password' => 'admin-jelszo']));
        \Livewire\Livewire::test(\App\Filament\Pages\Beallitasok::class)
            ->set('data.notifications.topics.program_submission', "koord@forgatag.test, masik@forgatag.test\n")
            ->call('save')
            ->assertHasNoErrors();
        Settings::flush();
        $this->assertSame(['koord@forgatag.test', 'masik@forgatag.test'], Settings::get('notifications.topics.program_submission'));

        \Livewire\Livewire::test(\App\Filament\Pages\Beallitasok::class)
            ->set('data.notifications.topics.program_submission', 'nem-cim')
            ->call('save')
            ->assertHasErrors();
    }

    public function test_hatarido_napjan_meg_nyitva(): void
    {
        Settings::save(['submission' => array_replace(Settings::block('submission'), ['deadline' => now()->format('Y-m-d')])]);
        $this->bekuld('borkert', $this->adat())->assertOk();
    }

    public function test_szovegtisztitas(): void
    {
        $this->bekuld('borkert', $this->adat(['title' => '  BORKÓSTOLÓ   A VÁRBAN 🍷 ']))->assertOk();
        $this->assertSame('Borkóstoló a várban', ProgramItem::sole()->title);
    }
}
