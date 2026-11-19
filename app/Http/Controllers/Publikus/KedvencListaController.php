<?php

namespace App\Http\Controllers\Publikus;

use App\Http\Controllers\Controller;
use App\Support\Kedvencek;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A KEDVENCLISTA végpontja (`/kedvencek-api/lista`, PROMPT-21) — a 2026-os favorites-api helyén, e-mail-cím nélkül.
 * A kulcs (a megosztható link része) azonosít; aki ismeri, látja és szerkeszti a listát.
 * · POST   /kedvencek-api/lista          {ids}   → új lista, a válasz a kulcs és a lista
 * · GET    /kedvencek-api/lista/{kulcs}          → a lista
 * · POST   /kedvencek-api/lista/{kulcs}  {ops}   → műveletek ([[id, true|false], …]) sorban, a válasz a friss lista
 * · DELETE /kedvencek-api/lista/{kulcs}          → a lista törlése
 * Nem létező kulcs: 404 (a kliens ebből tudja, hogy a listát máshol törölték). A hibaszöveg a kliensé (nyelvenként); itt kód.
 * Mindig `no-store`: a válasz egy látogató listája, gyorsítótárba nem kerülhet.
 */
class KedvencListaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $lista = Kedvencek::letrehoz((array) $request->json('ids', []));

        return $this->valasz(['ok' => true, 'kulcs' => $lista->token, 'ids' => $lista->program_ids], 201);
    }

    public function show(string $kulcs): JsonResponse
    {
        $lista = Kedvencek::keres($kulcs);

        return $lista
            ? $this->valasz(['ok' => true, 'ids' => Kedvencek::tisztit($lista->program_ids)])
            : $this->valasz(['ok' => false, 'hiba' => 'nincs'], 404);
    }

    public function update(Request $request, string $kulcs): JsonResponse
    {
        $ids = Kedvencek::alkalmaz($kulcs, (array) $request->json('ops', []));

        return $ids !== null
            ? $this->valasz(['ok' => true, 'ids' => $ids])
            : $this->valasz(['ok' => false, 'hiba' => 'nincs'], 404);
    }

    public function destroy(string $kulcs): JsonResponse
    {
        Kedvencek::keres($kulcs)?->delete();

        // Nem létező listára is „rendben”: a cél (nincs lista) teljesült.
        return $this->valasz(['ok' => true]);
    }

    private function valasz(array $adat, int $kod = 200): JsonResponse
    {
        return response()->json($adat, $kod)->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
    }
}
