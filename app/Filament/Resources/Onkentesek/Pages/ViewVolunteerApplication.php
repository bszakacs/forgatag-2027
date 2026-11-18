<?php

namespace App\Filament\Resources\Onkentesek\Pages;

use App\Filament\Resources\Onkentesek\VolunteerApplicationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVolunteerApplication extends ViewRecord
{
    protected static string $resource = VolunteerApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Állapot, jegyzet'),
            DeleteAction::make()->modalDescription('A jelentkezés minden adata és a feltöltött dokumentumai véglegesen törlődnek. Nem vonható vissza.'),
        ];
    }
}
