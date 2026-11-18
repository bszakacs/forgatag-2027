<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TITKOS BEÁLLÍTÁSOK (API-kulcs, később reCAPTCHA, SMTP-jelszó) — App\Support\Titkok.
 * Az érték a Laravel beépített titkosításával (APP_KEY) tárolva: az adatbázisban (és a MENTÉSBEN) csak titkosítva áll.
 * ⚠ Ha az APP_KEY változik, a tárolt titkok nem fejthetők vissza — ezt a felület kiírja, és újra meg kell adni őket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secret_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();   // App\Support\Titkok::KULCSOK
            $table->text('value');                  // titkosítva (Crypt)
            $table->string('updated_by')->nullable(); // ki cserélte (név) — az érték soha nem kerül naplóba
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secret_settings');
    }
};
