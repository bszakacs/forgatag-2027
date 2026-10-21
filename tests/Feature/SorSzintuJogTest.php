<?php

namespace Tests\Feature;

use App\Enums\ProgramSource;
use App\Enums\ProgramStatus;
use App\Enums\ReviewAction;
use App\Enums\TranslatableField;
use App\Enums\UserRole;
use App\Filament\Resources\ProgramItems\Pages\ListProgramItems;
use App\Filament\Resources\ProgramItems\Pages\ViewProgramItem;
use App\Filament\Resources\ProgramItems\ProgramItemResource;
use App\Filament\Resources\ProgramManagers\ProgramManagerResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Category;
use App\Models\ProgramItem;
use App\Models\ProgramItemTranslation;
use App\Models\ProgramManager;
use App\Models\ProgramOccurrence;
use App\Models\ProgramReviewEvent;
use App\Models\User;
use App\Models\Venue;
use App\Services\ProgramReview;
use App\Services\ReviewStepRefused;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sor-szintű jog (CLAUDE.md 4.): a kategóriagazda CSAK a saját kategóriája programjait látja és írhatja.
 * A hat felület egyenként (a hibája néma — ezért mindegyik külön teszt):
 *   1. lista  2. globális kereső  3. kapcsolatok  4. export (a tábla lekérdezése)  5. import (írás-őr)
 *   6. közvetlen cím (rekord-útvonal, fotó-útvonal)
 * + a szerepkör-mátrix (UserRole) és a policy-k.
 */
class SorSzintuJogTest extends TestCase
{
    use RefreshDatabase;

    private Category $borkert;

    private Category $teka;

    private Venue $var;

    private User $gazda;

    private ProgramItem $sajat;

    private ProgramItem $idegen;

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
        Filament::setCurrentPanel('admin');

        $this->borkert = Category::create(['slug' => 'borkert', 'name_hu' => 'Borkert', 'sort' => 1]);
        $this->teka = Category::create(['slug' => 'tekaforgatag', 'name_hu' => 'TékaForgatag', 'sort' => 2]);
        $this->var = Venue::create(['slug' => 'var', 'name' => 'Vár']);

        // A programok belépett felhasználó NÉLKÜL jönnek létre (mint egy beküldésnél) — nincs szűkítés.
        $this->sajat = $this->program('Saját borkóstoló', $this->borkert);
        $this->idegen = $this->program('Idegen könyvbemutató', $this->teka);

