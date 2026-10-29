<?php

use App\Support\TartalomHtml;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A hírek és az aloldal-blokkok szövege Markdown helyett HTML (WYSIWYG szerkesztő, 2026-10-29). A meglévő
 * Markdown-szövegeket átalakítja (App\Support\TartalomHtml); ami már HTML, ahhoz nem nyúl.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['news_items', 'page_blocks'] as $tabla) {
            DB::table($tabla)->where(fn ($q) => $q->whereNotNull('body_hu')->orWhereNotNull('body_ro'))
                ->orderBy('id')->each(function ($sor) use ($tabla) {
                    $uj = ['body_hu' => TartalomHtml::markdownbol($sor->body_hu), 'body_ro' => TartalomHtml::markdownbol($sor->body_ro)];
                    if ($uj['body_hu'] !== $sor->body_hu || $uj['body_ro'] !== $sor->body_ro) {
                        DB::table($tabla)->where('id', $sor->id)->update($uj);
                    }
                });
        }
    }

    public function down(): void
    {
        // HTML → Markdown visszaalakítás nincs; a forrás a 2026-os mentésben megvan.
    }
};
