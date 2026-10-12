<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A programok fordításai — VALÓDI idegen kulccsal (nem polimorf tábla, lásd CLAUDE.md 5.).
 * Programonként, nyelvenként és mezőnként EGY sor (egyedi kulcs).
 *
 * Státusz (app/Enums/TranslationStatus): machine_translated → reviewed → approved, illetve outdated,
 * ha a magyar forrás megváltozott (a source_text_hash eltér). A gépi futás a reviewed / approved /
 * outdated sort SOHA nem írja felül. Megjelenítés: approved > reviewed > machine_translated;
 * outdated esetén a magyar szöveg látszik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_item_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5)->default('ro');
            $table->string('field', 32); // title | description | ticket_info
            $table->text('translated_text')->nullable();
            $table->char('source_text_hash', 64)->nullable(); // a magyar forrás sha256-ja
            $table->string('status', 32)->default('machine_translated')->index();
            $table->text('check_note')->nullable();
            $table->timestamps();

            $table->unique(['program_item_id', 'locale', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_item_translations');
    }
};
