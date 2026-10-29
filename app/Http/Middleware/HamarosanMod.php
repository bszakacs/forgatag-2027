<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * „Hamarosan” mód (Beállítások → Publikus oldal). CSAK a publikus útvonalcsoporton fut (routes/web.php), ezért
 * az admin, a beküldő űrlap és a médiatár képei szerkezetileg kívül esnek rajta.
 * A belépett admin a valódi lapokat látja (hogy élesítés előtt dolgozhasson rajtuk), egy figyelmeztető sávval.
 */
class HamarosanMod
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Settings::get('public.coming_soon')) {
            return $next($request);
        }
        if (auth('web')->check()) {
            view()->share('hamarosanSav', true);

            return $next($request);
        }

        return response()->view('hamarosan', [], 200);
    }
}
