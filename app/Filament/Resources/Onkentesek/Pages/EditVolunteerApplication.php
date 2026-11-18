<?php

namespace App\Filament\Resources\Onkentesek\Pages;

use App\Filament\Resources\Onkentesek\VolunteerApplicationResource;
use Filament\Resources\Pages\EditRecord;

class EditVolunteerApplication extends EditRecord
{
    protected static string $resource = VolunteerApplicationResource::class;

    protected function getRedirectUrl(): string
    {
        return VolunteerApplicationResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
