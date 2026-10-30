<?php

namespace App\Support;

use App\Enums\ProgramSource;
use App\Models\Category;
use App\Models\Media;
use App\Models\ProgramItem;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * A 2026-os programok TESZTADATKÉNT (CLAUDE.md 7.: „a 2026-os programokat nem importáljuk, néhányat csak
 * tesztelésre”). A programkeresőt valós mennyiségen és szövegen kell kipróbálni — hosszú címek, éjfélen átnyúló
 * időpontok, több napos kiállítások, román fordítások —, ezért a 241-ből mind jön, AMELYIKNEK a helyszíne a 2027-es
 * listában van (2026-10-30: 180), `is_test` jelöléssel: az adminban TESZT jelvény, és Beállítások → Tesztadatok alatt EGY
 * lépésben törölhető. A HELYSZÍN NÉLKÜLI programok (61) nem jönnek, és a borítójuk a médiatárból is törlődik
 * (tulajdonosi döntés, 2026-10-30: „a tesztelésnél úgysem érnek semmit”, a képeikkel együtt).
 *
 * · A dátumok +364 nappal tolódnak: így a hét napja is egyezik (2026. aug. 26. szerda → 2027. aug. 25. szerda).
 * · Személyes adat NINCS a csomagban (kapcsolattartó neve, e-mail, beküldő): csak a nyilvánosan is kint lévő szöveg.
 * · Kategória és helyszín a SLUG alapján; amit a 2027-es törzsadat nem ismer, az üresen marad, és a csomag
 *   számszerűen rögzíti (néma kimaradás nincs).
 * · A borító a médiatár „Programok 2026” képe (legacy_id): ez a TAVALYI program saját képe, ezért a teszt-programhoz
 *   rendelhető (a ProgramItem őre csak a 2027-es programot tiltja).
 * · Csak EGYSZER: a jelölés fájlban (storage/app/import-2026/programok-teszt.json), hogy a tesztadat törlése után
 *   a következő telepítés ne hozza vissza.
 */
class ProgramTesztImport
{
    public const CSOMAG = 'database/import/programok-2026-teszt.json';

    public const JELOLES = 'import-2026/programok-teszt.json';

    private const ELTOLAS_NAP = 364;

