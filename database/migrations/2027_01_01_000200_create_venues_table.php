<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Helyszínek, szülő–gyerek hierarchiával (pl. „Kultúrpalota, nagyterem” → „Kultúrpalota”).
 * Az induló készlet: seed/venues.csv (61 db; a 2026-os 59-hez pótolva a két hiányzó szülő: Bolyai téri Unitárius templom, Marosvásárhelyi Nemzeti Színház).
 * A térkép-mezőket (map_*) a térkép-szerkesztő tölti (5. fázis); a koordináta a térkép-SVG-hez
 * viszonyított százalék (0–100).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('name_ro')->nullable();
            $table->string('short_name')->nullable();
            $table->string('address')->nullable();
            $table->string('address_ro')->nullable();
            $table->decimal('gps_lat', 9, 6)->nullable();
            $table->decimal('gps_lng', 9, 6)->nullable();
            $table->boolean('gps_approximate')->default(false);
            $table->json('aliases')->nullable();
            // A szülő törlése a gyereket nem törli, csak önállóvá teszi.
            $table->foreignId('parent_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->string('map_zone', 16)->default('none'); // none | castle | city
            $table->decimal('map_x', 6, 3)->nullable();
            $table->decimal('map_y', 6, 3)->nullable();
            $table->unsignedSmallInteger('map_no')->nullable();
            $table->boolean('map_edge')->default(false);
            $table->unsignedSmallInteger('map_bearing')->nullable(); // 0, 45, … 315
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
