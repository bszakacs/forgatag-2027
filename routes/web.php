<?php

use App\Http\Controllers\Bekuldes\BelepesController;
use App\Http\Controllers\Bekuldes\JelszoController;
use App\Http\Controllers\Bekuldes\UrlapController;
use App\Http\Controllers\Jelentkezes\OlimpiaController;
use App\Http\Controllers\Jelentkezes\OnkentesController;
use App\Http\Controllers\Publikus\HirekController;
use App\Http\Controllers\Publikus\KedvencekController;
use App\Http\Controllers\Publikus\KedvencListaController;
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
 * A két publikus jelentkező űrlap (PROMPT-20) — BELÉPÉS NÉLKÜL, csak magyarul (mint 2026-ban, akkor saját aldomainen:
 * olimpia.forgatag.ro, onkentes.forgatag.ro). Védelem: méz-bödön (a vezérlőben), IP-alapú gyakorisági korlát (itt), és a
 * nyitva/zárva a VÉGPONTON is (a vezérlőben). A „Hamarosan” mód nem takarja el őket (ahogy a beküldő űrlapot sem).
 */
Route::get('olimpia', [OlimpiaController::class, 'show'])->name('olimpia');
Route::post('olimpia', [OlimpiaController::class, 'store'])->middleware('throttle:jelentkezes')->name('olimpia.bekuld');
Route::get('onkentes', [OnkentesController::class, 'show'])->name('onkentes');
Route::post('onkentes', [OnkentesController::class, 'store'])->middleware('throttle:jelentkezes')->name('onkentes.bekuld');

/*
 * KEDVENCLISTA (PROMPT-21) — belépés nélkül, a lista kulcsával (a megosztható link része). Nyelvtől független JSON-végpont.
 * CSRF nélkül (bootstrap/app.php): a publikus lapok gyorsítótárazhatók, látogatónkénti tokent nem hordozhatnak; a listát a
 * 32 karakteres véletlen kulcs védi, nem a munkamenet. Korlát: IP-nként új lista 30 / 10 perc (egy fesztivál-wifi mögött sok
 * látogató ül egy IP-n, ezért tág), minden más 300 / perc.
 */
Route::prefix('kedvencek-api/lista')->name('kedvencek_api.')->middleware('throttle:kedvencek')->group(function () {
    Route::post('/', [KedvencListaController::class, 'store'])->middleware('throttle:kedvencek-uj')->name('uj');
    Route::get('{kulcs}', [KedvencListaController::class, 'show'])->name('lista');
    Route::post('{kulcs}', [KedvencListaController::class, 'update'])->name('valt');
    Route::delete('{kulcs}', [KedvencListaController::class, 'destroy'])->name('torles');
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
            // Naptárba (PROMPT-21): a program időpontjai egy .ics fájlban (?idopont= — csak az az egy).
            Route::get('program/{slug}.ics', [ProgramokController::class, 'naptar'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('program_naptar');
            Route::get('program/{slug}', [ProgramokController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('program');
            // Helyszínek, kategóriák, a térkép lapja (PROMPT-15).
            Route::get('helyszinek', [TaxonController::class, 'helyszinek'])->name('helyszinek');
            Route::get('helyszin/{slug}', [TaxonController::class, 'helyszin'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('helyszin');
            Route::get('kategoriak', [TaxonController::class, 'kategoriak'])->name('kategoriak');
            Route::get('kategoria/{slug}', [TaxonController::class, 'kategoria'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('kategoria');
            Route::get('terkep', [TaxonController::class, 'terkep'])->name('terkep');
            // Kedvenc programjaim (PROMPT-21): a lap maga rendszerlap-sablon (oldal/kedvencek); a látogató listájának kártyái és
            // naptárfájlja a böngésző kérésére (a lap forrásában nincs látogatói adat).
            Route::get('kedvencek/kartyak', [KedvencekController::class, 'kartyak'])->name('kedvencek_kartyak');
            Route::get('kedvencek/naptar.ics', [KedvencekController::class, 'naptar'])->name('kedvencek_naptar');
            // Aloldal a slugja alapján — a foglalt első szavak (admin, beküldés, rendszer-útvonalak) kizárva.
            Route::get('{lap}', [OldalController::class, 'show'])
                ->where('lap', '(?!(?:admin|bekuldes|olimpia|onkentes|curator|livewire|filament|storage|up|ro|hamarosan|hirek|programok|nap|ma|program|helyszinek|helyszin|kategoriak|kategoria|terkep|kedvencek-api)$)[a-z0-9]+(?:-[a-z0-9]+)*')
                ->name('oldal');
        });
}
