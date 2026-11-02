<?php

use App\Support\Theme;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 12 FIRKA-MOTÍVUM A TÉMÁBAN (tulajdonosi döntés, 2026-11-02): 2026-ban a lapokon szórt firkák 12 motívumból
 * válogattak, de a témában csak 6 cserélhető hely volt — a másik 6 a kódba volt égetve (forgatag-os lib/decor-motifs.ts).
 * Most mind a 12 feltöltött fájl (CLAUDE.md 2/c). A 7–12. a seed/tema/fajlok/ alatt, a 2026-os témában az 1–6.-on
 * alkalmazott színleképezéssel (a kitöltés var(--doodle-…), így a firka-színek ezekre is hatnak).
 *
 * A kint lévő témát ÚJ verzióként egészíti ki (append-only); a meglévő 6 helyhez nem nyúl. A piszkozatot is kiegészíti,
 * különben a következő mentése a „pontosan 12 firka-fájl” ellenőrzésen elbukna. Üres táblánál nem tesz semmit.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sor = DB::table('theme_versions')->orderByDesc('id')->first();
        if ($sor === null) {
            return;
        }
        $d = json_decode($sor->data, true);
        $ujak = $this->ujMotivumok(count($d['doodles']['motifs'] ?? []));
        if ($ujak !== []) {
            $d['doodles']['motifs'] = array_merge(array_values($d['doodles']['motifs']), $ujak);
            Theme::flush();
            Theme::publish($d, null, 'A 7–12. firka-motívum a témába (2026-ban kódba égetve volt)', 'migracio');
        }

        // A publikálás törli a piszkozatot; ha nem publikáltunk (már 12 volt), de a piszkozat még 6-os, azt egészítjük ki.
        $vazlat = DB::table('theme_drafts')->first();
        if ($vazlat !== null) {
            $v = json_decode($vazlat->data, true);
            $ujak = $this->ujMotivumok(count($v['doodles']['motifs'] ?? []));
            if ($ujak !== []) {
                $v['doodles']['motifs'] = array_merge(array_values($v['doodles']['motifs']), $ujak);
                DB::table('theme_drafts')->update(['data' => json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
        }
    }

    /** A hiányzó helyek (a meglévő darabszámtól 12-ig) a gyári fájlokból, rendes feltöltésként. @return list<string> */
    private function ujMotivumok(int $van): array
    {
        $ujak = [];
        for ($i = $van; $i < Theme::MOTIF_SLOTS; $i++) {
            $ujak[] = Theme::storeAsset(base_path('seed/tema/fajlok/firka-' . ($i + 1) . '.svg'), 'firka-' . ($i + 1));
        }

        return $ujak;
    }

    public function down(): void
    {
        // Szándékosan üres: a verzió append-only; visszaállni a /tema előzményéből lehet.
    }
};
