<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Szponzorcsomag-szintek. Az induló készlet: seed/sponsor_tiers.csv (9 db, 2025-ből).
 * Maguk a szponzorok (logóval) a médiatár eldöntése után jönnek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsor_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ro')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_tiers');
    }
};
