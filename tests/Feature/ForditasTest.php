<?php

namespace Tests\Feature;

use App\Enums\ProgramStatus;
use App\Enums\TranslatableField;
use App\Enums\TranslationStatus;
use App\Filament\Pages\Beallitasok;
use App\Filament\Resources\ProgramItems\Pages\ListProgramItems;
use App\Filament\Resources\ProgramItems\Pages\ViewProgramItem;
use App\Filament\Widgets\Figyelmeztetesek;
use App\Models\Category;
use App\Models\NewsItem;
use App\Models\ProgramItem;
use App\Models\ProgramItemTranslation;
use App\Models\User;
use App\Services\Forditas\Forditogep;
use App\Services\Forditas\GepiForditas;
use App\Services\Forditas\KeziForditas;
use App\Support\Forditas\VedettNevek;
use App\Support\ProgramSzoveg;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * A fordítási lánc (PROMPT-19, CLAUDE.md 5.): hatókör, az emberi fordítás sérthetetlensége, az elavult sor feltűnő
 * jelzése, a költség (változatlant nem fordít, üres futásnál NINCS API-hívás), kulcs nélkül nem törik, a védett nevek
 * és az ő/ű-ellenőrzés, a fordító szerepkör. A Claude API helyett hamis gép (HamisGep) — számolja a hívásokat.
 */
class ForditasTest extends TestCase
{
    use RefreshDatabase;

    private HamisGep $gep;

