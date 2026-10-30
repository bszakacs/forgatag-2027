<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * NAPI KIEMELÉS (2026: a `program_highlight` gyűjtemény — day, program, sort). A koordinátor naponként kiemel
 * programokat; a programkereső a kiválasztott nap kiemeltjeit a találatok fölötti sávban mutatja, ebben a sorrendben.
 * A program törlésével a kiemelése is megy (cascade).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_item_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['program_item_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_highlights');
    }
};
