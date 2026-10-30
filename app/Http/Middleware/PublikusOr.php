<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A publikus oldal NEM az admin szemével néz (CLAUDE.md 4.): a sor-szintű szűkítés minden kérésben hat, ahol
 * az alapértelmezett őr belépett felhasználója kategóriagazda — így egy ugyanabban a böngészőben belépett
 * kategóriagazda a nyilvános programkeresőben is csak a saját kategóriáit látná. Ezért a publikus lapok a
 * 'bekuldo' őrre állnak át (mint a beküldő lapok): ott admin-felhasználó nincs, a szűkítés nem hat, és a
 * publikus lekérdezések a STÁTUSZ alapján szűrnek (csak a közzétett látszik). A Hamarosan mód a 'web' őrt
 * név szerint kérdezi, arra ez nem hat.
 */
class PublikusOr
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('bekuldo');

        return $next($request);
    }
}
