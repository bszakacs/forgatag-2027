<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FavoriteList;
use App\Models\ProgramItem;
use App\Models\Venue;
use App\Support\Kedvencek;
use App\Support\LapSablon;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KEDVENCEK ÉS NAPTÁR (PROMPT-21): a lista a kulcsával (belépés és személyes adat nélkül), műveletenként írva; a lapok
 * forrásában NINCS látogatói adat (gyorsítótárazható — a szív semlegesen érkezik); a „Kedvenc programjaim” lap kártyái a
 * programkereső sablonjából, átfedés-jelzéssel; a naptárfájlban a VALÓDI időpont (a 23:00–00:30 másnap 00:30-kor ér véget).
 */
class KedvencekTest extends TestCase
{
    use RefreshDatabase;

    private Category $kat;

    private Venue $hely;

    protected function setUp(): void
    {
        parent::setUp();
        $seed = json_decode((string) file_get_contents(base_path('seed/settings.json')), true);
        Settings::save(array_intersect_key($seed, array_flip(['festival', 'submission', 'public', 'notifications'])));
        LapSablon::szinkron();
        $this->kat = Category::create(['slug' => 'koncert', 'name_hu' => 'Koncert', 'name_ro' => 'Concert', 'sort' => 1]);
        $this->hely = Venue::create(['slug' => 'var', 'name' => 'Vár, Borkert', 'address' => 'Avram Iancu 2']);
    }

    /** @param list<array{0: string, 1: string, 2?: ?string}> $idopontok */
    private function program(string $cim, array $idopontok, string $allapot = 'published'): ProgramItem
    {
        $p = ProgramItem::create(['title' => $cim, 'description' => "{$cim} leírása", 'status' => $allapot,
            'category_id' => $this->kat->id, 'venue_id' => $this->hely->id]);
        foreach ($idopontok as $o) {
            $p->occurrences()->create(['date' => $o[0], 'start_time' => $o[1], 'end_time' => $o[2] ?? null]);
        }

        return $p;
    }

    public function test_a_lista_letrejon_muveletenkent_valtozik_es_torolheto(): void
    {
        $uj = $this->postJson('/kedvencek-api/lista', ['ids' => [5, '7', 'x', -1, 5, 0]])->assertCreated();
        $this->assertStringContainsString('no-store', $uj->headers->get('Cache-Control'));
        $kulcs = $uj->json('kulcs');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $kulcs);
        $this->assertSame([5, 7], $uj->json('ids'));

        // Két eszköz: az egyik hozzáad, a másik levesz — egyik sem írja felül a másikat.
        $this->postJson("/kedvencek-api/lista/{$kulcs}", ['ops' => [[9, true]]])->assertOk()->assertJson(['ids' => [5, 7, 9]]);
        $this->postJson("/kedvencek-api/lista/{$kulcs}", ['ops' => [[5, false], ['rossz', true]]])->assertOk()->assertJson(['ids' => [7, 9]]);
        $this->getJson("/kedvencek-api/lista/{$kulcs}")->assertOk()->assertExactJson(['ok' => true, 'ids' => [7, 9]]);

