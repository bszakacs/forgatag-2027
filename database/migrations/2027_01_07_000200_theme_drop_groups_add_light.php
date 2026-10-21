<?php

use App\Support\Theme;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A KINT LÉVŐ téma igazítása (négy-hely szabály: a tárolt érték is menjen):
 *  · a kategóriacsoport-színek (colors.groups) kivezetése — tulajdonosi döntés, 2026-10-21;
 *  · a világos mód értékei (.light) a kezdő témából (a 2026-os beküldő űrlap világos palettája).
 * ÚJ verzióként publikál (az előzmény megmarad). Üres táblánál (friss telepítés) nem csinál semmit —
 * ott a forgatag:torzsadatok már az új kezdő témát tölti be.
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
        if (! isset($d['colors']['groups']) && isset($d['colors']['bg']['light'])) {
            return; // már átállt
        }
        $gyari = json_decode((string) file_get_contents(base_path('seed/tema/tema.json')), true);
        unset($d['colors']['groups']);
        foreach ($gyari['colors'] as $k => $v) {
            if (is_array($v) && array_key_exists('light', $v) && isset($d['colors'][$k])) {
                $d['colors'][$k]['light'] = $v['light'];
            }
        }
        $d['glow']['alpha']['light'] = $gyari['glow']['alpha']['light'];

        Theme::flush();
        Theme::publish($d, null, 'Kategóriacsoport-színek kivezetése + világos mód színei (a 2026-os beküldő űrlapé)', 'migracio');
    }

    public function down(): void
    {
        // Szándékosan üres: a verzió append-only; visszaállni a /tema előzményéből lehet.
    }
};
