<?php

namespace App\Policies;

/**
 * Kedvenclisták: MINDEN TILTVA az adminban (az alap Policy). Felülete nincs; a látogató a kulcsával, a publikus
 * végponton (App\Http\Controllers\Publikus\KedvencListaController) kezeli a saját listáját.
 */
class FavoriteListPolicy extends Policy {}
