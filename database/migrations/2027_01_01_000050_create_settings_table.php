<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A fesztivál MINDEN szervezői beállítása (CLAUDE.md 2/b) — az admin „Beállítások” lapja szerkeszti.
 * Blokkonként EGY sor (key = a blokk neve: festival, submission, notifications…), a mezők JSON-ban.
 * Új funkció = új blokk = új sor; nem kell hozzá séma-módosítás.
 *
 * Itt törlődik a rövid életű festival_days tábla is (a napokat mostantól a rendszer SZÁMOLJA a
 * beállított dátumokból — nincs napok-lista).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('festival_days');

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
