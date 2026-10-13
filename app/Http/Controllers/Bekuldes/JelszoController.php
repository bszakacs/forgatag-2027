<?php

namespace App\Http\Controllers\Bekuldes;

use App\Http\Controllers\Controller;
use App\Models\ProgramManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Elfelejtett jelszó a programgazdáknak (évente egyszer lépnek be — enélkül minden elfelejtett
 * jelszó kézi munka a koordinátornak). Külön jelszókezelő ('program_managers'), külön token-tábla.
 *
 * A válasz MINDIG ugyanaz, akár létezik a cím, akár nem — nem derül ki, ki van a nyilvántartásban.
 * Kikapcsolt fiók nem kap linket.
 */
class JelszoController extends Controller
{
    public const MIN_HOSSZ = 10;

    public const KULDVE = 'Ha ez a cím szerepel a nyilvántartásban, perceken belül küldünk egy levelet a jelszó beállításához. Nézd meg a levélszemét mappát is.';

    public function keres(): View
    {
        return view('bekuldes.jelszo-keres');
    }

    public function kuld(Request $request): RedirectResponse
    {
        $email = mb_strtolower(trim((string) $request->input('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('bekuldes.jelszo')->withInput()->withErrors(['email' => 'Adj meg egy érvényes e-mail-címet.']);
        }

        // A státuszt szándékosan nem mutatjuk (nincs ilyen cím / túl gyakori kérés / elküldve — mind ugyanaz).
        Password::broker('program_managers')->sendResetLink(['email' => $email, 'active' => true]);

        return redirect()->route('bekuldes.jelszo')->with('siker', self::KULDVE);
    }

    public function uj(Request $request, string $token): View
    {
        return view('bekuldes.jelszo-uj', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function ment(Request $request): RedirectResponse
    {
        $jelszo = (string) $request->input('password');
        if (mb_strlen($jelszo) < self::MIN_HOSSZ) {
            return back()->withErrors(['password' => 'A jelszó legalább ' . self::MIN_HOSSZ . ' karakter legyen.']);
        }
        if ($jelszo !== (string) $request->input('password_confirmation')) {
            return back()->withErrors(['password' => 'A két jelszó nem egyezik.']);
        }

        $allapot = Password::broker('program_managers')->reset(
            [
                'email' => mb_strtolower(trim((string) $request->input('email'))),
                'token' => (string) $request->input('token'),
                'password' => $jelszo,
                'active' => true,
            ],
            function (ProgramManager $beku, string $jelszo) {
                $beku->forceFill(['password' => $jelszo])->save();
            },
        );

        if ($allapot !== Password::PASSWORD_RESET) {
            return back()->withErrors(['password' => 'A link lejárt vagy már felhasználták. Kérj újat az „Elfelejtett jelszó” linkkel.']);
        }

        return redirect()->route('bekuldes.belepes')->with('siker', 'Az új jelszavad beállítva — most már beléphetsz vele.');
    }
}
