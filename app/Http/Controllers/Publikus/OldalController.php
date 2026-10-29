<?php

namespace App\Http\Controllers\Publikus;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\LapSablon;
use App\Support\LapSzovegek;
use App\Support\Nyelv;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\View\View;

/**
 * Aloldalak (adminban: Tartalom → Aloldalak). Csak közzétett lap látszik.
 * · Saját lap: a közös sablon (oldal/show) — cím, bevezető, nyitókép, blokkok.
 * · Rendszerlap: a SAJÁT sablonja (oldal/<slug>), ha már megépült; amíg nincs, 404 (nem egy félkész lap).
 */
class OldalController extends Controller
{
    public function show(string $lap): View
    {
        $oldal = Page::query()->where('slug', $lap)->where('status', ContentStatus::Published)
            ->with(['hero', 'blocks.media'])->firstOrFail();

        $sablon = "oldal.{$oldal->slug}";
        if (! ViewFactory::exists($sablon)) {
            abort_if($oldal->is_system, 404);
            $sablon = 'oldal.show';
        }

        return view($sablon, [
            'lap' => $oldal,
            'sz' => LapSablon::van($oldal->slug) ? new LapSzovegek($oldal, Nyelv::aktualis()) : null,
        ]);
    }
}
