<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 5. adósság: a kezdő kategórialista a 2025-ös 24 volt, holott 2026-ban 23 maradt (MENTES-LELTAR.md 1.):
 * öt megszűnt, négy új jött. A seed/categories.csv már a 2026-os listát hozza (üres adatbázisnál az tölt);
 * ez a lépés a MEGLÉVŐ adatbázist (helyi, proba) igazítja hozzá.
 *
 * Óvatosan: megszűnt kategóriát CSAK akkor töröl, ha semmi nem hivatkozik rá (program, programgazda,
 * kategóriagazda) — különben marad, és a kimenet kiírja. A meglévő neveket nem írja át; csak a hiányzó román
 * nevet pótolja. Üres táblához nem nyúl (azt a forgatag:torzsadatok tölti a seedből).
 */
return new class extends Migration
{
    private const MEGSZUNT = ['erdelyi-neptanc-antologia', 'ifi-setany', 'kandake-a-forgatagon', 'kerekasztal-beszelgetesek', 'verbunk'];

    public function up(): void
    {
        if (DB::table('categories')->count() === 0) {
            return;
        }

        foreach (self::MEGSZUNT as $slug) {
            $id = DB::table('categories')->where('slug', $slug)->value('id');
            if ($id === null) {
                continue;
            }
            $hasznalt = DB::table('program_items')->where('category_id', $id)->exists()
                || DB::table('category_program_item')->where('category_id', $id)->exists()
                || DB::table('category_program_manager')->where('category_id', $id)->exists()
                || DB::table('category_user')->where('category_id', $id)->exists();
            if ($hasznalt) {
                echo "KATEGÓRIA: „{$slug}” megszűnt 2026-ban, de még hivatkoznak rá — NEM töröltem.\n";

                continue;
            }
            DB::table('categories')->where('id', $id)->delete();
        }

        $h = fopen(base_path('seed/categories.csv'), 'r');
        fgetcsv($h, escape: '');
        $max = (int) DB::table('categories')->max('sort');
        while (($sor = fgetcsv($h, escape: '')) !== false) {
            if ($sor === [null]) {
                continue;
            }
            [$slug, $hu, $ro] = array_map('trim', $sor);
            $meglevo = DB::table('categories')->where('slug', $slug)->first();
            if ($meglevo === null) {
                DB::table('categories')->insert(['slug' => $slug, 'name_hu' => $hu, 'name_ro' => $ro ?: null,
                    'sort' => ++$max, 'created_at' => now(), 'updated_at' => now()]);
            } elseif ($meglevo->name_ro === null && $ro !== '') {
                DB::table('categories')->where('id', $meglevo->id)->update(['name_ro' => $ro]);
            }
        }
        fclose($h);
    }

    public function down(): void
    {
        // Adatigazítás — nincs visszaút (a törölt, használatlan kategória nem hordozott adatot).
    }
};
