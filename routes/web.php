<?php

use App\Http\Controllers\Bekuldes\BelepesController;
use App\Http\Controllers\Bekuldes\JelszoController;
use App\Http\Controllers\Bekuldes\UrlapController;
use App\Http\Controllers\Publikus\HirekController;
use App\Http\Controllers\Publikus\KezdolapController;
use App\Http\Controllers\Publikus\OldalController;
use App\Http\Controllers\Publikus\ProgramokController;
use App\Http\Controllers\Publikus\TaxonController;
use App\Http\Middleware\BekuldoBelepve;
use App\Http\Middleware\HamarosanMod;
use App\Http\Middleware\PublikusNyelv;
use App\Http\Middleware\PublikusOr;
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
 * A kezdőlap (PROMPT-14) a gyökérben; a galéria-ablak képei külön kérésben (galeria-adat.json), csak megnyitáskor.
 */
foreach (['hu' => '', 'ro' => 'ro'] as $nyelv => $elotag) {
    Route::prefix($elotag)->name("{$nyelv}.")
        ->middleware([PublikusNyelv::class . ":{$nyelv}", PublikusOr::class, HamarosanMod::class])
        ->group(function () {
            Route::get('/', [KezdolapController::class, 'index'])->name('kezdo');
            Route::get('galeria-adat.json', [KezdolapController::class, 'galeria'])->name('galeria_adat');
            Route::view('hamarosan', 'hamarosan')->name('hamarosan');
            Route::get('hirek', [HirekController::class, 'index'])->name('hirek');
            Route::get('hirek/{slug}', [HirekController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('hir');
            // Programkereső (a szűrés a címsorban), napi nézet, „Mi van ma?”, a program lapja.
            Route::get('programok', [ProgramokController::class, 'index'])->name('programok');
            Route::get('nap/{datum}', [ProgramokController::class, 'nap'])->where('datum', '\d{4}-\d{2}-\d{2}|allando')->name('nap');
            Route::get('ma', [ProgramokController::class, 'ma'])->name('ma');
            Route::get('program/{slug}', [ProgramokController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('program');
            // Helyszínek, kategóriák, a térkép lapja (PROMPT-15).
            Route::get('helyszinek', [TaxonController::class, 'helyszinek'])->name('helyszinek');
            Route::get('helyszin/{slug}', [TaxonController::class, 'helyszin'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('helyszin');
            Route::get('kategoriak', [TaxonController::class, 'kategoriak'])->name('kategoriak');
            Route::get('kategoria/{slug}', [TaxonController::class, 'kategoria'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('kategoria');
            Route::get('terkep', [TaxonController::class, 'terkep'])->name('terkep');
            // Aloldal a slugja alapján — a foglalt első szavak (admin, beküldés, rendszer-útvonalak) kizárva.
            Route::get('{lap}', [OldalController::class, 'show'])
                ->where('lap', '(?!(?:admin|bekuldes|curator|livewire|filament|storage|up|ro|hamarosan|hirek|programok|nap|ma|program|helyszinek|helyszin|kategoriak|kategoria|terkep)$)[a-z0-9]+(?:-[a-z0-9]+)*')
                ->name('oldal');
        });
}
