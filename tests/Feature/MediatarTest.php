<?php

namespace Tests\Feature;

use App\Enums\Gyujtemeny;
use App\Enums\MediaLicense;
use App\Enums\MediaOrigin;
use App\Enums\ProgramSource;
use App\Enums\ProgramStatus;
use App\Filament\Mediatar\Pages\ListMedia;
use App\Livewire\MediaPanel;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Models\ProgramItem;
use App\Models\ProgramManager;
use App\Models\Sponsor;
use App\Models\User;
use App\Models\Venue;
use App\Services\ProgramReview;
use App\Services\ReviewStepRefused;
use App\Support\MediatarImport;
use App\Support\Settings;
use App\Support\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * A médiatár (CLAUDE.md 2/d, PROMPT-07): gyűjtemény a feltöltés helyéből · licenc két esete ·
 * jóváhagyáskor zárt tár → médiatár · „Programok 2026” őr · törlés-őr · arculat · 2026-os behozatal.
 */
class MediatarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->admin = User::create(['name' => 'Koordinátor', 'email' => 'admin@forgatag.test', 'password' => 'admin-jelszo-hosszu', 'role' => 'superadmin']);
    }

    private function kep(array $felul = []): Media
    {
        Storage::disk('mediatar')->put($p = uniqid() . '.jpg', 'KEP');

        return Media::create(array_replace(['disk' => 'mediatar', 'name' => pathinfo($p, PATHINFO_FILENAME), 'path' => $p,
            'type' => 'image/jpeg', 'ext' => 'jpg', 'size' => 3], $felul));
    }

    // --- 1. A gyűjtemény abból jön, honnan töltötték fel -------------------------------------------------

    public function test_a_hir_lapjarol_feltoltott_kep_maga_a_hirek_gyujtemenybe_kerul(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(MediaPanel::class, ['settings' => [
            'diskName' => 'mediatar', 'visibility' => 'public', 'isMultiple' => false, 'statePath' => 'data.cover',
            'acceptedFileTypes' => ['image/*'], 'gyujtemeny' => Gyujtemeny::Hirek->value,
        ]])
            ->set('panelData.files_to_add', [UploadedFile::fake()->image('borito.jpg', 40, 30)])
            ->callAction('addFiles');

        $m = Media::where('origin', MediaOrigin::Admin)->sole();
        $this->assertTrue($m->isIn(Gyujtemeny::Hirek), 'a hír lapjáról feltöltött kép nem került a „Hírek” gyűjteménybe');
        $this->assertSame(MediaOrigin::Admin, $m->origin);
        $this->assertSame(MediaLicense::Sajat, $m->license, 'adminban feltöltött kép: saját forrás az alapérték');
    }

    public function test_kozvetlenul_a_mediatarba_feltoltott_kep_rendezetlen(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(MediaPanel::class, ['settings' => [
            'diskName' => 'mediatar', 'visibility' => 'public', 'isMultiple' => false, 'statePath' => 'x',
            'acceptedFileTypes' => ['image/*'],
        ]])
            ->set('panelData.files_to_add', [UploadedFile::fake()->image('valami.jpg', 40, 30)])
            ->callAction('addFiles');

        $this->assertSame(1, Media::query()->rendezetlen()->count());
    }

    public function test_a_gyujtemeny_nem_irhato_at_a_bongeszobol(): void
    {
        $this->actingAs($this->admin);
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        Livewire::test(MediaPanel::class, ['settings' => ['diskName' => 'mediatar', 'visibility' => 'public', 'gyujtemeny' => 'hirek']])
            ->set('gyujtemeny', 'arculat');
    }

    public function test_athelyezes_es_hozzaadas_gyujtemenyhez(): void
    {
        $this->actingAs($this->admin);
        $a = $this->kep();
        $b = $this->kep();
        $hirek = MediaCollection::idFor(Gyujtemeny::Hirek);
        $lapok = MediaCollection::idFor(Gyujtemeny::Lapok);

        Livewire::test(ListMedia::class)
            ->callTableBulkAction('athelyez', [$a, $b], ['gyujtemeny' => $hirek])
            ->assertHasNoTableBulkActionErrors();
        $this->assertSame(0, Media::query()->rendezetlen()->count());

        Livewire::test(ListMedia::class)->callTableBulkAction('hozzaad', [$a], ['gyujtemeny' => $lapok]);
        $this->assertEqualsCanonicalizing([$hirek, $lapok], $a->collections()->pluck('media_collections.id')->all(), 'egy kép több gyűjteményben is lehet');

        Livewire::test(ListMedia::class)->callTableBulkAction('athelyez', [$a], ['gyujtemeny' => $lapok]);
        $this->assertSame([$lapok], $a->collections()->pluck('media_collections.id')->all(), 'az áthelyezés a többiből kivesz');

        // A baloldali lista szűr: a „Rendezetlen” üres, a „Hírek”-ben egy kép van.
        Livewire::test(ListMedia::class)->set('gyujtemeny', ListMedia::RENDEZETLEN)->assertCanNotSeeTableRecords([$a, $b]);
        Livewire::test(ListMedia::class)->set('gyujtemeny', (string) $hirek)->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);
    }

    // --- 2. A licenc két esete ------------------------------------------------------------------------------

    public function test_bekuldott_kep_licenc_nelkul_vagy_ismeretlennel_nem_mentheto(): void
    {
        foreach ([MediaLicense::Ismeretlen, null] as $lic) {
            try {
                $this->kep(['origin' => MediaOrigin::Bekuldes, 'license' => $lic, 'rights_holder' => 'Teleki Téka']);
                $this->fail('beküldött kép ' . ($lic?->value ?? 'licenc nélkül') . ' elment');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('licenc kötelező', $e->getMessage());
            }
        }
        $this->expectExceptionMessage('jogtulajdonos');
        $this->kep(['origin' => MediaOrigin::Bekuldes, 'license' => MediaLicense::MindenJogFenntartva]);
    }

    public function test_adminban_feltoltott_kepnel_a_licenc_nem_all_az_utba(): void
    {
        $m = $this->kep(); // licenc, jogtulajdonos nélkül
        $this->assertSame(MediaLicense::Sajat, $m->fresh()->license);
        $m->update(['license' => MediaLicense::Ismeretlen]); // saját képnél ez is elmehet
        $this->assertSame(MediaLicense::Ismeretlen, $m->fresh()->license);
    }

    // --- 3. Jóváhagyáskor a zárt tárból a médiatárba --------------------------------------------------------

    private function bekuldottProgram(): ProgramItem
    {
        Settings::save([
            'festival' => ['start_date' => '2027-08-25', 'end_date' => '2027-08-29', 'prep_days' => 2, 'opening_day' => '2027-08-25',
                'name_hu' => 'Vásárhelyi Forgatag', 'name_ro' => 'Vâltoarea Mureșeană', 'day_cut_hour' => 3],
            'submission' => ['open' => true, 'closed_message' => 'Zárva.', 'deadline' => null],
            'notifications' => ['topics' => []],
        ]);
        $kat = Category::create(['slug' => 'borkert', 'name_hu' => 'Borkert', 'sort' => 1]);
        $hely = Venue::create(['slug' => 'var', 'name' => 'Vár']);
        $beku = ProgramManager::create(['name' => 'Teleki Téka', 'email' => 'teka@example.test', 'password' => 'titok-jelszo', 'active' => true]);
        Storage::disk('local')->put($ut = 'bekuldesek/abc.jpg', 'EREDETI-KEPADAT');
        $p = ProgramItem::create(['title' => 'Borkóstoló a várban', 'description' => str_repeat('Leírás ', 12),
            'category_id' => $kat->id, 'venue_id' => $hely->id, 'source' => ProgramSource::PublicForm,
            'program_manager_id' => $beku->id, 'contact_name' => 'Kiss Anna', 'contact_email' => 'teka@example.test',
            'photographer_credit' => 'Nagy Péter', 'photo_rights_declared' => true, 'photo_path' => $ut,
            'status' => ProgramStatus::PendingReview]);
        $p->occurrences()->create(['date' => '2027-08-26', 'start_time' => '18:00', 'end_time' => '20:00']);

        return $p;
    }

    public function test_jovahagyaskor_a_foto_atkerul_a_mediatarba_a_jogallas_adataival(): void
    {
        $p = $this->bekuldottProgram();
        $this->assertSame(0, Media::where('origin', '!=', MediaOrigin::Arculat)->count(), 'jóváhagyás előtt a fotó NEM lehet a médiatárban');
        $this->assertSame([], Storage::disk('mediatar')->allFiles());

        ProgramReview::approve($p, $this->admin);

        $p->refresh();
        $m = $p->cover;
        $this->assertNotNull($m, 'jóváhagyás után a programnak van médiatár-borítója');
        $this->assertSame(ProgramStatus::Published, $p->status);
        $this->assertTrue($m->isIn(Gyujtemeny::Programok));
        $this->assertSame(MediaOrigin::Bekuldes, $m->origin);
        $this->assertSame(MediaLicense::MindenJogFenntartva, $m->license);
        $this->assertSame('Teleki Téka', $m->rights_holder);
        $this->assertSame('Nagy Péter', $m->photographer_credit);
        $this->assertSame('EREDETI-KEPADAT', Storage::disk('mediatar')->get($m->path));
        $this->assertTrue(Storage::disk('local')->exists('bekuldesek/abc.jpg'), 'a beküldött eredeti a zárt tárban marad');
    }

    public function test_visszakuldott_program_fotoja_nem_kerul_a_mediatarba(): void
    {
        $p = $this->bekuldottProgram();
        ProgramReview::returnForFix($p, $this->admin, 'Kérlek, javítsd a leírást.');
        $this->assertSame(0, Media::where('origin', '!=', MediaOrigin::Arculat)->count());
        $this->assertSame([], Storage::disk('mediatar')->allFiles());
    }

    public function test_ha_a_foto_nem_teheto_at_a_jovahagyas_sem_tortenik_meg(): void
    {
        $p = $this->bekuldottProgram();
        Storage::disk('local')->delete('bekuldesek/abc.jpg');
        try {
            ProgramReview::approve($p, $this->admin);
            $this->fail('fotó nélkül jóváhagyta');
        } catch (ReviewStepRefused) {
        }
        $this->assertSame(ProgramStatus::PendingReview, $p->fresh()->status);
        $this->assertSame(0, Media::where('origin', '!=', MediaOrigin::Arculat)->count());
        Mail::assertNothingSent();
    }

    // --- „Programok 2026” őr, törlés-őr, arculat ------------------------------------------------------------

    public function test_programok_2026_kepe_2027_es_programhoz_nem_rendelheto(): void
    {
        $p = $this->bekuldottProgram();
        $tavalyi = $this->kep(['origin' => MediaOrigin::Import2026]);
        $tavalyi->addToCollection(Gyujtemeny::Programok2026);

        $this->expectExceptionMessage('Programok 2026');
        $p->cover_media_id = $tavalyi->id;
        $p->save();
    }

    public function test_hasznalt_kep_nem_torolheto_a_hasznalatlan_igen(): void
    {
        $logo = $this->kep();
        Sponsor::create(['name' => 'FomCo', 'year' => 2026, 'logo_media_id' => $logo->id]);
        $this->assertFalse($this->admin->can('delete', $logo));
        try {
            $logo->delete();
            $this->fail('a használt logó törölhető volt');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('FomCo', $e->getMessage());
        }
        $this->assertTrue(Storage::disk('mediatar')->exists($logo->path));

        $szabad = $this->kep();
        $this->assertTrue($this->admin->can('delete', $szabad));
    }

    public function test_az_arculat_fajljai_az_arculat_gyujtemenyben_vannak_es_nem_torolhetok(): void
    {
        $arculat = Media::where('origin', MediaOrigin::Arculat)->get();
        $this->assertCount(9, $arculat, 'logó + fejléc-logó + favicon + 6 firka');
        foreach ($arculat as $m) {
            $this->assertTrue($m->isIn(Gyujtemeny::Arculat));
            $this->assertSame(Theme::DISK, $m->disk, 'ugyanaz a fájl, nem másolat');
            $this->assertFalse($this->admin->can('delete', $m));
        }
    }

    public function test_jog_kategoriagazda_nem_eri_el_a_mediatarat(): void
    {
        $kg = User::create(['name' => 'KG', 'email' => 'kg@forgatag.test', 'password' => 'hosszu-jelszo-kg', 'role' => 'category_owner']);
        $this->actingAs($kg)->get('/admin/mediatar')->assertForbidden();
        $this->actingAs($kg)->get('/admin/galeriak')->assertForbidden();
        $this->actingAs($kg)->get('/admin/tamogatok')->assertForbidden();
    }

    // --- 4. A 2026-os behozatal -----------------------------------------------------------------------------

    public function test_behozatal_kapcsolatokkal_szamszeru_jelentessel_es_ismetles_biztosan(): void
    {
        $dir = sys_get_temp_dir() . '/mt-' . uniqid();
        mkdir($dir);
        $fajl = fn (string $id, string $tart, array $e) => ['id' => $id, 'forras' => "{$id}.jpg", 'cel' => "{$id}.jpg", 'type' => 'image/jpeg',
            'ext' => 'jpg', 'type_2026' => null, 'size' => strlen($tart), 'width' => 10, 'height' => 10, 'sha256' => hash('sha256', $tart),
            'title' => $id, 'eredeti_nev' => "{$id}.jpg", 'created' => '2026-07-23 15:07:26+00', ...$e];
        $tartalom = ['g1' => 'GALERIAKEP', 'l1' => 'LOGO', 'p1' => 'BORITO', 'z1' => 'ZART', 'r1' => 'RENDEZETLEN'];
        $leiras = [
            'fajlok_szama' => 6, 'sorsok' => ['onkentes' => 1],
            'szintek' => [['id' => 't1', 'name' => 'Társszervezők', 'name_ro' => 'Coorganizatori', 'sort' => 11]],
            'galeriak' => [['id' => 'gal', 'year' => 2013, 'title_hu' => 'Forgatag 2013', 'title_ro' => null, 'credit' => null, 'sort' => 1]],
            'tamogatok' => [['id' => 's1', 'name' => 'FomCo', 'tier' => 't1', 'url' => null, 'sort' => 0, 'logo' => 'l1']],
            'fajlok' => [
                $fajl('g1', $tartalom['g1'], ['sors' => 'galeria', 'gyujtemeny' => ['galeriak'], 'license' => 'sajat', 'galeria' => ['id' => 'gal', 'sort' => 1], 'note' => 'g']),
                $fajl('l1', $tartalom['l1'], ['sors' => 'tamogato', 'gyujtemeny' => ['tamogatok'], 'license' => 'sajat', 'rights_holder' => 'FomCo', 'tamogato' => 's1', 'note' => 'l']),
                $fajl('p1', $tartalom['p1'], ['sors' => 'program2026', 'gyujtemeny' => ['programok-2026'], 'license' => 'minden_jog_fenntartva', 'rights_holder' => 'Kolompos', 'photographer' => 'Kolompos', 'note' => '2026-os program borítója: X']),
                $fajl('z1', $tartalom['z1'], ['sors' => 'bekuldes_zart', 'gyujtemeny' => [], 'license' => 'minden_jog_fenntartva', 'note' => 'z']),
                $fajl('r1', $tartalom['r1'], ['sors' => 'rendezetlen', 'gyujtemeny' => [], 'license' => 'sajat', 'note' => 'r']),
            ],
        ];
        file_put_contents("{$dir}/leiras.json", json_encode($leiras));
        $zip = new ZipArchive();
        $zip->open("{$dir}/import-2026-1.zip", ZipArchive::CREATE);
        foreach ($tartalom as $id => $t) {
            $zip->addFromString("fajlok/{$id}.jpg", $t);
        }
        $zip->close();

        $j = (new MediatarImport($dir, "{$dir}/leiras.json"))->run(fn () => null);

        $this->assertSame([], $j['hibak'], implode("\n", $j['hibak']));
        $this->assertSame(4, Media::where('origin', MediaOrigin::Import2026)->count(), '4 nyilvános (a zárt NEM a médiatárba)');
        $this->assertTrue(Storage::disk('local')->exists('bekuldesek-2026/z1.jpg'), 'a meg nem jelent program fotója a zárt tárban');
        $this->assertFalse(Storage::disk('mediatar')->exists('z1.jpg'));
        $this->assertSame(['g1'], Gallery::where('legacy_id', 'gal')->sole()->media->pluck('legacy_id')->all(), 'galériakép a galériájához');
        $sp = Sponsor::where('legacy_id', 's1')->sole();
        $this->assertSame('l1', $sp->logo->legacy_id, 'logó a támogatóhoz');
        $this->assertSame('Társszervezők', $sp->tier->name);
        $this->assertSame(2026, $sp->tier->year, 'a 2026-os szint nem keveredik a 2027-es kezdőlistával');
        $p1 = Media::where('legacy_id', 'p1')->sole();
        $this->assertTrue($p1->isIn(Gyujtemeny::Programok2026));
        $this->assertSame(['Kolompos', 'Kolompos', MediaLicense::MindenJogFenntartva], [$p1->rights_holder, $p1->photographer_credit, $p1->license]);
        $this->assertSame('2026-07-23', $p1->created_at->toDateString(), 'a 2026-os feltöltés dátuma megmarad');
        $this->assertTrue(Media::where('legacy_id', 'r1')->sole()->collections()->doesntExist(), 'semmi nem hivatkozott rá → Rendezetlen');
        $this->assertFileDoesNotExist("{$dir}/import-2026-1.zip", 'a teljesen behozott zip törlődik (hely a tárhelyen)');
        $this->assertStringContainsString('galéria 1/1, logó 1/1', $j['osszegzes']);

        // Ismétlés: semmi nem duplázódik, csak ellenőriz.
        $j2 = (new MediatarImport($dir, "{$dir}/leiras.json"))->run(fn () => null);
        $this->assertSame([], $j2['hibak']);
        $this->assertSame(4, Media::where('origin', MediaOrigin::Import2026)->count());

        // Ha egy fájl eltűnik a tárból, az SZÁMSZERŰEN kiderül (néma hiba tilos).
        Storage::disk('mediatar')->delete('g1.jpg');
        $j3 = (new MediatarImport($dir, "{$dir}/leiras.json"))->run(fn () => null);
        $this->assertStringContainsString('1 fájl HIÁNYZIK', $j3['osszegzes']);
        $this->assertCount(1, $j3['hibak']);

        // A csomag már nincs fent (a zip törlődött) → a behozatal LEZÁRT: egy szándékosan törölt 2026-os kép nem hiba.
        Storage::disk('mediatar')->put('g1.jpg', $tartalom['g1']);
        Media::where('legacy_id', 'r1')->sole()->delete();
        $j4 = (new MediatarImport($dir, "{$dir}/leiras.json"))->run(fn () => null);
        $this->assertSame([], $j4['hibak'], 'a felhasználó által törölt kép nem behozatali hiba');
        $this->assertStringContainsString('lezárva', $j4['osszegzes']);
    }

    public function test_kicsomagolt_mappabol_athelyezve_es_takaritva(): void
    {
        // A tárhely telepítő-PHP-jában nincs zip-kezelő: a felhasználó a Fájlkezelőben csomagol ki (import-2026/fajlok/).
        $dir = sys_get_temp_dir() . '/mt-' . uniqid();
        mkdir("{$dir}/fajlok", 0777, true);
        file_put_contents("{$dir}/fajlok/k.jpg", 'KICSOMAGOLT');
        file_put_contents("{$dir}/leiras.json", json_encode(['fajlok_szama' => 1, 'sorsok' => [], 'szintek' => [], 'galeriak' => [], 'tamogatok' => [],
            'fajlok' => [['id' => 'k', 'forras' => 'k.jpg', 'cel' => 'k.jpg', 'type' => 'image/jpeg', 'ext' => 'jpg', 'type_2026' => null,
                'size' => 11, 'width' => null, 'height' => null, 'sha256' => hash('sha256', 'KICSOMAGOLT'), 'title' => 'k', 'eredeti_nev' => 'k.jpg',
                'created' => '2026-07-01', 'sorsok' => null, 'sors' => 'rendezetlen', 'gyujtemeny' => [], 'note' => 'k']]]));

        $j = (new MediatarImport($dir, "{$dir}/leiras.json"))->run(fn () => null);

        $this->assertSame([], $j['hibak']);
        $this->assertSame('KICSOMAGOLT', Storage::disk('mediatar')->get('k.jpg'));
        $this->assertFileDoesNotExist("{$dir}/fajlok/k.jpg", 'áthelyezés, nem másolás (nem foglal kétszer helyet)');
        $this->assertDirectoryDoesNotExist("{$dir}/fajlok", 'a kiürült mappa törlődik');
    }

    public function test_serult_fajl_nem_kerul_be(): void
    {
        $dir = sys_get_temp_dir() . '/mt-' . uniqid();
        mkdir("{$dir}/fajlok", 0777, true);
        file_put_contents("{$dir}/fajlok/x.jpg", 'MASIK-TARTALOM');
        file_put_contents("{$dir}/leiras.json", json_encode(['fajlok_szama' => 1, 'sorsok' => [], 'szintek' => [], 'galeriak' => [], 'tamogatok' => [],
            'fajlok' => [['id' => 'x', 'forras' => 'x.jpg', 'cel' => 'x.jpg', 'type' => 'image/jpeg', 'ext' => 'jpg', 'type_2026' => null,
                'size' => 4, 'width' => null, 'height' => null, 'sha256' => hash('sha256', 'JOKEP'), 'title' => 'x', 'eredeti_nev' => 'x.jpg',
                'created' => '2026-07-01', 'sors' => 'rendezetlen', 'gyujtemeny' => [], 'note' => 'x']]]));

        $j = (new MediatarImport($dir, "{$dir}/leiras.json"))->run(fn () => null);

        $this->assertSame(0, Media::where('origin', MediaOrigin::Import2026)->count());
        $this->assertFalse(Storage::disk('mediatar')->exists('x.jpg'));
        $this->assertStringContainsString('1 fájl HIÁNYZIK', $j['osszegzes']);
    }
}
