<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProgramItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * A beküldött programfotó az adminnak. A fájl a NEM nyilvános tárban van (storage/app/private);
 * ez az útvonal a Filament panel belépett útvonalai között van (AdminPanelProvider::authenticatedRoutes),
 * tehát csak belépett, a panelhez jogosult admin-felhasználónak szolgál ki.
 *
 * A programot a modellen át keressük, tehát a kategóriagazda sor-szintű szűkítése (ProgramScope) itt is
 * hat: más kategória fotójára 404 jön. A policy-kérdés mélységi védelem (és kizárja pl. az arculat-szerkesztőt).
 *
 * ?kis=1 → kis előnézet a programlistához (webp), a zárt tárban gyorsítótárazva — a lista ne töltse le az akár
 * 10 MB-os eredetiket. Ugyanaz a jog- és szűkítés-ellenőrzés.
 */
class ProgramFotoController extends Controller
{
    public const KIS_MERET = 96;

    public function __invoke(Request $request, int $program): Response
    {
        $p = ProgramItem::findOrFail($program);
        abort_unless(Gate::allows('view', $p) && $p->hasPhotoFile(), 404);

        $zart = Storage::disk('local');
        $fejlec = ['Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff'];

        if (! $request->boolean('kis')) {
            return $zart->response($p->photo_path, null, $fejlec);
        }

        $kis = '.elonezet/' . $p->id . '-' . substr(md5((string) $p->photo_path), 0, 12) . '.webp';
        if (! $zart->exists($kis)) {
            try {
                @ini_set('memory_limit', '512M'); // nagy (akár 24 MP-es) beküldött fotóhoz
                $kep = ImageManager::gd()->read($zart->path($p->photo_path))
                    ->scaleDown(self::KIS_MERET, self::KIS_MERET)->toWebp(80);
                $zart->put($kis, (string) $kep);
            } catch (Throwable $e) {
                report($e);

                return $zart->response($p->photo_path, null, $fejlec); // inkább a nagy, mint semmi
            }
        }

        return $zart->response($kis, null, ['Content-Type' => 'image/webp', ...$fejlec]);
    }
}
