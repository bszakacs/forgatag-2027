<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programok. A státusz, a belépés típusa és a forrás értékkészlete az app/Enums alatt él
 * (egy forrás) — az oszlop szöveg, hogy egy új érték ne kívánjon sémamódosítást.
 *
 * Az időpontok KIZÁRÓLAG a program_occurrences táblában vannak. (2026-ban a napok két helyen
 * is szerepeltek — recurrence_days + occurrences —, és egy háttérfolyamat szinkronizálta őket.)
 * A román szöveg a program_item_translations táblában van (nincs tükör-oszlop).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // a címből EGYSZER generálódik, utána nem változik
            $table->string('title');
            $table->text('description');
            $table->string('organizer_names')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('entry_type', 16)->nullable();
            $table->text('ticket_info')->nullable();
            $table->string('schedule_note')->nullable();

            // Elsődleges kategória: ez dönti el az űrlap extra mezőit és a kategóriagazda jogát.
            // Törlése a programot NEM törli, csak árvává teszi.
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();

            // Kategóriánkénti extra űrlapmezők (pl. fellépő neve, könyvbemutató adatai).
            $table->json('extra_fields')->nullable();

            // Beküldés adatai.
            $table->string('source', 16)->default('internal');
            $table->foreignId('program_manager_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('photographer_credit')->nullable();
            $table->boolean('photo_rights_declared')->default(false);

            $table->timestamps();
        });

        // További kategóriák (csak a koordinátor állítja; az űrlap nem).
        Schema::create('category_program_item', function (Blueprint $table) {
            $table->foreignId('program_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['program_item_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_program_item');
        Schema::dropIfExists('program_items');
    }
};
