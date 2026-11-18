<?php

namespace App\Http\Controllers\Jelentkezes;

use App\Http\Controllers\Controller;
use App\Services\OlimpiaJelentkezes;
use App\Support\Olimpia;
use App\Support\PublikusUrlap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A Családi Olimpia publikus jelentkező űrlapja (PROMPT-20; 2026: olimpia.forgatag.ro, olympics-api). BELÉPÉS NÉLKÜL — ezért:
 * méz-bödön, IP-alapú gyakorisági korlát (`throttle:jelentkezes`), és a nyitva/zárva kapcsoló a VÉGPONTON is (403), nem csak
 * az űrlap tűnik el (mentett lapról vagy robottól különben továbbra is jönne jelentkezés).
 */
class OlimpiaController extends Controller
{
    public function show(): View
    {
        return view('jelentkezes.olimpia', [
            'nyitva' => Olimpia::nyitva(),
            'allapot' => Olimpia::nyitva() ? Olimpia::allapot() : null,
            'sz' => PublikusUrlap::szovegek('olimpia'),
        ]);
    }

    public function store(Request $request, OlimpiaJelentkezes $jelentkezes): JsonResponse
    {
        // ZÁRT KAPU a szerveren is — elsőként (2026-ban is).
        if (! Olimpia::nyitva()) {
            return response()->json(['ok' => false, 'closed' => true, 'errors' => [Olimpia::zartUzenet()]], 403);
        }
        // Méz-bödön: a rejtett mezőt csak robot tölti ki → látszólagos siker, de NINCS mentés, nincs levél.
        if (PublikusUrlap::mezBodon($request)) {
            return response()->json(OlimpiaJelentkezes::sikerValasz(false));
        }

        [$kod, $valasz] = $jelentkezes->fogad($request->except(['_token', PublikusUrlap::MEZ_BODON]));

        return response()->json($valasz, $kod);
    }
}
