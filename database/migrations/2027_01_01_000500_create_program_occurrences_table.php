<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Egy program időpontjai — egy program több napon / több időponttal futhat (kiállítások!).
 * A `date` a FESZTIVÁLNAP: a hajnali (dayCutHour előtti) befejezés még ehhez a naphoz tartozik,
 * ezért az end_time lehet KISEBB a start_time-nál (éjfélen átnyúló program).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_item_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['program_item_id', 'date', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_occurrences');
    }
};
