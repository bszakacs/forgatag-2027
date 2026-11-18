<?php

namespace App\Filament\Resources\Onkentesek\Pages;

use App\Filament\Resources\Onkentesek\VolunteerApplicationResource;
use App\Support\Onkentes;
use Filament\Resources\Pages\ListRecords;

class ListVolunteerApplications extends ListRecords
{
    protected static string $resource = VolunteerApplicationResource::class;

    public function getSubheading(): ?string
    {
        return 'Személyes adat, kiskorúaké is — csak a szuperadmin és az önkéntes-koordinátor látja. A jelentkezés '
            . (Onkentes::nyitva() ? 'NYITVA' : 'zárva') . ' (Beállítások → Önkéntesek).';
    }
}
