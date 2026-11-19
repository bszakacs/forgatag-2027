<?php

namespace App\Http\Controllers\Publikus;

use App\Http\Controllers\Controller;
use App\Support\Kedvencek;
use App\Support\Naptar;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * A „Kedvenc programjaim” lap adatai (PROMPT-21). Maga a lap (`/kedvencek`) rendszerlap-sablon (oldal/kedvencek), és
 * SEMMILYEN látogatói adatot nem tartalmaz — így gyorsítótárazható. A böngésző a saját listájával kéri le:
 * · /kedvencek/kartyak?p=1,2,3   → a lista HTML-je (napok → a programkereső kártyái, átfedés-jelzéssel) és az összegző sor;
 * · /kedvencek/naptar.ics?p=…    → az egész lista egy naptárfájlban.
 * A válasz csak a kért program-azonosítóktól függ (személyes adat nincs benne).
 */
class KedvencekController extends Controller
{
    public function kartyak(Request $request): Response
    {
        return response()->view('kedvencek._lista', ['napok' => Kedvencek::napok($this->ids($request))]);
    }

    public function naptar(Request $request): Response
    {
        $sorok = Kedvencek::napok($this->ids($request))->flatten(1);

        return Naptar::valasz(Naptar::ics($sorok), 'forgatag-kedvencek.ics');
    }

    /** @return list<int> */
    private function ids(Request $request): array
    {
        return Kedvencek::tisztit(explode(',', (string) $request->query('p', '')));
    }
}
