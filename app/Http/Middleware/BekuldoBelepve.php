<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A beküldő lapok kapuja: csak belépett, AKTÍV programgazda jut tovább (a 'bekuldo' őrrel —
 * az admin-belépés itt nem számít). A közben kikapcsolt fiók azonnal kilép.
 */
class BekuldoBelepve
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('bekuldo');
        $beku = $guard->user();

        if ($beku && ! $beku->active) {
            $guard->logout();
            $beku = null;
        }

        if (! $beku) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'errors' => ['Belépés szükséges.']], 401)
                : redirect()->guest(route('bekuldes.belepes'));
        }

        // A kérés további részében a programgazda a „felhasználó” — egy ugyanabban a böngészőben belépett
        // admin-fiók sor-szintű szűkítése (RowAccess) itt nem hathat a programgazda saját programjaira.
        Auth::shouldUse('bekuldo');

        return $next($request);
    }
}
