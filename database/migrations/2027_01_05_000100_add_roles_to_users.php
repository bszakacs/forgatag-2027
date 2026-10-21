<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Szerepkörök (app/Enums/UserRole) + a kategóriagazdák kategóriái.
 *
 * Az ÚJ fiók alapértéke a legszűkebb: csak olvasó. A MEGLÉVŐ fiókok szuperadminok lesznek — eddig
 * minden admin-fiók teljes jogú volt, és nem zárhatjuk ki a tulajdonost a saját rendszeréből.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('read_only')->after('email');
        });
        DB::table('users')->update(['role' => 'superadmin']);

        Schema::create('category_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
