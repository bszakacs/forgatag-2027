<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A programgazdák elfelejtett-jelszó tokenjei — KÜLÖN az admin-felhasználókétól (password_reset_tokens),
 * mert a két jelszótár szándékosan elválik (CLAUDE.md 2. fázis, 2.7): ugyanaz az e-mail-cím
 * a két helyen nem írhatja felül egymás tokenjét.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_manager_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_manager_password_reset_tokens');
    }
};
