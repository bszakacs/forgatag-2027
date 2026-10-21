<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A téma (arculat) publikált verziói — APPEND-ONLY. A LEGUTOLSÓ sor a kint lévő téma.
 * Egy publikálás = egy új sor: a téma-adat (JSON), a belőle generált CSS fájlneve (benne a tartalom
 * lenyomata), ki és miért publikálta. Visszaállás = egy régebbi adat újrapublikálása (új sorként).
 * A fájlok (generált CSS, logó, favicon, firkák): public/arculat/ — lásd App\Support\Theme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_versions', function (Blueprint $table) {
            $table->id();
            $table->json('data');
            $table->string('css_file');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('published_by')->nullable();
            $table->string('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_versions');
    }
};
