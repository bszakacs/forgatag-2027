<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tartalmi gyűjtemények (PROMPT-08): hírek, menü (fa), aloldalak (szerkeszthető szövegekkel és blokkokkal).
 *
 * Kétnyelvű: a saját tartalmat KÉZZEL fordítjuk (CLAUDE.md 5.), ezért itt sima _hu/_ro mezők vannak — a
 * gépi fordítás fordítás-táblái csak a beküldött programokra kellenek.
 * Kép: mindig a médiatárra mutat (restrictOnDelete — használt kép nem törölhető, lásd Media::usages()).
 *
 * TESZTADAT (tulajdonosi szabály, 2026-10-28): a teszteléshez behozott hír és program `is_test` jelölést kap —
 * felismerhető (TESZT jelvény) és EGY lépésben törölhető (App\Support\Tesztadat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title_hu');
            $table->string('title_ro')->nullable();
            $table->text('excerpt_hu')->nullable();
            $table->text('excerpt_ro')->nullable();
            $table->longText('body_hu')->nullable();
            $table->longText('body_ro')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('curator')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->date('published_on')->nullable();
            $table->boolean('is_test')->default(false)->index();
            $table->string('legacy_id', 36)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title_hu');
            $table->string('title_ro')->nullable();
            $table->text('lead_hu')->nullable();
            $table->text('lead_ro')->nullable();
            $table->string('seo_description_hu', 300)->nullable();
            $table->string('seo_description_ro', 300)->nullable();
            $table->foreignId('hero_media_id')->nullable()->constrained('curator')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            // Rendszerlap: a publikus oldal kódja a slugja alapján rajzolja (programkereső, galéria...). A slug
            // nem írható át, és a lap nem törölhető — különben a hozzá tartozó oldal némán eltűnne.
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('legacy_id', 36)->nullable()->unique();
            $table->timestamps();
        });

        // A lap SZERKESZTHETŐ SZÖVEGEI: a sablon kulcsai (pl. about_story_p1), nyelvenként; képes kulcsnál kép.
        // A 2026-os `page.home_sections` JSON megfelelője — itt soronként, hogy a kép valódi idegen kulcs legyen.
        Schema::create('page_texts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('key', 100);
            $table->text('value_hu')->nullable();
            $table->text('value_ro')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('curator')->restrictOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->unique(['page_id', 'key']);
        });

        Schema::create('page_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('type', 20);
            $table->string('title_hu')->nullable();
            $table->string('title_ro')->nullable();
            $table->longText('body_hu')->nullable();
            $table->longText('body_ro')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('curator')->restrictOnDelete();
            $table->string('cta_label_hu')->nullable();
            $table->string('cta_label_ro')->nullable();
            $table->string('cta_url')->nullable();
            // Videós blokk: [{title_hu, title_ro, youtube_id}] — sorrend a listáé.
            $table->json('videos')->nullable();
            $table->boolean('show_hu')->default(true);
            $table->boolean('show_ro')->default(true);
            $table->string('legacy_id', 36)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('title_hu');
            $table->string('title_ro')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('legacy_id', 36)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('label_hu');
            $table->string('label_ro')->nullable();
            // page = egy aloldalra mutat (a slug változása nem töri el) · link = cím · group = csak almenü-fejléc
            $table->string('type', 10)->default('link');
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('url')->nullable();
            $table->boolean('new_tab')->default(false);
            // Nyelvenkénti elrejtés (2026: locale_visibility). Mindkettő hamis = rejtett menüpont.
            $table->boolean('show_hu')->default(true);
            $table->boolean('show_ro')->default(true);
            $table->string('legacy_id', 36)->nullable()->unique();
            $table->timestamps();
        });

        Schema::table('program_items', function (Blueprint $table) {
            $table->boolean('is_test')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('program_items', function (Blueprint $table) {
            $table->dropIndex(['is_test']);
            $table->dropColumn('is_test');
        });
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('page_blocks');
        Schema::dropIfExists('page_texts');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('news_items');
    }
};
