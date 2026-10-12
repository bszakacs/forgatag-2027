<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programnapok (fesztiválnapok + előkészítő napok). EZ AZ EGYETLEN FORRÁSUK — az adminban
 * („Fesztiválnapok”) szerkeszthető, bővíthető. A seed/festival-days.json csak az első feltöltéshez kell.
 * A napnevek és feliratok a dátumból számolódnak (nincs tárolt felirat, ami elcsúszhatna).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('festival_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('kind', 16)->default('festival'); // festival | prep (app/Enums/FestivalDayKind)
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('festival_days');
    }
};
