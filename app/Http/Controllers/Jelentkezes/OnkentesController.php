<?php

namespace App\Http\Controllers\Jelentkezes;

use App\Http\Controllers\Controller;
use App\Services\OnkentesJelentkezes;
use App\Support\Onkentes;
use App\Support\PublikusUrlap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

/**
 * Az önkéntes publikus jelentkező űrlapja (PROMPT-20; 2026: volunteer-api). BELÉPÉS NÉLKÜL — méz-bödön, IP-alapú gyakorisági
 * korlát, és a nyitva/zárva a VÉGPONTON is. Kiskorúak adatai: a dokumentumok a zárt tárba (OnkentesJelentkezes).
 */
class OnkentesController extends Controller
{
    public function show(): View
    {
        return view('jelentkezes.onkentes', [
            'nyitva' => Onkentes::nyitva(),
            'sz' => PublikusUrlap::szovegek('onkentes'),
        ]);
    }

    public function store(Request $request, OnkentesJelentkezes $jelentkezes): JsonResponse
    {
        if (! Onkentes::nyitva()) {
            return response()->json(['ok' => false, 'closed' => true, 'errors' => [Onkentes::zartUzenet()]], 403);
        }
        if (PublikusUrlap::mezBodon($request)) {
            return response()->json(['ok' => true]);
        }

        $fajl = fn (string $nev) => $request->file($nev) instanceof UploadedFile ? $request->file($nev) : null;
        [$kod, $valasz] = $jelentkezes->fogad(
            $request->except(['_token', PublikusUrlap::MEZ_BODON, 'parental_file', 'motivation_file']),
            ['szuloi' => $fajl('parental_file'), 'motivacios' => $fajl('motivation_file')],
        );

        return response()->json($valasz, $kod);
    }
}
