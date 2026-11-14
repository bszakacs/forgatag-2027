<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kézművesek (PROMPT-17) — a 2026-os `craftsperson` gyűjtemény mezőivel: név, bemutatkozás HU/RO, kép, sorrend, látható.
 * Saját tartalom → kézzel fordított, sima _hu/_ro mezők (CLAUDE.md 5.). A kép a médiatárban él (restrict: használt kép
 * nem törölhető — Media::usages()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('craftspeople', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description_hu')->nullable();
            $table->text('description_ro')->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('curator')->restrictOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('visible')->default(true);
            $table->string('legacy_id', 36)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('craftspeople');
    }
};
