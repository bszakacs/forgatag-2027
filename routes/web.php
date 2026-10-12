<?php

use App\Http\Controllers\Bekuldes\BelepesController;
use App\Http\Controllers\Bekuldes\UrlapController;
use App\Http\Middleware\BekuldoBelepve;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * Programbeküldés (2. fázis). Külön belépés ('bekuldo' őr) — az admintól független.
 * Új beküldő aldomain később: ugyanez a csoport egy ->domain(...) megkötéssel.
 */
Route::prefix('bekuldes')->name('bekuldes.')->group(function () {
    Route::get('belepes', [BelepesController::class, 'show'])->name('belepes');
    Route::post('belepes', [BelepesController::class, 'login'])->middleware('throttle:bekuldes-belepes');
    Route::post('kilepes', [BelepesController::class, 'logout'])->name('kilepes');

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
