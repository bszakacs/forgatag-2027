<?php

namespace App\Http\Controllers\Publikus;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Kezdolap;
use App\Support\LapSzovegek;
use App\Support\Nyelv;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * A kezdőlap (PROMPT-14) és a galéria-ablak adata. Az adat-összeszedés szabályai: App\Support\Kezdolap.
 */
class KezdolapController extends Controller
{
    public function index(): View
    {
        $lap = Page::query()->where('slug', 'home')->firstOrFail();
        $sz = new LapSzovegek($lap, Nyelv::aktualis());

        return view('kezdolap', [
            'lap' => $lap,
            'sz' => $sz,
            'fesztival' => Kezdolap::fesztivalAllapot(),
            'hirek' => Kezdolap::rejtett('news') ? collect() : Kezdolap::hirek(),
            'videok' => Kezdolap::videok(),
            'galeriak' => Kezdolap::galeriak(),
            'partnerek' => Kezdolap::partnerek([$sz->szoveg('home_org_name_1'), $sz->szoveg('home_org_name_2')]),
        ]);
    }

    /** A galéria-ablak képei évenként — csak akkor kéri le a lap, amikor a látogató megnyit egy évet. */
    public function galeria(): JsonResponse
    {
        return response()->json(Kezdolap::galeriaAdat(Nyelv::aktualis()), 200, ['Cache-Control' => 'public, max-age=600'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
