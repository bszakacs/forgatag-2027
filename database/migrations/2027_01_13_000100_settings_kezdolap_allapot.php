<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A KEZDŐLAP KÉT ÁLLAPOTA (PROMPT-14, tulajdonosi döntés): a „Publikus oldal” beállítás-blokk két új mezőt kap —
 * `home_state` (fesztival = visszaszámláló + Kiemelt programok látszik; utana = a 2026-os mai állapot) és
 * `countdown_time` (a visszaszámláló célja a nyitónapon; 2026-ban 10:00 volt a sablonba égetve).
 * A meglévő blokkhoz csak a HIÁNYZÓ mezőt adja hozzá, a kezdőérték a seed/settings.json-ból jön (a mai állapot: utana).
 * Üres táblánál nem tesz semmit (a forgatag:torzsadatok a teljes blokkot tölti be).
 */
return new class extends Migration
{
    public function up(): void
    {
        $sor = DB::table('settings')->where('key', 'public')->first();
        if ($sor === null) {
            return;
        }
        $ertek = json_decode($sor->value, true) ?? [];
        $seed = json_decode((string) file_get_contents(base_path('seed/settings.json')), true)['public'];
        $uj = $ertek;
        foreach (['home_state', 'countdown_time'] as $mezo) {
            $uj[$mezo] ??= $seed[$mezo];
        }
        if ($uj !== $ertek) {
            DB::table('settings')->where('key', 'public')
                ->update(['value' => json_encode($uj, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Szándékosan üres: a két mező a Beállításokban állítható; törlésük a kezdőlapot állítaná meg.
    }
};
