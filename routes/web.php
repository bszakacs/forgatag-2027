<?php

use App\Http\Controllers\Bekuldes\BelepesController;
use App\Http\Controllers\Bekuldes\JelszoController;
use App\Http\Controllers\Bekuldes\UrlapController;
use App\Http\Controllers\Publikus\HirekController;
use App\Http\Controllers\Publikus\OldalController;
use App\Http\Middleware\BekuldoBelepve;
use App\Http\Middleware\HamarosanMod;
use App\Http\Middleware\PublikusNyelv;
use Illuminate\Support\Facades\Route;

/*
 * Programbeküldés (2. fázis). Külön belépés ('bekuldo' őr) — az admintól független.
 * Új beküldő aldomain később: ugyanez a csoport egy ->domain(...) megkötéssel.
 */
Route::prefix('bekuldes')->name('bekuldes.')->group(function () {
    Route::get('belepes', [BelepesController::class, 'show'])->name('belepes');
    Route::post('belepes', [BelepesController::class, 'login'])->middleware('throttle:bekuldes-belepes');
    Route::post('kilepes', [BelepesController::class, 'logout'])->name('kilepes');

    // Elfelejtett jelszó (belépés nélkül). A kérések IP-nként korlátozva, mint a belépés.
    Route::get('jelszo', [JelszoController::class, 'keres'])->name('jelszo');
    Route::post('jelszo', [JelszoController::class, 'kuld'])->middleware('throttle:bekuldes-belepes')->name('jelszo.kuld');
    Route::get('jelszo/uj/{token}', [JelszoController::class, 'uj'])->name('jelszo.uj');
    Route::post('jelszo/uj', [JelszoController::class, 'ment'])->middleware('throttle:bekuldes-belepes')->name('jelszo.ment');

    Route::middleware(BekuldoBelepve::class)->group(function () {
        Route::get('/', [UrlapController::class, 'kezdo'])->name('kezdo');
        Route::get('sajat', [UrlapController::class, 'sajat'])->name('sajat');
        Route::get('program/{program}/javitas', [UrlapController::class, 'szerkesztes'])->whereNumber('program')->name('szerkesztes');
        Route::get('program/{program}/foto', [UrlapController::class, 'foto'])->whereNumber('program')->name('foto');
        Route::post('program/{program}', [UrlapController::class, 'update'])->whereNumber('program')
            ->middleware('throttle:bekuldes')->name('modositas');
        Route::get('{category:slug}', [UrlapController::class, 'urlap'])->name('urlap');
        Route::post('{category:slug}', [UrlapController::class, 'store'])->middleware('throttle:bekuldes')->name('bekuldes');
    });
});

/*
 * PUBLIKUS OLDAL — magyar a gyökérben, román a /ro/ előtag alatt (mint 2026-ban). Mindkét nyelv UGYANAZOKAT az
 * útvonalakat kapja (nevek: hu.* és ro.*). A „Hamarosan” mód CSAK ezen a csoporton fut: az admin, a beküldő
 * űrlap és a médiatár képei ezen kívül vannak, tehát a kapcsoló nem zárhatja ki őket.
 * A kezdőlap még NINCS megépítve (PROMPT-09: szándékosan) — addig a gyökér a Hamarosan lapot mutatja.
 */
foreach (['hu' => '', 'ro' => 'ro'] as $nyelv => $elotag) {
    Route::prefix($elotag)->name("{$nyelv}.")
        ->middleware([PublikusNyelv::class . ":{$nyelv}", HamarosanMod::class])
        ->group(function () {
            Route::view('/', 'hamarosan')->name('kezdo');
            Route::view('hamarosan', 'hamarosan')->name('hamarosan');
            Route::get('hirek', [HirekController::class, 'index'])->name('hirek');
            Route::get('hirek/{slug}', [HirekController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('hir');
            // Aloldal a slugja alapján — a foglalt első szavak (admin, beküldés, rendszer-útvonalak) kizárva.
            Route::get('{lap}', [OldalController::class, 'show'])
                ->where('lap', '(?!(?:admin|bekuldes|curator|livewire|filament|storage|up|ro|hamarosan|hirek)$)[a-z0-9]+(?:-[a-z0-9]+)*')
                ->name('oldal');
        });
}