    /** A fejlesztőgépen: a mentésből → a csomag (a tárolóba kerül). @return array<string, int> */
    public static function csomagol(string $mentes): array
    {
        $t = Mentes2026::tablak($mentes, ['program_item', 'program_occurrence', 'translation', 'category', 'venue']);
        $kat = array_column($t['category'], 'slug', 'id');
        $hely = array_column($t['venue'], 'slug', 'id');
        $idopontok = collect($t['program_occurrence'])->groupBy('program_id');
        $forditasok = collect($t['translation'])->where('entity', 'program_item')->where('locale', 'ro')->groupBy('entity_id');

        $programok = [];
        foreach ($t['program_item'] as $p) {
            if ($p['status'] !== 'published') {
                continue;
            }
            $programok[] = [
                'legacy_id' => $p['id'],
                'slug' => $p['slug'],
                'title' => $p['title'],
                'description' => (string) $p['description'],
                'organizer_names' => $p['organizer_names'],
                'entry_type' => $p['entry_type'],
                'ticket_info' => $p['ticket_info'],
                'schedule_note' => $p['schedule_note'],
                'extra_fields' => json_decode((string) $p['extra_fields'], true) ?: null,
                'category' => $kat[$p['category']] ?? null,
                'venue' => $hely[$p['venue']] ?? null,
                'cover' => $p['cover_image'],
                'idopontok' => $idopontok->get($p['id'], collect())->map(fn ($o) => [
                    'date' => CarbonImmutable::parse($o['date'])->addDays(self::ELTOLAS_NAP)->format('Y-m-d'),
                    'start_time' => $o['start_time'], 'end_time' => $o['end_time'], 'note' => $o['note'],
                ])->values()->all(),
                'forditasok' => $forditasok->get($p['id'], collect())->map(fn ($f) => [
                    'field' => $f['field'], 'text' => $f['translated_text'], 'status' => $f['status'], 'hash' => $f['source_text_hash'],
                ])->values()->all(),
            ];
        }

        file_put_contents(base_path(self::CSOMAG), json_encode([
            '_forras' => 'A 2026-os programok TESZTADATKÉNT (App\Support\ProgramTesztImport). Dátum +364 nap. Személyes adat nincs benne.',
            'programok' => $programok,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ['program' => count($programok), 'időpont' => array_sum(array_map(fn ($p) => count($p['idopontok']), $programok))];
    }

    /** A szerveren (telepítéskor): a csomagból az adatbázisba, egyszer. */
    public function run(callable $naplo): void
    {
        if (Storage::disk('local')->exists(self::JELOLES)) {
            $naplo('egyszer már behozva (' . self::JELOLES . '), nem hozom újra.');

            return;
        }
        $csomag = json_decode((string) @file_get_contents(base_path(self::CSOMAG)), true)
            ?? throw new RuntimeException('a csomag nem olvasható: ' . self::CSOMAG);

        $kat = Category::query()->pluck('id', 'slug');
        $hely = Venue::query()->pluck('id', 'slug');
        $borito = Media::query()->whereNotNull('legacy_id')->pluck('id', 'legacy_id');
        $db = ['program' => 0, 'időpont' => 0, 'fordítás' => 0, 'kategória nélkül' => 0, 'kép nélkül' => 0, 'kihagyva (helyszín nélkül)' => 0];
        $nincsHely = [];
        $kihagyottBoritok = [];

        DB::transaction(function () use ($csomag, $kat, $hely, $borito, &$db, &$nincsHely, &$kihagyottBoritok): void {
            foreach ($csomag['programok'] as $p) {
                // HELYSZÍN NÉLKÜL NEM JÖN (tulajdonosi döntés, 2026-10-30): a tesztelésnél nem érnek semmit — a
                // képükkel együtt mennek (lent). Ide tartozik a 2027-es listában nem szereplő helyszín is.
                if (! isset($hely[$p['venue']])) {
                    $db['kihagyva (helyszín nélkül)']++;
                    $nincsHely[$p['venue'] ?? '—'] = true;
                    $kihagyottBoritok[] = $p['cover'];

                    continue;
                }
                $program = new ProgramItem([
                    'title' => $p['title'], 'description' => $p['description'], 'organizer_names' => $p['organizer_names'],
                    'status' => 'published', 'entry_type' => $p['entry_type'], 'ticket_info' => $p['ticket_info'],
                    'schedule_note' => $p['schedule_note'], 'extra_fields' => $p['extra_fields'],
                    'category_id' => $kat[$p['category']] ?? null, 'venue_id' => $hely[$p['venue']] ?? null,
                    'source' => ProgramSource::Internal, 'photo_rights_declared' => true,
                ]);
                $program->slug = ProgramItem::uniqueSlug($p['slug'] ?: $p['title']);
                $program->is_test = true;
                $program->cover_media_id = $borito[$p['cover']] ?? null;
                $program->save();

                foreach ($p['idopontok'] as $o) {
                    $program->occurrences()->create($o);
                    $db['időpont']++;
                }
                foreach ($p['forditasok'] as $f) {
                    $program->translations()->create(['locale' => 'ro', 'field' => $f['field'], 'translated_text' => $f['text'],
                        'status' => $f['status'], 'source_text_hash' => $f['hash']]);
                    $db['fordítás']++;
                }
                $db['program']++;
                if ($program->category_id === null) {
                    $db['kategória nélkül']++;
                }
                if ($program->cover_media_id === null) {
                    $db['kép nélkül']++;
                }
            }
        });

        $db['törölt kép'] = self::boritokTorlese($kihagyottBoritok);
        Storage::disk('local')->put(self::JELOLES, json_encode(['db' => $db, 'ekkor' => now()->toIso8601String()], JSON_UNESCAPED_UNICODE));
        $naplo(collect($db)->map(fn ($n, $mi) => "{$n} {$mi}")->implode(', ') . ' — mind TESZT jelöléssel.');
        if ($nincsHely) {
            $naplo('kihagyva, mert a helyszíne nincs a 2027-es listában (a képével együtt törölve): ' . implode(', ', array_keys($nincsHely)));
        }
    }

    /**
     * A helyszín nélküli 2026-os programok BORÍTÓI a médiatárból (tulajdonosi döntés, 2026-10-30: „a képeikkel együtt”).
     * Csak a 2026-os behozatalból jött, sehol máshol nem használt kép törlődik (a médiatár őre: Media::usages()); a fájl a
     * modell törlésével megy. A médiatár-behozatal lezárt, ezért ez szerkesztés, nem behozatali hiba.
     *
     * @param  list<?string>  $legacyIdk
     */
    public static function boritokTorlese(array $legacyIdk): int
    {
        $torolt = 0;
        foreach (Media::query()->whereIn('legacy_id', array_filter($legacyIdk))->where('origin', 'import2026')->get() as $m) {
            if ($m->usages() === []) {
                $m->delete();
                $torolt++;
            }
        }

        return $torolt;
    }
}
