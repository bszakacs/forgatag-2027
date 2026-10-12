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

    \App\Models\User::create(['name' => 'Admin', 'email' => $email, 'password' => $jelszo]);
    $this->info("ADMIN: {$email} letrehozva. Most torold az ADMIN_PASSWORD sort a .env-bol.");

    return 0;
})->purpose('Elso admin letrehozasa a .env-bol (shell nelkuli telepiteshez)');

// IDEIGLENES: proba-programgazda letrehozasa shell nelkul, amig nincs „Felhasznalok → Programgazdak” lap
// (utana ez a parancs, a .cpanel.yml sora es a .env sorai TORLENDOK — negy-hely szabaly).
// Ugyanaz a minta, mint a forgatag:admin: az adatot a szerveri .env adja, a jelszot a felhasznalo irja be.
// Ha a fiok mar letezik, a jelszavahoz NEM nyul, csak a kategoriait igazitja.
Artisan::command('forgatag:programgazda', function () {
    $email = mb_strtolower(trim((string) env('PROGRAMGAZDA_EMAIL')));
    $nev = (string) env('PROGRAMGAZDA_NEV', 'Proba programgazda');
    $jelszo = (string) env('PROGRAMGAZDA_JELSZO');
    $slugok = array_filter(array_map('trim', explode(',', (string) env('PROGRAMGAZDA_KATEGORIAK'))));

    if ($email === '') {
        $this->line('PROGRAMGAZDA: nincs PROGRAMGAZDA_EMAIL a .env-ben — kihagyva.');

        return 0;
    }
    if (str_contains($email, '<<') || str_contains($jelszo, '<<') || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('PROGRAMGAZDA: a PROGRAMGAZDA_EMAIL / _JELSZO kitoltetlen vagy hibas — a fiok NEM jott letre.');

        return 1;
    }

    $kategoriak = \App\Models\Category::whereIn('slug', $slugok)->pluck('id', 'slug');
    if ($hiany = array_diff($slugok, $kategoriak->keys()->all())) {
        $this->error('PROGRAMGAZDA: ismeretlen kategoria: ' . implode(', ', $hiany));

        return 1;
    }

    $m = \App\Models\ProgramManager::where('email', $email)->first();
    if (! $m) {
        if (strlen($jelszo) < 12) {
            $this->error('PROGRAMGAZDA: a PROGRAMGAZDA_JELSZO hianyzik vagy 12 karakternel rovidebb — a fiok NEM jott letre.');

            return 1;
        }
        $m = \App\Models\ProgramManager::create(['name' => $nev, 'email' => $email, 'password' => $jelszo, 'active' => true]);
        $this->info("PROGRAMGAZDA: {$email} letrehozva. Most torold a PROGRAMGAZDA_JELSZO sort a .env-bol.");
    } else {
        $this->info("PROGRAMGAZDA: {$email} mar letezik — a jelszavat nem modositottam.");
    }
    $m->categories()->sync($kategoriak->values()->all());
    $this->info('PROGRAMGAZDA: kategoriak: ' . ($slugok ? implode(', ', $slugok) : 'nincs'));

    return 0;
})->purpose('IDEIGLENES: proba-programgazda a .env-bol (amig nincs Programgazdak lap)');
