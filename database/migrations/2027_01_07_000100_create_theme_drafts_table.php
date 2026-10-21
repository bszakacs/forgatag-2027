<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A /tema szerkesztő piszkozata (EGY közös sor) + a kontraszt-felülbírálás indoklása a verzión.
 * A piszkozat a publikált témától KÜLÖN él: amíg nincs publikálva, a látogató semmit nem lát belőle.
 * A `base_version_id` mutatja, melyik publikált verzióra épül — ha közben más publikált, a
 * szerkesztő figyelmeztet (a 2026-os „elavult vázlat felülírta a palettát” eset ellen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_drafts', function (Blueprint $table) {
            $table->id();
            $table->json('data');
            $table->foreignId('base_version_id')->nullable()->constrained('theme_versions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('saved_by')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::table('theme_versions', function (Blueprint $table) {
            $table->text('contrast_override')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('theme_versions', fn (Blueprint $t) => $t->dropColumn('contrast_override'));
        Schema::dropIfExists('theme_drafts');
    }
};