    private Category $kat;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::save([
            'festival' => ['start_date' => '2027-08-25', 'end_date' => '2027-08-29', 'prep_days' => 2,
                'opening_day' => '2027-08-25', 'name_hu' => 'Vásárhelyi Forgatag', 'name_ro' => 'Vâltoarea Mureșeană', 'day_cut_hour' => 3],
            'translation' => [
                'terms' => ['Ebforgatag', 'Csicsergő', 'Szűcsök Bástyája'],
                'bound' => [['hu' => 'Vásárhelyi Forgatag', 'ro' => 'Vâltoarea Mureșeană'], ['hu' => 'Forgatag', 'ro' => 'Vâltoarea']],
            ],
        ]);
        config(['forditas.kulcs' => 'teszt-kulcs', 'forditas.ellenorzes' => false]);
        $this->gep = new HamisGep;
        $this->app->instance(Forditogep::class, $this->gep);
        $this->kat = Category::create(['slug' => 'borkert', 'name_hu' => 'Borkert', 'sort' => 1]);
    }

    private function program(array $felul = []): ProgramItem
    {
        return ProgramItem::create(array_replace([
            'title' => 'Borkóstoló', 'description' => 'Kóstoló a várban.', 'ticket_info' => 'Jegy: 20 lej',
            'status' => ProgramStatus::Draft, 'category_id' => $this->kat->id,
        ], $felul));
    }

    private function futtat(): array
    {
        return app(GepiForditas::class)->futtat(null, 'teszt');
    }

    private function sor(ProgramItem $p, TranslatableField $m): ?ProgramItemTranslation
    {
        return $p->translations()->where('field', $m->value)->first();
    }

    public function test_a_gep_csak_a_program_harom_mezojet_forditja_a_hirhez_nem_nyul(): void
    {
        $p = $this->program();
        $hir = NewsItem::create(['slug' => 'hir', 'title_hu' => 'Magyar hír', 'body_hu' => 'Szöveg', 'status' => 'published']);

        $j = $this->futtat();

        $this->assertSame('kesz', $j['allapot']);
        $this->assertSame(3, $j['forditva']);
        $this->assertSame(1, $this->gep->hivas);                // egy csomag = egy hívás
        $this->assertSame('RO: Borkóstoló', $this->sor($p, TranslatableField::Title)->translated_text);
        $this->assertSame(TranslationStatus::MachineTranslated, $this->sor($p, TranslatableField::Title)->status);
        $this->assertSame(ProgramItemTranslation::hashSource('Borkóstoló'), $this->sor($p, TranslatableField::Title)->source_text_hash);
        $this->assertNull($hir->fresh()->title_ro);              // saját tartalom: KÉZI fordítás
        $this->assertSame(['Borkóstoló', 'Kóstoló a várban.', 'Jegy: 20 lej'], array_values($this->gep->kapott));
    }

    public function test_valtozatlant_nem_fordit_ujra_es_ures_futasnal_nincs_api_hivas(): void
    {
        $this->program();
        $this->futtat();
        $this->assertSame(1, $this->gep->hivas);

        $j = $this->futtat();
        $this->assertSame('nincs_teendo', $j['allapot']);
        $this->assertSame(0, $j['api_hivas']);
        $this->assertSame(1, $this->gep->hivas);                // a második futás NEM hívott
        $this->assertSame(3, $j['naprakesz']);
        $this->assertStringContainsString('API-hívás nem történt', GepiForditas::allapotSzoveg($j));
    }

    public function test_kulcs_nelkul_csendben_kimarad_es_semmi_nem_torik(): void
    {
        config(['forditas.kulcs' => null]);
        $p = $this->program();
        $p->update(['title' => 'Új cím']);                      // a mentés sem törik el

        $j = $this->futtat();
        $this->assertSame('nincs_kulcs', $j['allapot']);
        $this->assertSame(0, $this->gep->hivas);
        $this->assertSame(0, $p->translations()->count());
        $this->assertSame('Új cím', ProgramSzoveg::mezo($p->fresh('translations'), TranslatableField::Title, 'ro')['szoveg']);
    }

    public function test_az_emberi_forditast_a_gep_nem_irja_felul(): void
    {
        $p = $this->program();
        foreach ([TranslationStatus::Reviewed, TranslationStatus::Approved] as $i => $st) {
            $m = [TranslatableField::Title, TranslatableField::Description][$i];
            ProgramItemTranslation::create(['program_item_id' => $p->id, 'field' => $m, 'translated_text' => 'KÉZI ' . $i,
                'status' => $st, 'source_text_hash' => ProgramItemTranslation::hashSource($p->{$m->value})]);
        }
        ProgramItemTranslation::create(['program_item_id' => $p->id, 'field' => TranslatableField::TicketInfo, 'translated_text' => 'RÉGI',
            'status' => TranslationStatus::Outdated, 'source_text_hash' => 'regi']);

        $j = $this->futtat();

        $this->assertSame(0, $this->gep->hivas);                // semmi nem gépi → nincs mit fordítani
        $this->assertSame('KÉZI 0', $this->sor($p, TranslatableField::Title)->translated_text);
        $this->assertSame('KÉZI 1', $this->sor($p, TranslatableField::Description)->translated_text);
        $this->assertSame(TranslationStatus::Outdated, $this->sor($p, TranslatableField::TicketInfo)->status);
        $this->assertSame('RÉGI', $this->sor($p, TranslatableField::TicketInfo)->translated_text);
        $this->assertSame(2, $j['emberi']);
    }

    public function test_a_magyar_valtozasa_elavultta_teszi_az_atnezettet_a_gepit_ujraforditja(): void
    {
        $p = $this->program(['status' => ProgramStatus::Published]);
        $this->futtat();
        $atnezett = $this->sor($p, TranslatableField::Title);
        $atnezett->update(['status' => TranslationStatus::Reviewed, 'translated_text' => 'Degustare (átnézve)']);
        $this->assertSame('Degustare (átnézve)', ProgramSzoveg::cim($this->friss($p, 'ro')));

        // A koordinátor átírja a magyart (időpont/ár változott): az átnézett sor AZONNAL elavul.
        $p->update(['title' => 'Borkóstoló — ÚJ időpont', 'description' => 'Kóstoló a várban, 18 órától.']);
        $this->assertSame(TranslationStatus::Outdated, $this->sor($p, TranslatableField::Title)->status);
        // A román oldalon a MAGYAR látszik — a régi román és a régi gépi leírás sem.
        $friss = $this->friss($p, 'ro');
        $this->assertSame('Borkóstoló — ÚJ időpont', ProgramSzoveg::cim($friss));
        $this->assertSame('Kóstoló a várban, 18 órától.', ProgramSzoveg::mezo($friss, TranslatableField::Description, 'ro')['szoveg']);

        $j = $this->futtat();
        $this->assertSame(1, $j['forditva']);                   // csak a gépi leírás, a cím emberre vár
        $this->assertSame('RO: Kóstoló a várban, 18 órától.', $this->sor($p, TranslatableField::Description)->translated_text);
        $this->assertSame(TranslationStatus::Outdated, $this->sor($p, TranslatableField::Title)->status);
        $this->assertSame('Degustare (átnézve)', $this->sor($p, TranslatableField::Title)->translated_text);
        $this->assertSame(1, $j['elavult_osszes']);
    }

    public function test_a_lenyomat_eltereset_a_futas_is_eszreveszi(): void
    {
        $p = $this->program();
        ProgramItemTranslation::create(['program_item_id' => $p->id, 'field' => TranslatableField::Title, 'translated_text' => 'Kézi',
            'status' => TranslationStatus::Approved, 'source_text_hash' => ProgramItemTranslation::hashSource('valami régi')]);

        $j = $this->futtat();
        $this->assertSame(1, $j['elavult_most']);
        $this->assertSame(TranslationStatus::Outdated, $this->sor($p, TranslatableField::Title)->status);
    }

    public function test_az_elavult_sor_feltunoen_latszik_a_jovahagyasi_sorban(): void
    {
        $kint = $this->program(['status' => ProgramStatus::Published, 'title' => 'Kint lévő']);
        ProgramItemTranslation::create(['program_item_id' => $kint->id, 'field' => TranslatableField::Title, 'translated_text' => 'Vechi',
            'status' => TranslationStatus::Outdated, 'source_text_hash' => 'x']);
        $tiszta = $this->program(['title' => 'Tiszta']);
        $admin = User::create(['name' => 'K', 'email' => 'k@forgatag.test', 'password' => 'jelszo-hosszu-1', 'role' => 'coordinator']);
        $this->actingAs($admin);

        // A közzétett program a „Teendő” fülön nincs rajta — ezért van saját, piros fül.
        Livewire::test(ListProgramItems::class)
            ->assertSee('Elavult fordítás')
            ->set('activeTab', 'elavult')
            ->assertCanSeeTableRecords([$kint])->assertCanNotSeeTableRecords([$tiszta]);
        Livewire::test(ViewProgramItem::class, ['record' => $kint->id])
            ->assertSee('ELAVULT — a magyar szöveg változott')
            ->assertSee('Vechi');
        $this->assertContains('1 program román fordítása ELAVULT', array_column(Figyelmeztetesek::uzenetek(), 'cim'));
    }

    public function test_vedett_nevek_valtoarei_es_o_u_ellenorzes(): void
    {
        $v = VedettNevek::beallitasbol();
        $prompt = $v->forditoPrompt();
        $this->assertStringContainsString('- Szűcsök Bástyája', $prompt);
        $this->assertStringContainsString('„Vásárhelyi Forgatag" → „Vâltoarea Mureșeană"', $prompt);
        $this->assertStringContainsString('„Ebforgatag"', $prompt);            // a csapda-lista
        $this->assertStringContainsString('Vâltoarei', $prompt);

        // Rendben: a védett név magyar betűje (Csicsergő) nem gyanús; a birtokos eset (Vâltorii) elfogadott.
        $this->assertSame([], $v->ellenoriz('Csicsergő a Vásárhelyi Forgatagon', 'Csicsergő pe teritoriul Vâltorii Mureșene'));
        $this->assertSame([], $v->ellenoriz('Ebforgatag a parkban', 'Ebforgatag în parc'));
        // Hibák: kiesett védett név, „Vâltoarei”, bent maradt magyar szó.
        $hibak = implode("\n", $v->ellenoriz('Ebforgatag a Forgatagon', 'Ebvâltoarea la Vâltoarei, előadás'));
        $this->assertStringContainsString('TULAJDONNÉV: „Ebforgatag”', $hibak);
        $this->assertStringContainsString('HIBÁS ALAK: „Vâltoarei”', $hibak);
        $this->assertStringContainsString('MAGYAR BETŰ (ő/ű) a román szövegben: „előadás”', $hibak);
    }

    public function test_jelzett_gepi_forditas_nem_megy_ki_a_roman_oldalra(): void
    {
        $this->gep->atalakit = fn (string $hu) => $hu === 'Borkóstoló' ? 'Degustare de vin előadás' : 'RO: ' . $hu;
        $p = $this->program();

        $j = $this->futtat();

        $this->assertSame(1, $j['jelzett']);
        $sor = $this->sor($p, TranslatableField::Title);
        $this->assertStringContainsString('„előadás”', (string) $sor->check_note);
        // Jelzett gépi sor: átnézésig a magyar látszik; a jelzés nélküli gépi sor kimegy.
        $friss = $this->friss($p, 'ro');
        $this->assertSame('Borkóstoló', ProgramSzoveg::cim($friss));
        $this->assertSame('RO: Kóstoló a várban.', ProgramSzoveg::mezo($friss, TranslatableField::Description, 'ro')['szoveg']);
        // A teljes generált román szövegben nincs ő/ű — kivéve a jelzett sort.
        $tiszta = ProgramItemTranslation::whereNull('check_note')->pluck('translated_text')->implode(' ');
        $this->assertDoesNotMatchRegularExpression('/[őűŐŰ]/u', $tiszta);
    }

    public function test_a_teny_ellenorzes_jelzese_a_sorra_kerul(): void
    {
        config(['forditas.ellenorzes' => true]);
        $this->gep->tenyek = ['t0' => ['HOZZÁTOLDÁS: „18:00” — a forrásban nincs időpont.']];
        $p = $this->program();

        $this->futtat();

        $this->assertSame(2, $this->gep->hivas);                // fordítás + ellenőrzés
        $this->assertStringContainsString('HOZZÁTOLDÁS', (string) $this->sor($p, TranslatableField::Title)->check_note);
    }

    public function test_hibanal_semmit_nem_dont_be_de_a_hiba_latszik(): void
    {
        $this->gep->hiba = new RuntimeException('529 Overloaded');
        $p = $this->program();

        $j = $this->futtat();

        $this->assertSame('hiba', $j['allapot']);
        $this->assertStringContainsString('529 Overloaded', $j['hiba']);
        $this->assertSame(0, $p->translations()->count());
        $this->assertSame('hiba', GepiForditas::utolsoFutas()['allapot']);
        $this->assertContains('A gépi fordítás legutóbbi futása hibával állt meg', array_column(Figyelmeztetesek::uzenetek(), 'cim'));
        $this->assertSame(1, $this->artisan('forgatag:forditas')->run());   // a parancs hibakóddal lép ki
    }

    public function test_a_mentes_utan_magatol_lefut(): void
    {
        $this->withoutDefer();
        $p = $this->program();

        $this->assertSame(3, $p->translations()->count());
        $this->assertSame(1, $this->gep->hivas);
    }

    public function test_egyszerre_csak_egy_futas(): void
    {
        $zar = cache()->lock('forgatag-forditas', 60);
        $this->assertTrue($zar->get());
        $this->program();

        $j = $this->futtat();
        $this->assertSame('fut_mar', $j['allapot']);
        $this->assertSame(0, $this->gep->hivas);
        $zar->release();
    }

    public function test_kezi_forditas_szabalyai_es_a_fordito_szerepkor(): void
    {
        $p = $this->program();
        $this->futtat();
        $fordito = User::create(['name' => 'F', 'email' => 'f@forgatag.test', 'password' => 'jelszo-hosszu-1', 'role' => 'translator']);
        $gazda = User::create(['name' => 'G', 'email' => 'g@forgatag.test', 'password' => 'jelszo-hosszu-1', 'role' => 'category_owner']);

        // Kézzel átírt szöveg nem maradhat „gépi”.
        try {
            KeziForditas::ment($p, $fordito, ['title' => ['ro' => 'Degustare', 'status' => 'machine_translated']]);
            $this->fail('A kézzel írt szöveg gépi státusszal átment.');
        } catch (ValidationException) {
        }
        $this->assertSame(1, KeziForditas::ment($p, $fordito, ['title' => ['ro' => 'Degustare de vin', 'status' => 'approved']]));
        $sor = $this->sor($p, TranslatableField::Title);
        $this->assertSame(TranslationStatus::Approved, $sor->status);
        $this->assertTrue($sor->isCurrentFor($p));

        // A gép a jóváhagyottat többé nem írja felül.
        $sor->update(['source_text_hash' => $sor->source_text_hash]);
        $this->futtat();
        $this->assertSame('Degustare de vin', $this->sor($p, TranslatableField::Title)->translated_text);

        // A kategóriagazda nem fordíthat.
        try {
            KeziForditas::ment($p, $gazda, ['title' => ['ro' => 'X', 'status' => 'approved']]);
            $this->fail('A kategóriagazda fordíthatott.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // A fordító a program lapján látja és szerkeszti; a Beállításokhoz nem fér hozzá.
        $this->actingAs($fordito, 'web');
        Livewire::test(ViewProgramItem::class, ['record' => $p->id])
            ->assertSee('Degustare de vin')
            ->assertActionVisible('forditas')
            ->assertActionHidden('jovahagy')
            ->callAction('forditas', data: ['description' => ['ro' => 'Degustare în cetate.', 'status' => 'reviewed']])
            ->assertHasNoActionErrors();
        $this->assertSame(TranslationStatus::Reviewed, $this->sor($p, TranslatableField::Description)->status);
        $this->assertFalse(Beallitasok::canAccess());
    }

    public function test_a_vedett_nevek_a_beallitasokban_szerkeszthetok(): void
    {
        $k = User::create(['name' => 'K', 'email' => 'k@forgatag.test', 'password' => 'jelszo-hosszu-1', 'role' => 'coordinator']);
        $this->actingAs($k, 'web');
        Settings::save(['submission' => ['open' => false, 'closed_message' => 'Zárva.', 'deadline' => null],
            'public' => ['coming_soon' => false, 'coming_soon_title_hu' => 'H', 'coming_soon_title_ro' => null, 'coming_soon_text_hu' => 'H',
                'coming_soon_text_ro' => null, 'footer_text_hu' => 'L', 'footer_text_ro' => null, 'home_state' => 'utana', 'countdown_time' => '10:00'],
            'notifications' => ['topics' => ['program_submission' => ['k@forgatag.test']]]]
            // a Beállítások lap többi blokkja (a mentés a kötelező mezőiket is ellenőrzi) — a kezdőértékek a seedből
            + array_intersect_key(json_decode((string) file_get_contents(base_path('seed/settings.json')), true), array_flip(['olympics', 'volunteer'])));
        $this->program();
        $this->futtat();

        Livewire::test(Beallitasok::class)
            ->assertSee('Gépi fordítás (magyar → román)')
            ->assertSet('data.translation.terms', "Ebforgatag\nCsicsergő\nSzűcsök Bástyája")
            ->assertSee('Lefordítva: 3')
            ->set('data.translation.terms', "Ebforgatag\nMátka\n\n Mátka ")
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(['Ebforgatag', 'Mátka'], Settings::get('translation.terms'));
        $this->assertStringContainsString('- Mátka', VedettNevek::beallitasbol()->forditoPrompt());
    }

    private function friss(ProgramItem $p, string $nyelv): ProgramItem
    {
        app()->setLocale($nyelv);

        return ProgramItem::with('translations')->findOrFail($p->id);
    }
}

/** Hamis fordítógép: nem hív API-t; „RO: ” elé teszi a szöveget, és számolja a hívásokat. */
class HamisGep implements Forditogep
{
    public int $hivas = 0;

    /** @var array<string, string> */
    public array $kapott = [];

    /** @var \Closure(string): string|null */
    public ?\Closure $atalakit = null;

    /** @var array<string, list<string>> */
    public array $tenyek = [];

    public ?\Throwable $hiba = null;

    public function fordit(array $tetelek, string $rendszerPrompt): array
    {
        $this->hivas++;
        if ($this->hiba) {
            throw $this->hiba;
        }
        $this->kapott += $tetelek;

        return array_map(fn ($hu) => $this->atalakit ? ($this->atalakit)($hu) : 'RO: ' . $hu, $tetelek);
    }

    public function ellenoriz(array $parok): array
    {
        $this->hivas++;

        return $this->tenyek + array_map(fn () => [], $parok);
    }

    public function hasznalat(): array
    {
        return ['hivas' => $this->hivas, 'be' => 0, 'ki' => 0, 'gyorsitotarbol' => 0];
    }
}
