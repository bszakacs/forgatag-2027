<?php

namespace App\Http\Controllers\Publikus;

use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use App\Models\Page;
use App\Support\LapSzovegek;
use App\Support\Nyelv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hírek: lista lapozással + egy hír lapja. A lapszám a címsorban (`?oldal=2`): megosztható, könyvjelzőzhető,
 * a kereső is megnyitja. A lapozó htmx-szel csak a listát cseréli (hx-boost + hx-select a sablonban) — a szerver
 * mindig a TELJES lapot adja, így JavaScript nélkül és a böngésző „vissza” gombjával is ugyanaz jön.
 */
class HirekController extends Controller
{
    /** Egy lapon ennyi hír (2026-ban is 12: 3 oszlop × 4 sor). */
    public const LAPMERET = 12;

    public function index(Request $request): View|RedirectResponse
    {
        $alap = Nyelv::ut('/hirek');
        $kert = $request->query('oldal');
        $oldal = ctype_digit((string) $kert) ? (int) $kert : 1;
        // Egy lapnak EGY címe legyen: ?oldal=1, ?oldal=0, ?oldal=abc → a sima /hirek (a kereső ne lássa duplán).
        if ($kert !== null && ($oldal < 2 || (string) $oldal !== $kert)) {
            return redirect($oldal >= 2 ? "{$alap}?oldal={$oldal}" : $alap, 301);
        }

        $hirek = NewsItem::query()->publikus()->with('cover')
            ->orderByDesc('published_on')->orderByDesc('id')
            ->paginate(self::LAPMERET, ['*'], 'oldal', $oldal);
        abort_if($oldal > max(1, $hirek->lastPage()), 404);

        $lap = Page::query()->where('slug', 'hirek')->firstOrFail();

        return view('hirek.index', [
            'lap' => $lap,
            'sz' => new LapSzovegek($lap, Nyelv::aktualis()),
            'hirek' => $hirek,
            'lapUrl' => fn (int $n) => $n <= 1 ? $alap : "{$alap}?oldal={$n}",
        ]);
    }

    public function show(string $slug): View
    {
        $hir = NewsItem::query()->publikus()->with('cover')->where('slug', $slug)->firstOrFail();

        return view('hirek.show', ['hir' => $hir]);
    }
}
