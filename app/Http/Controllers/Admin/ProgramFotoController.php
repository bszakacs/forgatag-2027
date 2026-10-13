<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProgramItem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A beküldött programfotó az adminnak. A fájl a NEM nyilvános tárban van (storage/app/private);
 * ez az útvonal a Filament panel belépett útvonalai között van (AdminPanelProvider::authenticatedRoutes),
 * tehát csak belépett, a panelhez jogosult admin-felhasználónak szolgál ki.
 *
 * A programot a modellen át keressük: amikor a kategóriagazda sor-szintű szűkítése (globális scope)
 * megérkezik, más kategória fotójára itt is 404 jön — külön kód nélkül.
 */
class ProgramFotoController extends Controller
{
    public function __invoke(int $program): StreamedResponse
    {
        $p = ProgramItem::findOrFail($program);
        abort_unless($p->hasPhotoFile(), 404);

        return Storage::disk('local')->response($p->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
