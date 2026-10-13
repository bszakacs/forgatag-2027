<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A programok jóváhagyási naplója — APPEND-ONLY (sort csak hozzáadunk, nem módosítunk, nem törlünk).
 * Ki, mikor, mit tett: beküldés, javítás, jóváhagyásra küldés, jóváhagyás, visszaküldés (indokkal).
 * A visszaküldés indokának EGYETLEN helye ez a tábla — a beküldő is innen látja.
 * Az esemény-fajták: app/Enums/ReviewAction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_review_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_item_id')->constrained()->cascadeOnDelete();
            $table->string('action', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            // Aki tette: admin-felhasználó VAGY programgazda (a törölt fiók neve a naplóban megmarad).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_manager_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['program_item_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_review_events');
    }
};