        $this->gazda = $this->user(UserRole::CategoryOwner, 'gazda@forgatag.test');
        $this->gazda->categories()->attach($this->borkert);
    }

    private function user(UserRole $role, string $email): User
    {
        return User::create(['name' => $role->label(), 'email' => $email, 'password' => 'hosszu-jelszo-12', 'role' => $role]);
    }

    private function program(string $cim, Category $kat, ProgramStatus $status = ProgramStatus::PendingReview): ProgramItem
    {
        $ut = 'bekuldesek/' . uniqid() . '.jpg';
        Storage::disk('local')->put($ut, 'KEP');
        $p = ProgramItem::create([
            'title' => $cim, 'description' => str_repeat('Leírás ', 12), 'status' => $status,
            'category_id' => $kat->id, 'venue_id' => $this->var->id, 'source' => ProgramSource::PublicForm,
            'contact_name' => 'Beküldő', 'contact_email' => 'bekuldo@example.test',
            'photo_rights_declared' => true, 'photo_path' => $ut,
        ]);
        $p->occurrences()->create(['date' => '2027-08-26', 'start_time' => '18:00']);
        $p->translations()->create(['field' => TranslatableField::Title, 'translated_text' => 'RO ' . $cim]);
        ProgramReview::log($p, ReviewAction::Submitted);

        return $p;
    }

    private function mintGazda(): void
    {
        $this->actingAs($this->gazda, 'web');
    }

    // ---- 1. A programlista ----

    public function test_1_lista_csak_a_sajat_kategoria(): void
    {
        $this->mintGazda();
        foreach (['teendo', 'kint', 'mind'] as $ful) {
            Livewire::test(ListProgramItems::class)->set('activeTab', $ful)
                ->assertCanNotSeeTableRecords([$this->idegen]);
        }
        Livewire::test(ListProgramItems::class)->set('activeTab', 'mind')
            ->assertCanSeeTableRecords([$this->sajat])
            ->assertDontSee('Idegen könyvbemutató');

        // A számlálók sem árulják el az idegen programot (menü-jelvény).
        $this->assertSame('1', ProgramItemResource::getNavigationBadge());

        // A kategória-szűrő idegen kategóriára: üres (nem „kinyit”).
        Livewire::test(ListProgramItems::class)->set('activeTab', 'mind')
            ->filterTable('category', $this->teka->id)
            ->assertCanNotSeeTableRecords([$this->idegen, $this->sajat]);
    }

    public function test_1_kategoria_nelkuli_kategoriagazda_semmit_nem_lat(): void
    {
        $ures = $this->user(UserRole::CategoryOwner, 'ures@forgatag.test');
        $this->actingAs($ures, 'web');
        $this->assertSame(0, ProgramItem::count());
        Livewire::test(ListProgramItems::class)->set('activeTab', 'mind')
            ->assertCanNotSeeTableRecords([$this->sajat, $this->idegen]);
    }

    // ---- 2. A globális kereső ----

    public function test_2_globalis_kereso(): void
    {
        $this->mintGazda();
        $talalat = Filament::getGlobalSearchProvider()->getResults('könyvbemutató');
        $cimek = collect($talalat?->getCategories() ?? [])->flatten()->map(fn ($r) => $r->title)->all();
        $this->assertNotContains('Idegen könyvbemutató', $cimek);

        $sajat = collect(Filament::getGlobalSearchProvider()->getResults('borkóstoló')?->getCategories() ?? [])->flatten()->map(fn ($r) => $r->title)->all();
        $this->assertContains('Saját borkóstoló', $sajat);

        // Más lapok (programgazdák, admin-felhasználók) a keresőben sem nyílnak meg neki.
        $this->assertFalse(ProgramManagerResource::canGloballySearch());
        $this->assertFalse(UserResource::canGloballySearch());
    }

    // ---- 3. A kapcsolatok (helyszín, kategória, programgazda, gyerek-sorok) ----

    public function test_3_kapcsolatok_es_gyerek_sorok(): void
    {
        $beku = ProgramManager::create(['name' => 'Téka', 'email' => 'teka@example.test', 'password' => 'titok-jelszo', 'active' => true]);
        ProgramItem::query()->update(['program_manager_id' => $beku->id]);
        $this->idegen->extraCategories()->attach($this->borkert); // idegen program a gazda kategóriájában mint TOVÁBBI kategória

        $this->mintGazda();
        $this->assertSame([$this->sajat->id], $this->var->programItems()->pluck('id')->all());
        $this->assertSame(0, $this->teka->programItems()->count());
        $this->assertSame(0, $this->borkert->extraProgramItems()->count()); // az elsődleges kategória dönt
        $this->assertSame([$this->sajat->id], $beku->programItems()->pluck('id')->all());
        $this->assertSame(1, Venue::withCount('programItems')->first()->program_items_count);

        // A program gyerek-sorai: időpont, fordítás, napló.
        $this->assertSame([$this->sajat->id], ProgramOccurrence::pluck('program_item_id')->unique()->values()->all());
        $this->assertSame(['RO Saját borkóstoló'], ProgramItemTranslation::pluck('translated_text')->all());
        $this->assertSame([$this->sajat->id], ProgramReviewEvent::pluck('program_item_id')->unique()->values()->all());
        $this->assertNull(ProgramOccurrence::with('programItem')->where('program_item_id', $this->idegen->id)->first());
    }

    // ---- 4. Az export (a Filament exportja a tábla szűrt lekérdezéséből dolgozik) ----

    public function test_4_export_lekerdezese_szukitett(): void
    {
        $this->mintGazda();
        $lista = Livewire::test(ListProgramItems::class)->set('activeTab', 'mind')->instance();
        $this->assertSame([$this->sajat->id], $lista->getFilteredTableQuery()->pluck('id')->all());
        $this->assertSame([$this->sajat->id], ProgramItem::query()->pluck('id')->all());
        $this->assertSame(1, ProgramItem::query()->toBase()->count()); // nyers lekérdezés-építővel is
    }

    // ---- 5. Az import (írás-őr: soronként, a modellen) ----

    public function test_5_import_idegen_kategoriaba_nem_ir(): void
    {
        $this->mintGazda();

        // Új sor idegen kategóriába.
        $this->assertTilos(fn () => ProgramItem::create(['title' => 'Becsempészett', 'description' => 'x', 'category_id' => $this->teka->id]));
        $this->assertTilos(fn () => ProgramItem::create(['title' => 'Kategória nélkül', 'description' => 'x']));

        // Saját sor átvitele idegen kategóriába.
        $this->assertTilos(fn () => $this->sajat->fresh()->update(['category_id' => $this->teka->id]));

        // Az importer azonosító alapján keres: az idegen sort nem találja…
        $this->assertNull(ProgramItem::find($this->idegen->id));
        $this->assertNull(ProgramItem::where('slug', $this->idegen->slug)->first());
        // …és ha „újként” akarná létrehozni ugyanazzal a kategóriával: tiltva.
        $this->assertTilos(fn () => ProgramItem::firstOrNew(['slug' => $this->idegen->slug])->fill([
            'title' => 'Felülírt', 'description' => 'x', 'category_id' => $this->teka->id])->save());

        // Gyerek-sorok idegen programhoz.
        $this->assertTilos(fn () => ProgramOccurrence::create(['program_item_id' => $this->idegen->id, 'date' => '2027-08-27', 'start_time' => '10:00']));
        $this->assertTilos(fn () => ProgramItemTranslation::create(['program_item_id' => $this->idegen->id, 'field' => TranslatableField::Description, 'translated_text' => 'x']));
        $sajatIdopont = $this->sajat->occurrences()->first();
        $this->assertTilos(fn () => $sajatIdopont->update(['program_item_id' => $this->idegen->id]));

        // Tömeges módosítás/törlés: csak a látható sorokra hat.
        $this->assertSame(1, ProgramItem::query()->update(['schedule_note' => 'tömeges']));
        $this->assertSame(0, ProgramItem::whereKey($this->idegen->id)->delete());

        // A saját kategóriába írhat.
        $uj = ProgramItem::create(['title' => 'Saját új', 'description' => 'x', 'category_id' => $this->borkert->id]);
        $this->assertTrue($uj->exists);

        $this->actingAs($this->user(UserRole::SuperAdmin, 'super@forgatag.test'), 'web');
        $idegen = $this->idegen->fresh();
        $this->assertSame('Idegen könyvbemutató', $idegen->title);
        $this->assertNull($idegen->schedule_note);
        $this->assertSame($this->teka->id, $idegen->category_id);
        $this->assertSame(1, $idegen->occurrences()->count());
    }

    private function assertTilos(callable $muvelet): void
    {
        try {
            $muvelet();
            $this->fail('Az írás idegen kategóriába átment.');
        } catch (AuthorizationException $e) {
            $this->assertSame('Ehhez a kategóriához nincs jogosultságod.', $e->getMessage());
        }
    }

    // ---- 6. A közvetlen cím (begépelt azonosító) ----

    public function test_6_kozvetlen_cim_idegen_programra_404(): void
    {
        $this->mintGazda();
        $this->get("/admin/programok/{$this->sajat->id}")->assertOk()->assertSee('Saját borkóstoló');
        $this->get("/admin/programok/{$this->idegen->id}")->assertNotFound();
        $this->get("/admin/programok/{$this->idegen->id}/edit")->assertNotFound();
        $this->get("/admin/programok/{$this->sajat->id}/foto")->assertOk();
        $this->get("/admin/programok/{$this->idegen->id}/foto")->assertNotFound();
    }

    public function test_6_livewire_rekord_csere_sem_nyit_ki(): void
    {
        $this->mintGazda();
        Livewire::test(ViewProgramItem::class, ['record' => $this->idegen->id])->assertNotFound();
    }

    // ---- A szerepkör-mátrix ----

    public function test_kategoriagazda_atnez_de_nem_hagy_jova(): void
    {
        $this->mintGazda();
        Livewire::test(ViewProgramItem::class, ['record' => $this->sajat->id])
            ->assertActionHidden('jovahagy')
            ->assertActionVisible('vissza');

        try {
            ProgramReview::approve($this->sajat, $this->gazda);
            $this->fail('A kategóriagazda jóváhagyhatott.');
        } catch (ReviewStepRefused $e) {
            $this->assertStringContainsString('Jóváhagyni csak a programkoordinátor', $e->getMessage());
        }
        $this->assertSame(ProgramStatus::PendingReview, $this->sajat->fresh()->status);

        // Idegen programot a szolgáltatáson át sem érhet el.
        $this->expectException(ModelNotFoundException::class);
        ProgramReview::returnForFix($this->idegen, $this->gazda, 'x');
    }

    public function test_kategoriagazda_nem_lat_mas_lapokat(): void
    {
        $this->mintGazda();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/program-managers')->assertForbidden();
        $this->get('/admin/beallitasok')->assertForbidden();
    }

    public function test_koordinator_mindent_lat_es_jovahagy_de_felhasznalot_nem_kezel(): void
    {
        $koord = $this->user(UserRole::Coordinator, 'koord@forgatag.test');
        $this->actingAs($koord, 'web');
        $this->assertSame(2, ProgramItem::count());
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/program-managers')->assertOk();
        $this->get('/admin/beallitasok')->assertOk();
        Livewire::test(ViewProgramItem::class, ['record' => $this->idegen->id])
            ->callAction('jovahagy')->assertNotified('Jóváhagyva, közzétéve.');
        $this->assertSame(ProgramStatus::Published, $this->idegen->fresh()->status);
    }

    /** @return array<string, array{UserRole}> */
    public static function olvasoSzerepek(): array
    {
        return ['fordító' => [UserRole::Translator], 'csak olvasó' => [UserRole::ReadOnly]];
    }

    #[DataProvider('olvasoSzerepek')]
    public function test_fordito_es_csak_olvaso_lat_de_nem_lep(UserRole $role): void
    {
        $this->actingAs($this->user($role, $role->value . '@forgatag.test'), 'web');
        $this->assertSame(2, ProgramItem::count());
        $this->get("/admin/programok/{$this->idegen->id}")->assertOk();
        Livewire::test(ViewProgramItem::class, ['record' => $this->sajat->id])
            ->assertActionHidden('jovahagy')->assertActionHidden('kuld')->assertActionHidden('vissza');
        $this->get('/admin/beallitasok')->assertForbidden();
    }

    public function test_arculat_szerkeszto_programot_nem_lat(): void
    {
        $this->actingAs($this->user(UserRole::ThemeEditor, 'arculat@forgatag.test'), 'web');
        $this->get('/admin/programok')->assertForbidden();
        $this->get("/admin/programok/{$this->sajat->id}")->assertForbidden();
        $this->get("/admin/programok/{$this->sajat->id}/foto")->assertNotFound();
        $this->assertFalse(ProgramItemResource::canGloballySearch());
    }

    public function test_uj_fiok_alapbol_csak_olvaso_a_sajat_szerepkor_nem_allithato(): void
    {
        $uj = User::create(['name' => 'Új', 'email' => 'uj@forgatag.test', 'password' => 'hosszu-jelszo-12']);
        $this->assertSame(UserRole::ReadOnly, $uj->fresh()->role);

        $super = $this->user(UserRole::SuperAdmin, 'super@forgatag.test');
        $this->actingAs($super, 'web');
        Livewire::test(EditUser::class, ['record' => $super->id])->assertFormFieldDisabled('role');
        Livewire::test(EditUser::class, ['record' => $this->gazda->id])
            ->assertFormFieldEnabled('role')
            ->fillForm(['role' => UserRole::CategoryOwner->value, 'categories' => []])
            ->call('save')->assertHasFormErrors(['categories' => 'required']);
    }

    // ---- A beküldő felület nem szűkül egy közben belépett admin jogával ----

    public function test_beekuldo_sajat_programjai_admin_belepes_mellett_is_latszanak(): void
    {
        $beku = ProgramManager::create(['name' => 'Téka', 'email' => 'teka@example.test', 'password' => 'titok-jelszo', 'active' => true]);
        $beku->categories()->attach($this->teka);
        $this->idegen->update(['program_manager_id' => $beku->id]);

        // Ugyanabban a böngészőben: borkerti kategóriagazda (web) ÉS téka-programgazda (bekuldo).
        $this->actingAs($this->gazda, 'web');
        $this->actingAs($beku, 'bekuldo')->get('/bekuldes/sajat')->assertOk()->assertSee('Idegen könyvbemutató');
        $this->actingAs($beku, 'bekuldo')->get("/bekuldes/program/{$this->idegen->id}/foto")->assertOk();
    }
}
