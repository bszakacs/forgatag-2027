<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VolunteerApplication;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Az önkéntes-jelentkezés dokumentumai (szülői beleegyezés, motivációs levél) az adminnak — KISKORÚAK adatai.
 * A fájl a ZÁRT tárban van (storage/app/private/onkentesek/); webről közvetlenül nem érhető el. Ez az útvonal a Filament panel
 * belépett útvonalai között van (AdminPanelProvider::authenticatedRoutes), és a policy-t is megkérdezi: csak a szuperadmin és az
 * önkéntes-koordinátor kapja meg — mindenki másnak 404 (a létezését sem áruljuk el).
 */
class OnkentesFajlController extends Controller
{
    public function __invoke(int $jelentkezes, string $tipus): Response
    {
        $a = VolunteerApplication::find($jelentkezes);
        abort_unless($a !== null && isset(VolunteerApplication::FAJLOK[$tipus]) && Gate::allows('view', $a) && $a->vanFajl($tipus), 404);

        [$ut, $mime] = VolunteerApplication::FAJLOK[$tipus];

        return Storage::disk('local')->response($a->{$ut}, null, [
            'Content-Type' => (string) $a->{$mime},
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
