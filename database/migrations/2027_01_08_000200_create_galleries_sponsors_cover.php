<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A képek gazdái: galériák (képsorral), támogatók (logóval), programok (borítóval).
 * A médiatár képe NEM törölhető, amíg valami használja (restrictOnDelete + Media::usages()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('title_hu');
            $table->string('title_ro')->nullable();
            $table->string('credit')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('legacy_id', 36)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('gallery_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained('galleries')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('curator')->restrictOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->unique(['gallery_id', 'media_id']);
        });

        // A szponzorszintek évenként mások (2026-ban 11, átnevezve a 2025-ös 9-hez képest). A 2027-es
        // kezdőlista (seed) év nélkül marad; a 2026-os szintek year=2026-tal jönnek, hogy a 2027-es
        // listát ne keverjék össze.
        Schema::table('sponsor_tiers', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable()->after('id');
            $table->string('legacy_id', 36)->nullable()->unique();
        });

        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->foreignId('sponsor_tier_id')->nullable()->constrained('sponsor_tiers')->nullOnDelete();
            $table->string('url')->nullable();
            $table->foreignId('logo_media_id')->nullable()->constrained('curator')->restrictOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('legacy_id', 36)->nullable()->unique();
            $table->timestamps();
        });

        Schema::table('program_items', function (Blueprint $table) {
            // A KÖZZÉTETT borító (a médiatárban). A beküldött eredeti a zárt tárban marad (photo_path).
            $table->foreignId('cover_media_id')->nullable()->after('photo_path')->constrained('curator')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('program_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cover_media_id');
        });
        Schema::dropIfExists('sponsors');
        Schema::table('sponsor_tiers', function (Blueprint $table) {
            $table->dropUnique(['legacy_id']);
            $table->dropColumn(['year', 'legacy_id']);
        });
        Schema::dropIfExists('gallery_media');
        Schema::dropIfExists('galleries');
    }
};
