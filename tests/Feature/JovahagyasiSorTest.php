<?php

namespace Tests\Feature;

use App\Enums\ProgramSource;
use App\Enums\ProgramStatus;
use App\Enums\ReviewAction;
use App\Enums\TranslatableField;
use App\Enums\TranslationStatus;
use App\Filament\Resources\ProgramItems\Pages\ListProgramItems;
use App\Filament\Resources\ProgramItems\Pages\ViewProgramItem;
use App\Mail\SzovegesLevel;
use App\Models\Category;
use App\Models\ProgramItem;
use App\Models\ProgramManager;
use App\Models\ProgramReviewEvent;
use App\Models\User;
use App\Models\Venue;
use App\Services\ProgramReview;
use App\Services\ReviewStepRefused;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Programlista + jóváhagyási sor: a fotó csak adminnak, a lépések, a levelek, a napló,
 * az elavult fordítás jelzése.
 */
class JovahagyasiSorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProgramManager $beku;

    private Category $borkert;

    private Category $teka;

    private Venue $var;

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
        Storage::fake('local');
        Mail::fake();
        $this->borkert = Category::create(['slug' => 'borkert', 'name_hu' => 'Borkert', 'sort' => 1]);
        $this->teka = Category::create(['slug' => 'tekaforgatag', 'name_hu' => 'TékaForgatag', 'sort' => 2]);
        $this->var = Venue::create(['slug' => 'var', 'name' => 'Vár']);
        $this->admin = User::create(['name' => 'Koordinátor', 'email' => 'admin@forgatag.test', 'password' => 'admin-jelszo-hosszu', 'role' => 'superadmin']);
        $this->beku = ProgramManager::create(['name' => 'Teleki Téka', 'email' => 'teka@example.test', 'password' => 'titok-jelszo', 'active' => true]);
        $this->beku->categories()->attach([$this->borkert->id, $this->teka->id]);
    }

    private function program(array $felul = [], bool $fotoval = true): ProgramItem
    {
        $ut = null;
        if ($fotoval) {
            $ut = 'bekuldesek/' . uniqid() . '.jpg';
            Storage::disk('local')->put($ut, 'KEPADAT');
        }
        $p = ProgramItem::create(array_replace([
            'title' => 'Borkóstoló a várban', 'description' => str_repeat('Leírás ', 12),
            'category_id' => $this->borkert->id, 'venue_id' => $this->var->id,
            'source' => ProgramSource::PublicForm, 'program_manager_id' => $this->beku->id,
            'contact_name' => 'Teleki Téka', 'contact_email' => 'teka@example.test',
            'photo_rights_declared' => true, 'photo_path' => $ut,
        ], $felul));
        $p->occurrences()->create(['date' => '2027-08-26', 'start_time' => '18:00', 'end_time' => '20:00']);

        return $p;
    }

    /** @return SzovegesLevel|null az utolsó levél ide */
    private function level(string $cimzett): ?SzovegesLevel
    {
        return Mail::sent(SzovegesLevel::class, fn ($m) => $m->hasTo($cimzett))->last();
    }

    // ---- A fotó: csak belépett adminnak ----

    public function test_a_foto_csak_belepett_adminnak_jon(): void
    {
        $p = $this->program();
        $url = "/admin/programok/{$p->id}/foto";

        $this->get($url)->assertRedirect('/admin/login');
        $this->actingAs($this->beku, 'bekuldo')->get($url)->assertRedirect('/admin/login');

        $valasz = $this->actingAs($this->admin, 'web')->get($url)->assertOk();
        $this->assertSame('KEPADAT', $valasz->streamedContent());
        $this->assertStringContainsString('private', $valasz->headers->get('Cache-Control'));
    }

    public function test_hianyzo_fotofajl_404_es_a_lapon_latszik(): void
    {
        $p = $this->program();
        Storage::disk('local')->delete($p->photo_path);

        $this->actingAs($this->admin)->get("/admin/programok/{$p->id}/foto")->assertNotFound();
        Livewire::test(ViewProgramItem::class, ['record' => $p->id])
            ->assertSee('A fotó fájlja hiányzik a tárhelyről');
    }

    public function test_a_program_lapja_mutatja_a_fotot(): void
    {
        $p = $this->program();
        $this->actingAs($this->admin);
        Livewire::test(ViewProgramItem::class, ['record' => $p->id])
            ->assertSee("/admin/programok/{$p->id}/foto", false)
            ->assertSee('Borkóstoló a várban');
    }

    // ---- A lépések ----

    public function test_a_teljes_ut_kuldes_jovahagyas_level_naplo(): void
    {
        $p = $this->program();
        $this->actingAs($this->admin);

        Livewire::test(ViewProgramItem::class, ['record' => $p->id])
            ->assertActionVisible('kuld')->assertActionHidden('jovahagy')
            ->callAction('kuld')->assertNotified('Jóváhagyásra küldve.')
            ->assertActionVisible('jovahagy')->assertActionEnabled('jovahagy')
            ->callAction('jovahagy')->assertNotified('Jóváhagyva, közzétéve.');

        $this->assertSame(ProgramStatus::Published, $p->fresh()->status);
        $level = $this->level('teka@example.test');
        $this->assertNotNull($level, 'A beküldő nem kapott jóváhagyási levelet.');
        $this->assertStringContainsString('jóváhagyták', $level->szoveg);
        $this->assertTrue($level->hasReplyTo('koordinator@forgatag.test'));

        $naplo = $p->reviewEvents()->get();
        $this->assertSame([ReviewAction::SentToReview, ReviewAction::Approved], $naplo->pluck('action')->all());
        $this->assertSame('Koordinátor', $naplo->last()->actor_name);
        $this->assertSame(ProgramStatus::PendingReview, $naplo->last()->from_status);
    }

    public function test_foto_nelkul_nem_hagyhato_jova(): void
    {
        $p = $this->program(['status' => ProgramStatus::PendingReview], fotoval: false);
        $this->actingAs($this->admin);

        Livewire::test(ViewProgramItem::class, ['record' => $p->id])
            ->assertSee('Ez a program még nem hagyható jóvá')
            ->assertSee('Nincs fotó')
            ->assertActionDisabled('jovahagy');

        // A szerver is nemet mond (nem csak a gomb szürke).
        $this->expectException(ReviewStepRefused::class);
        ProgramReview::approve($p, $this->admin);
    }

    public function test_vazlatbol_nem_lehet_egybol_kozzetenni(): void
    {
        $p = $this->program();
        $this->expectException(ReviewStepRefused::class);
        ProgramReview::approve($p, $this->admin);
    }

    public function test_ha_a_level_nem_megy_ki_az_latszik(): void
    {
        $p = $this->program(['status' => ProgramStatus::PendingReview]);
        $this->actingAs($this->admin);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP nem elérhető'));

        Livewire::test(ViewProgramItem::class, ['record' => $p->id])
            ->callAction('jovahagy')
            ->assertNotified('Jóváhagyva, DE A LEVÉL NEM MENT KI.');
        $this->assertSame(ProgramStatus::Published, $p->fresh()->status);
    }

    public function test_visszakuldes_indokkal_level_es_a_bekuldo_latja(): void
    {
        $p = $this->program(['status' => ProgramStatus::PendingReview]);
        $this->actingAs($this->admin);

        Livewire::test(ViewProgramItem::class, ['record' => $p->id])
            ->callAction('vissza', ['indok' => ''])->assertHasActionErrors(['indok' => 'required']);
        Livewire::test(ViewProgramItem::class, ['record' => $p->id])
            ->callAction('vissza', ['indok' => 'A fotón nem a program látszik.'])
            ->assertNotified('Visszaküldve javításra.');

        $this->assertSame(ProgramStatus::Draft, $p->fresh()->status);
        $level = $this->level('teka@example.test');
        $this->assertNotNull($level);
        $this->assertStringContainsString('A fotón nem a program látszik.', $level->szoveg);
        $this->assertStringContainsString("/bekuldes/program/{$p->id}/javitas", $level->szoveg);

        // A beküldő a saját listáján látja az indokot.
        $this->actingAs($this->beku, 'bekuldo')->get('/bekuldes/sajat')
            ->assertSee('Javítást kérnek')->assertSee('A fotón nem a program látszik.');
        $this->assertSame('A fotón nem a program látszik.', $p->fresh()->openReturn()?->note);
    }

    public function test_belso_programot_nem_lehet_visszakuldeni(): void
    {
        $p = $this->program(['source' => ProgramSource::Internal, 'contact_email' => null, 'program_manager_id' => null]);
        $this->actingAs($this->admin);
        Livewire::test(ViewProgramItem::class, ['record' => $p->id])->assertActionHidden('vissza');
    }

    public function test_a_bekuldes_es_a_javitas_is_naploba_kerul(): void
    {
        $p = $this->program();
        ProgramReview::log($p, ReviewAction::Submitted, manager: $this->beku);
        ProgramReview::returnForFix($p, $this->admin, 'Pontosítsd a kezdést.');
        $this->assertNotNull($p->openReturn());

        // A beküldő javítása után a visszaküldés már nem „nyitott”.
        ProgramReview::log($p, ReviewAction::Resubmitted, manager: $this->beku);
        $this->assertNull($p->openReturn());
        $this->assertSame('Teleki Téka', $p->reviewEvents()->first()->actor_name);
    }

    public function test_a_naplo_nem_modosithato_es_nem_torolheto(): void
    {
        $e = ProgramReview::log($this->program(), ReviewAction::Submitted, manager: $this->beku);
        try {
            $e->update(['note' => 'átírva']);
            $this->fail('A naplósor módosítható volt.');
        } catch (LogicException) {
        }
        $this->expectException(LogicException::class);
        $e->delete();
    }

    // ---- A lista ----

    public function test_lista_fulek_es_szurok(): void
    {
        $var = $this->program(['title' => 'Vár-program', 'status' => ProgramStatus::PendingReview]);
        $kint = $this->program(['title' => 'Kint-program', 'status' => ProgramStatus::Published]);
        $teka = $this->program(['title' => 'Téka-program', 'category_id' => $this->teka->id]);
        $this->actingAs($this->admin);

        // Alapból a „Teendő” fül: ami jóváhagyásra vár, az elöl.
        Livewire::test(ListProgramItems::class)
            ->assertCanSeeTableRecords([$var, $teka], inOrder: true)
            ->assertCanNotSeeTableRecords([$kint]);

        Livewire::test(ListProgramItems::class)->set('activeTab', 'mind')
            ->filterTable('category', $this->teka->id)
            ->assertCanSeeTableRecords([$teka])->assertCanNotSeeTableRecords([$var, $kint]);

        $masnap = $this->program(['title' => 'Másnapi']);
        $masnap->occurrences()->update(['date' => '2027-08-28']);
        Livewire::test(ListProgramItems::class)->set('activeTab', 'mind')
            ->filterTable('nap', '2027-08-28')
            ->assertCanSeeTableRecords([$masnap])->assertCanNotSeeTableRecords([$var, $teka]);
    }

    // ---- Elavult fordítás: feltűnően ----

    public function test_az_elavult_forditas_feltunoen_latszik(): void
    {
        $p = $this->program(['status' => ProgramStatus::PendingReview]);
        $p->translations()->create(['field' => TranslatableField::Title, 'translated_text' => 'Degustare de vin',
            'status' => TranslationStatus::Outdated]);
        $tiszta = $this->program(['title' => 'Tiszta']);
        $this->actingAs($this->admin);

        Livewire::test(ListProgramItems::class)
            ->assertSee('Elavult fordítás')
            ->filterTable('elavult', true)
            ->assertCanSeeTableRecords([$p])->assertCanNotSeeTableRecords([$tiszta]);

        Livewire::test(ViewProgramItem::class, ['record' => $p->id])
            ->assertSee('Elavult román fordítás')
            ->assertSee('ELAVULT — a magyar szöveg változott');
    }
}
