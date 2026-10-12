<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programkategóriák. Az induló készlet: seed/categories.csv (24 db).
 * Védett kategória nincs (2026-os tulajdonosi döntés): szabadon szerkeszthető és törölhető.
 * A román név KÉZZEL fordított (a gépi fordítás csak a beküldött programokra fut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_hu');
            $table->string('name_ro')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
