<?php

namespace App\Http\Controllers\Bekuldes;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * A programgazdák belépése — KÜLÖN az admintól ('bekuldo' őr, program_managers tábla).
 * Ismételt hibás próbálkozásnál zárolás (IP + e-mail): 5 rossz próba → 15 perc (mint 2026-ban).
 * Ismeretlen e-mail és rossz jelszó UGYANAZT az üzenetet adja (nem derül ki, létezik-e a fiók).
 */
class BelepesController extends Controller
{
    private const MAX_PROBA = 5;

    private const ZAR_MP = 15 * 60;

    private const HIBAS = 'Hibás e-mail vagy jelszó.';

    public function show(): View|RedirectResponse
    {
        if (Auth::guard('bekuldo')->check()) {
            return redirect()->route('bekuldes.kezdo');
        }

        return view('bekuldes.belepes');
    }

    public function login(Request $request): RedirectResponse
    {
        $email = mb_strtolower(trim((string) $request->input('email')));
        $jelszo = (string) $request->input('password');
        $kulcs = 'bekuldes-belepes:' . $request->ip() . '|' . $email;

        if (RateLimiter::tooManyAttempts($kulcs, self::MAX_PROBA)) {
            return back()->withInput(['email' => $email])
                ->withErrors(['email' => 'Túl sok sikertelen próbálkozás. Próbáld újra pár perc múlva.']);
        }
        if ($email === '' || $jelszo === '') {
            RateLimiter::hit($kulcs, self::ZAR_MP);

            return back()->withInput(['email' => $email])->withErrors(['email' => 'Add meg az e-mailt és a jelszót.']);
        }

        if (! Auth::guard('bekuldo')->attempt(['email' => $email, 'password' => $jelszo, 'active' => true])) {
            RateLimiter::hit($kulcs, self::ZAR_MP);

            return back()->withInput(['email' => $email])->withErrors(['email' => self::HIBAS]);
        }

        RateLimiter::clear($kulcs);
        $request->session()->regenerate();

        return redirect()->intended(route('bekuldes.kezdo'));
    }

    public function logout(Request $request): RedirectResponse
    {
        // Csak a beküldő őrből lép ki (a munkamenet többi részéhez nem nyúl).
        Auth::guard('bekuldo')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('bekuldes.belepes');
    }
}
