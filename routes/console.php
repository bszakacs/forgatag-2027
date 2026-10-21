<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Az elso admin letrehozasa shell nelkul: a .cpanel.yml futtatja minden telepiteskor.
// Az adatot a szerveri .env ADMIN_EMAIL / ADMIN_PASSWORD sora adja (a jelszot a felhasznalo irja be).
// Ha a fiok mar letezik, NEM nyul hozza (a jelszot sem irja felul). Siker utan az ADMIN_PASSWORD
// sor torlendo a .env-bol.
Artisan::command('forgatag:admin', function () {
    $email = env('ADMIN_EMAIL');
    $jelszo = env('ADMIN_PASSWORD');

    if (blank($email)) {
        $this->warn('ADMIN: nincs ADMIN_EMAIL a .env-ben — admin-letrehozas kihagyva.');

        return 0;
    }

    // Kitoltetlen minta (.env.szerverre.txt <<...>> helye) soha ne legyen e-mail vagy jelszo.
    if (str_contains($email, '<<') || str_contains((string) $jelszo, '<<') || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('ADMIN: az ADMIN_EMAIL / ADMIN_PASSWORD kitoltetlen vagy hibas — a fiok NEM jott letre.');

        return 1;
    }

    if (\App\Models\User::where('email', $email)->exists()) {
        $this->info("ADMIN: {$email} mar letezik — nem modositottam.");

        return 0;
    }

    if (blank($jelszo) || strlen($jelszo) < 12) {
        $this->error('ADMIN: az ADMIN_PASSWORD hianyzik vagy 12 karakternel rovidebb — a fiok NEM jott letre.');

        return 1;
    }

    \App\Models\User::create(['name' => 'Admin', 'email' => $email, 'password' => $jelszo, 'role' => \App\Enums\UserRole::SuperAdmin]);
    $this->info("ADMIN: {$email} letrehozva. Most torold az ADMIN_PASSWORD sort a .env-bol.");

    return 0;
})->purpose('Elso admin letrehozasa a .env-bol (shell nelkuli telepiteshez)');