        $this->deleteJson("/kedvencek-api/lista/{$kulcs}")->assertOk();
        $this->assertSame(0, FavoriteList::count());
        $this->getJson("/kedvencek-api/lista/{$kulcs}")->assertNotFound()->assertJson(['hiba' => 'nincs']);
        $this->postJson("/kedvencek-api/lista/{$kulcs}", ['ops' => [[1, true]]])->assertNotFound();
        $this->getJson('/kedvencek-api/lista/nem-kulcs')->assertNotFound();
    }

    public function test_a_lista_merete_korlatos(): void
    {
        $this->assertCount(Kedvencek::MAX, Kedvencek::tisztit(range(1, Kedvencek::MAX + 50)));
        $kulcs = $this->postJson('/kedvencek-api/lista', ['ids' => range(1, Kedvencek::MAX)])->json('kulcs');
        $this->postJson("/kedvencek-api/lista/{$kulcs}", ['ops' => [[99999, true]]])->assertOk();
        $this->assertCount(Kedvencek::MAX, FavoriteList::first()->program_ids);
    }

    /** 🔴 A gyorsítótárazhatóság: a lapban semmi nem árulja el, kinek mi van megjelölve — a szív semlegesen és működőn érkezik. */
    public function test_a_lapok_semleges_szivvel_erkeznek_es_nincs_bennuk_latogatoi_adat(): void
    {
        $p = $this->program('Esti koncert', [['2027-08-27', '20:00', '21:00']]);
        $kulcs = $this->postJson('/kedvencek-api/lista', ['ids' => [$p->id]])->json('kulcs');

        foreach (['/programok?nap=2027-08-27', '/programok?nap=2027-08-27&program=' . $p->slug, '/program/' . $p->slug, '/kedvencek', '/ro/kedvencek'] as $url) {
            $html = $this->withCookie('fg-kedvencek', '[' . $p->id . ']')->get($url)->assertOk()->getContent();
            preg_match_all('~<[^>]*\bdata-save="[^>]*>~', $html, $szivek);
            foreach ($szivek[0] as $sziv) {
                $this->assertStringContainsString('aria-pressed="false"', $sziv, $url);
                $this->assertStringNotContainsString('is-fav', $sziv, $url);
                $this->assertDoesNotMatchRegularExpression('~\sdisabled[\s>]~', $sziv, $url . ': a szív nem lehet tiltott');
            }
            $this->assertStringNotContainsString($kulcs, $html, $url);
            $this->assertStringContainsString('js/kedvencek.js', $html, $url);
        }
        // A fejléc szíve a kedvencek lapjára visz; a pötty rejtve érkezik.
        $this->get('/programok')->assertSee('class="cart-btn fg-fav-btn"', false)->assertSee('id="favDot" hidden', false);
    }

    public function test_a_kartyak_a_kereso_sablonjabol_jonnek_atfedessel_es_csak_nyilvanos_program(): void
    {
        $a = $this->program('Borkóstoló', [['2027-08-27', '23:00', '00:30'], ['2027-08-28', '18:00', '19:00']]);
        $b = $this->program('Éjféli zene', [['2027-08-27', '23:30', '01:00']]);
        $c = $this->program('Vázlat', [['2027-08-27', '10:00', '11:00']], 'draft');

        $html = $this->get("/kedvencek/kartyak?p={$a->id},{$b->id},{$c->id},abc")->assertOk()->getContent();
        $this->assertStringContainsString('<b>3</b>', $html);              // 3 időpont
        $this->assertSame(2, substr_count($html, 'class="kv-day"'));       // 2 nap
        $this->assertSame(2, substr_count($html, 'kv-clash"'));            // a két átfedő kártya
        $this->assertStringContainsString('1 átfedés', $html);
        $this->assertStringContainsString('data-first-clash', $html);
        $this->assertStringContainsString('class="pcard pk-card kv-card', $html);
        $this->assertStringContainsString('data-save="' . $a->id . '"', $html);
        $this->assertStringNotContainsString('Vázlat', $html);
        // A kártya a KERESŐ felugró ablakára visz (minden a keresőbe vezet vissza).
        $this->assertMatchesRegularExpression('~href="/programok\?nap=2027-08-27&amp;program=' . $a->slug . '&amp;idopont=\d+" data-kv-open~', $html);
        $this->assertStringContainsString('Augusztus 27., péntek', $html);

        $this->get('/kedvencek/kartyak?p=')->assertOk()->assertDontSee('kv-day', false);
    }

    /** A FESZTIVÁLNAP SZABÁLYA: a péntek 23:00–00:30 szombat 00:30-kor ér véget, a péntek 00:30-as kezdés szombat hajnal. */
    public function test_a_naptarfajlban_a_valodi_idopont_szerepel(): void
    {
        $p = $this->program('Borkóstoló', [['2027-08-27', '23:00', '00:30']]);
        $q = $this->program('Hajnali tánc', [['2027-08-27', '00:30', '02:00'], ['2027-08-28', '20:00', null]]);

        $ics = $this->get("/program/{$p->slug}.ics")->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();
        // Bukarest nyáron UTC+3: 23:00 → 20:00Z, a vég MÁSNAP 00:30 → 27-én 21:30Z (nem 26-án 21:30Z).
        $this->assertStringContainsString("DTSTART:20270827T200000Z\r\nDTEND:20270827T213000Z", $ics);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString("SUMMARY:Borkóstoló\r\n", $ics);
        $this->assertStringContainsString('LOCATION:Vár › Borkert · Avram Iancu 2', $ics);
        foreach (explode("\r\n", $ics) as $sor) {
            $this->assertLessThanOrEqual(75, strlen($sor));
        }

        $ics2 = $this->get("/program/{$q->slug}.ics")->getContent();
        $this->assertStringContainsString("DTSTART:20270827T213000Z\r\nDTEND:20270827T230000Z", $ics2); // szombat 00:30–02:00
        $this->assertStringContainsString("DTSTART:20270828T170000Z\r\nDTEND:20270828T180000Z", $ics2); // vég nélkül: 1 óra
        $this->assertStringContainsString('egy órás', str_replace("\r\n ", '', $ics2));

        $egy = $q->occurrences()->orderBy('date')->first();
        $this->assertSame(1, substr_count($this->get("/program/{$q->slug}.ics?idopont={$egy->id}")->getContent(), 'BEGIN:VEVENT'));
        $this->get("/program/{$q->slug}.ics?idopont=999999")->assertNotFound();

        // Az egész lista egy fájlban, románul is.
        $this->assertSame(3, substr_count($this->get("/ro/kedvencek/naptar.ics?p={$p->id},{$q->id}")->getContent(), 'BEGIN:VEVENT'));

        // Lemondott program nem kerül naptárba.
        $l = $this->program('Elmarad', [['2027-08-27', '18:00', '19:00']], 'cancelled');
        $this->get("/program/{$l->slug}.ics")->assertNotFound();
    }

    public function test_a_felugro_ablak_gombjai_mukodnek_es_a_kedvencek_lapjan_helyben_zar(): void
    {
        $p = $this->program('Borkóstoló', [['2027-08-27', '23:00', '00:30']]);
        $url = '/programok?nap=2027-08-27&program=' . $p->slug;
        $ablak = $this->withHeaders(['HX-Request' => 'true', 'HX-Target' => 'pkModal'])->get($url)->assertOk()->assertHeader('HX-Push-Url');
        $html = $ablak->getContent();
        $this->assertMatchesRegularExpression('~id="pkMIcs"[^>]*data-naptar="[^"]*/program/' . $p->slug . '\.ics\?idopont=~', $html);
        $this->assertStringContainsString('20270827T200000Z', $html);
        $this->assertMatchesRegularExpression('~id="pkMSave" aria-pressed="false" data-save="' . $p->id . '"\s*>~', $html);

        $kv = $this->flushHeaders()->withHeaders(['HX-Request' => 'true', 'HX-Target' => 'pkModal', 'X-Kedvencek' => '1'])->get($url)->assertOk();
        $this->assertFalse($kv->headers->has('HX-Push-Url'));
        $this->assertMatchesRegularExpression('~id="pkMSave"[^>]*hidden~', $kv->getContent());
        $this->assertDoesNotMatchRegularExpression('~class="pk-close"[^>]*hx-get~', $kv->getContent());
    }

    public function test_a_kedvencek_lapja_mindket_allapotot_es_a_link_helyet_hozza(): void
    {
        $this->program('Ajánlott koncert', [['2027-08-27', '20:00', '21:00']]);
        foreach (['/kedvencek' => ['Az én ', 'Ez a listád linkje', 'Még nincs mentett programod', 'Ajánlott koncert'], '/ro/kedvencek' => ['Acesta este linkul listei tale', 'Încă nu ai program salvat']] as $url => $szovegek) {
            $r = $this->get($url)->assertOk()->assertSee('<meta name="robots" content="noindex">', false)
                ->assertSee('id="kvList"', false)->assertSee('id="kvEmpty"', false)->assertSee('id="kvLink"', false)->assertSee('id="kvUndo"', false);
            foreach ($szovegek as $s) {
                $r->assertSee($s, false);
            }
        }
    }
}
