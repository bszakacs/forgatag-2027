<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programgazdák: a beküldő űrlap külsős felhasználói (pl. Teleki Téka). NEM admin-felhasználók —
 * a Filamentbe nem lépnek be. Az, hogy melyik kategóriába küldhetnek be, a pivot táblában van;
 * 2027-ben ezt a SZERVER is ellenőrzi (2026-ban csak a kiküldött linkeket döntötte el).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_managers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique(); // kisbetűsítve tárolva (a modell gondoskodik róla)
            $table->string('password')->nullable();
            $table->boolean('active')->default(true);
            $table->text('note')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('category_program_manager', function (Blueprint $table) {
            $table->foreignId('program_manager_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['program_manager_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_program_manager');
        Schema::dropIfExists('program_managers');
    }
};
