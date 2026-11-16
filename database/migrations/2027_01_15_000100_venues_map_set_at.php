<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A térkép-szerkesztő (PROMPT-18) nyoma a helyszínen: MIKOR állította be valaki kézzel a térkép-adatát.
 * Miért kell: a telepítés része a 2026-os térkép-adat betöltése (forgatag:helyszin-terkep-import), ami csak az
 * „érintetlen” helyszínt tölti. A szerkesztőben LEVETT helyszín (zóna none, nincs pozíció) addig érintetlennek látszott
 * volna, és a következő telepítés visszatette volna a 2026-os helyére. Ha ez a mező ki van töltve, a behozatal nem nyúl hozzá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->timestamp('map_set_at')->nullable()->after('map_bearing');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn('map_set_at');
        });
    }
};
