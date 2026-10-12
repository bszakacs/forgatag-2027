<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A beküldött program fotója (2. fázis). A fájl a NEM nyilvános tárban van
 * (storage/app/private/bekuldesek/), az oszlop az ottani relatív útvonal.
 * A médiatár eldöntéséig (nyitott kérdés) ez az egyetlen kép a programon; a jogi adatok
 * (photographer_credit, photo_rights_declared) már a program_items táblában vannak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_items', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('photo_rights_declared');
        });
    }

    public function down(): void
    {
        Schema::table('program_items', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
