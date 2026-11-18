<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROMPT-20: a két publikus jelentkező űrlap (Családi Olimpia, önkéntesek) táblái.
 *
 * OLIMPIA — a csapatszín ATOMI foglalása az ADATBÁZIS dolga: egyedi kulcs az (év, szín) páron. Két egyidejű
 * beküldésből a másodikat maga az adatbázis utasítja vissza, nem egy „előbb megnézem, aztán beírom” ellenőrzés
 * (az versenyezhető). Visszalépéskor a `color_key` kiürül (NULL) — az egyedi kulcs a NULL-t nem számolja, a szín
 * újra választható; a `color_name` megmarad, hogy látszódjon, mi volt.
 *
 * ÖNKÉNTES — kiskorúak adatai: a feltöltött fájlok a ZÁRT tárban (storage/app/private), itt csak az útvonaluk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olympics_teams', function (Blueprint $t) {
            $t->id(); // a jelentkezés SORRENDJE (a várólista ezt követi)
            $t->unsignedSmallInteger('year');
            $t->string('team_name', 120);
            $t->string('color_key', 40)->nullable();
            $t->string('color_name', 60);
            $t->string('color_hex', 9)->nullable();
            $t->json('members'); // [{name, age}] — 1–5 kötelező, 6–7 opcionális
            $t->string('captain_phone', 40);
            $t->string('captain_email', 190);
            $t->boolean('declaration')->default(false);
            $t->text('declaration_text'); // az ELFOGADOTT szöveg — a hozzájárulás bizonyítéka
            $t->string('status', 20)->default('applied');
            $t->boolean('status_manual')->default(false); // az adminban állították: a sorrend-javítás nem nyúl hozzá
            $t->text('coordinator_note')->nullable();
            $t->timestamps();

            $t->unique(['year', 'color_key']);
            $t->index(['year', 'status']);
        });

        Schema::create('volunteer_applications', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('year');
            $t->string('full_name', 120);
            $t->unsignedTinyInteger('age');
            $t->string('email', 190);
            $t->boolean('was_volunteer')->default(false);
            $t->text('prev_experience')->nullable();
            $t->text('motivation');
            $t->json('available_days'); // dátumok (a Beállítások önkéntes-napjaiból)
            $t->json('areas');
            $t->boolean('has_license')->default(false);
            $t->text('health_notes');
            $t->json('dietary')->nullable();
            $t->string('dietary_other', 255)->nullable();
            $t->string('emergency_name', 120);
            $t->string('emergency_phone', 40);
            // ZÁRT tár (local lemez): soha nem a nyilvános médiatár.
            $t->string('parental_consent_path')->nullable();
            $t->string('parental_consent_mime', 60)->nullable();
            $t->string('motivation_letter_path')->nullable();
            $t->string('motivation_letter_mime', 60)->nullable();
            $t->boolean('privacy_consent')->default(false);
            $t->text('privacy_text'); // az ELFOGADOTT szöveg
            $t->string('status', 20)->default('new');
            $t->text('coordinator_note')->nullable();
            $t->timestamps();

            $t->index(['year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_applications');
        Schema::dropIfExists('olympics_teams');
    }
};
