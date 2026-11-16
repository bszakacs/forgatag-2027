<?php

namespace Tests\Feature;

use App\Enums\MapZone;
use App\Enums\UserRole;
use App\Filament\Pages\TerkepSzerkeszto;
use App\Models\User;
use App\Models\Venue;
use App\Support\HelyszinTerkepImport;
use App\Support\Settings;
use App\Support\Terkep;
use App\Support\TerkepMentes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A térkép-szerkesztő (PROMPT-18): jog (az arculat-szerkesztő NEM fér hozzá), a mentés három állapota (térképen /
 * a szélén / levéve), minden-vagy-semmi hibakezelés, és hogy a telepítés 2026-os térkép-behozatala a szerkesztőben
 * beállított helyszínhez (a levetthez sem) nem nyúl.
 */
class TerkepSzerkesztoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::save([
            'festival' => ['start_date' => '2027-08-25', 'end_date' => '2027-08-29', 'prep_days' => 2, 'opening_day' => '2027-08-25',
                'name_hu' => 'Vásárhelyi Forgatag', 'name_ro' => 'Vâltoarea Mureșeană', 'day_cut_hour' => 3],
            'submission' => ['open' => true, 'closed_message' => 'Zárva.', 'deadline' => null],
            'notifications' => ['topics' => []],
        ]);
    }

    private function mint(UserRole $r): static
    {
        return $this->actingAs(User::create(['name' => $r->label(), 'email' => $r->value . '@forgatag.test',
            'password' => 'titok-jelszo-123', 'role' => $r]), 'web');
    }

    public function test_a_koordinator_megnyitja_a_szerkesztot(): void
    {
        Venue::create(['slug' => 'kulturpalota', 'name' => 'Kultúrpalota']);
        $this->mint(UserRole::Coordinator)->get('/admin/terkep-szerkeszto')
            ->assertOk()->assertSee('me-config', false)->assertSee('Kultúrpalota')->assertSee('terkep-szel.js', false);
    }

    /** @return array<string, array{0: UserRole}> */
    public static function tiltottSzerepkorok(): array
    {
        return ['arculat-szerkesztő' => [UserRole::ThemeEditor], 'kategóriagazda' => [UserRole::CategoryOwner],
            'fordító' => [UserRole::Translator], 'csak olvasó' => [UserRole::ReadOnly]];
    }

    // Szerepkörönként KÜLÖN futás (a munkamenet-őr egy teszten belüli felhasználóváltást kiléptetésnek venne).
    #[\PHPUnit\Framework\Attributes\DataProvider('tiltottSzerepkorok')]
    public function test_mas_szerepkor_nem_fer_hozza_kozvetlen_cimmel_sem(UserRole $r): void
    {
        $this->mint($r)->get('/admin/terkep-szerkeszto')->assertForbidden();
        $v = Venue::create(['slug' => 'var', 'name' => 'Vár']);
        // a Livewire-hívás sem írhat (külön kérés — a jogot ott is meg kell kérdezni)
        try {
            Livewire::test(TerkepSzerkeszto::class)->call('mentes', [['id' => $v->id, 'map_zone' => 'city', 'map_x' => 10, 'map_y' => 10]]);
        } catch (\Throwable) {
            // a lap meg sem nyílik — ez is tiltás
        }
        $this->assertSame(MapZone::None, $v->fresh()->map_zone);
    }

    public function test_a_mentes_harom_allapota(): void
    {
        $a = Venue::create(['slug' => 'a', 'name' => 'A']);
        $b = Venue::create(['slug' => 'b', 'name' => 'B']);
        $c = Venue::create(['slug' => 'c', 'name' => 'C', 'map_zone' => 'city', 'map_x' => 5, 'map_y' => 5, 'map_no' => 3]);

        $this->mint(UserRole::Coordinator);
        $valasz = Livewire::test(TerkepSzerkeszto::class)->call('mentes', [
            ['id' => $a->id, 'map_zone' => 'castle', 'map_x' => 12.345, 'map_y' => 67.8, 'map_no' => 7, 'map_edge' => false, 'map_bearing' => null, 'short_name' => ''],
            ['id' => $b->id, 'map_zone' => 'city', 'map_x' => null, 'map_y' => null, 'map_no' => 41, 'map_edge' => true, 'map_bearing' => 90, 'short_name' => 'Bé'],
            ['id' => $c->id, 'map_zone' => 'none', 'map_x' => null, 'map_y' => null, 'map_no' => null, 'map_edge' => false, 'map_bearing' => null],
        ])->effects['returns'][0] ?? null;
        $this->assertTrue($valasz['ok'] ?? false, json_encode($valasz));
        $this->assertSame(3, $valasz['n']);

        $a->refresh(); $b->refresh(); $c->refresh();
        $this->assertSame([MapZone::Castle, 12.35, 67.8, 7, false], [$a->map_zone, (float) $a->map_x, (float) $a->map_y, $a->map_no, $a->map_edge]);
        $this->assertSame([MapZone::City, null, true, 90, 'Bé'], [$b->map_zone, $b->map_x, $b->map_edge, $b->map_bearing, $b->short_name]);
        $this->assertSame([MapZone::None, null, null], [$c->map_zone, $c->map_x, $c->map_no]);
        $this->assertNotNull($c->map_set_at, 'a levett helyszín is „kézzel beállított”');

        // a publikus térkép adata ugyanezt mutatja: jelölő a várban, szélső jelvény a városban (irány + rövid név)
        $adat = collect(Terkep::adat('hu')['zonak'])->keyBy('zone');
        $this->assertSame('a', $adat['castle']['marks'][0]['slug']);
        $this->assertSame(['slug' => 'b', 'no' => 41, 'name' => 'B', 'label' => 'Bé', 'bearing' => 90], $adat['city']['edges'][0]);
        $this->assertSame([], $adat['city']['marks']);
    }

    public function test_hibas_sor_eseten_semmi_nem_irodik_ki_es_a_hiba_emberi(): void
    {
        $a = Venue::create(['slug' => 'a', 'name' => 'Alfa']);
        $b = Venue::create(['slug' => 'b', 'name' => 'Béta']);
        $jo = ['id' => $a->id, 'map_zone' => 'castle', 'map_x' => 10, 'map_y' => 10];

        $esetek = [
            [['id' => $b->id, 'map_zone' => 'castle', 'map_x' => 120, 'map_y' => 10], 'pozíció kell'],
            [['id' => $b->id, 'map_zone' => 'castle', 'map_x' => null, 'map_y' => null], 'pozíció kell'],
            [['id' => $b->id, 'map_zone' => 'city', 'map_edge' => true, 'map_bearing' => 30], '8 irány'],
            [['id' => $b->id, 'map_zone' => 'hold', 'map_x' => 1, 'map_y' => 1], 'ismeretlen térkép'],
            [['id' => $b->id, 'map_zone' => 'city', 'map_x' => 1, 'map_y' => 1, 'map_no' => 0], 'sorszám'],
            [['id' => 99999, 'map_zone' => 'city', 'map_x' => 1, 'map_y' => 1], 'Ismeretlen helyszín'],
        ];
        foreach ($esetek as [$rossz, $szoveg]) {
            try {
                TerkepMentes::ment([$jo, $rossz]);
                $this->fail('el kellett volna utasítani: ' . json_encode($rossz));
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($szoveg, $e->getMessage());
            }
            $this->assertSame(MapZone::None, $a->fresh()->map_zone, 'minden-vagy-semmi: a jó sor sem íródhat ki');
        }

        // a felületen a hiba látszik (ok:false + szöveg), nem néma
        $this->mint(UserRole::Coordinator);
        $valasz = Livewire::test(TerkepSzerkeszto::class)->call('mentes', [$jo, $esetek[0][0]])->effects['returns'][0] ?? null;
        $this->assertFalse($valasz['ok']);
        $this->assertStringContainsString('Béta', $valasz['hiba']);
    }

    public function test_a_2026_os_behozatal_a_szerkesztoben_beallitott_helyszinhez_nem_nyul(): void
    {
        // A csomagban a „var” helyszínnek van 2026-os pozíciója (25-ös jelölő a várostérképen).
        $var = Venue::create(['slug' => 'var', 'name' => 'Vár']);
        TerkepMentes::ment([['id' => $var->id, 'map_zone' => 'none']]); // a szerkesztőben LEVETTÉK

        (new HelyszinTerkepImport)->run(fn () => null);

        $var->refresh();
        $this->assertSame(MapZone::None, $var->map_zone, 'a levett helyszínt a telepítés nem teheti vissza');
        $this->assertNull($var->map_no);
    }
}
