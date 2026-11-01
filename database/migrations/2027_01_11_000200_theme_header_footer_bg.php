<?php

use App\Support\Theme;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A FEJLÉC ÉS A LÁBLÉC HÁTTERE a témába (tulajdonosi kérés, 2026-10-30): 2026-ban a chrome.css-be égetett rgba volt
 * (fejléc rgba(24,19,52,.78), lábléc rgba(16,12,36,.7)), a 2027-es keret helyette a --bg-ből kevert — ezért más volt a
 * színe. Most két téma-token (colors.headerBg / colors.footerBg) a 2026-os értékkel; az Arculatban állítható.
 * A kint lévő témát ÚJ verzióként igazítja (append-only); üres táblánál nem tesz semmit (a kezdő téma már tartalmazza).
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
        if (isset($d['colors']['headerBg'], $d['colors']['footerBg'])) {
            return; // már átállt
        }
        $gyari = json_decode((string) file_get_contents(base_path('seed/tema/tema.json')), true);
        $d['colors']['headerBg'] ??= $gyari['colors']['headerBg'];
        $d['colors']['footerBg'] ??= $gyari['colors']['footerBg'];
        Theme::flush();
        Theme::publish($d, null, 'A fejléc és a lábléc háttere a témába (a 2026-os értékek)', 'migracio');
    }

    public function down(): void
    {
        // Szándékosan üres: a verzió append-only; visszaállni a /tema előzményéből lehet.
    }
};
