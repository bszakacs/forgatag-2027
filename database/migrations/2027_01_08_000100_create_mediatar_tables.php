<?php

use App\Enums\Gyujtemeny;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A médiatár (CLAUDE.md 2/d): a Curator saját táblája (`curator`, a csomag mezőivel) + a MI mezőink
 * (jogtulajdonos, licenc, fotós-kredit, eredet) + a gyűjtemények (szűrő-„mappák”, több-több kapcsolat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curator', function (Blueprint $table) {
            $table->id();
            // --- a Curator mezői (vendor/awcodes/filament-curator/stubs/migration.stub) ---
            $table->string('disk');
            $table->string('directory')->nullable();
            $table->string('visibility')->default('public');
            $table->string('name');
            $table->string('path')->index();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->string('type');
            $table->string('ext');
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('caption')->nullable();
            $table->text('pretty_name')->nullable();
            $table->text('exif')->nullable();
            $table->longText('curations')->nullable();
            // --- a mi mezőink ---
            $table->string('rights_holder')->nullable();
            // Adminban feltöltve: saját forrás az alapérték, ne álljon az útba. Beküldöttnél kötelező (Media).
            $table->string('license')->default('sajat');
            $table->string('photographer_credit')->nullable();
            $table->string('origin')->default('admin');
            // A 2026-os Directus-fájl azonosítója — a behozatal ezzel ismétlés-biztos, és a későbbi
            // tartalmi körök (hírek, csapat...) ezen keresztül kötik be a saját képüket.
            $table->string('legacy_id', 36)->nullable()->unique();
            // Eredet-jelölés: honnan jött, mihez tartozott (pl. „2026-os program: …”).
            $table->string('legacy_note')->nullable();
            $table->timestamps();
        });

        Schema::create('media_collections', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            // A rendszer-gyűjteményekre a kód hivatkozik (App\Enums\Gyujtemeny) — nem törölhetők, nem nevezhetők át.
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('media_collection_media', function (Blueprint $table) {
            $table->foreignId('media_collection_id')->constrained('media_collections')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('curator')->cascadeOnDelete();
            $table->primary(['media_collection_id', 'media_id']);
        });

        $most = now();
        DB::table('media_collections')->insert(array_map(fn (Gyujtemeny $g) => [
            'slug' => $g->value, 'name' => $g->label(), 'is_system' => true, 'sort' => $g->sort(),
            'created_at' => $most, 'updated_at' => $most,
        ], Gyujtemeny::cases()));
    }

    public function down(): void
    {
        Schema::dropIfExists('media_collection_media');
        Schema::dropIfExists('media_collections');
        Schema::dropIfExists('curator');
    }
};
