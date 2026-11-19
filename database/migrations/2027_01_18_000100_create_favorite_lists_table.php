<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * KEDVENCLISTÁK (PROMPT-21). Belépés nincs, személyes adat nincs: egy lista = egy véletlen kulcs (a megosztható link
 * része) és a program-azonosítók. A 2026-os favorites-api e-mail-címhez kötötte — 2027-ben tulajdonosi döntés szerint
 * semmilyen személyes adatot nem kérünk; a link az egyetlen út vissza a listához.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorite_lists', function (Blueprint $table) {
            $table->id();
            $table->char('token', 32)->unique();
            $table->json('program_ids');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_lists');
    }
};
