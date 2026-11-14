<?php

namespace App\Filament\Resources\Craftspeople\Pages;

use App\Filament\Resources\Craftspeople\CraftspersonResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCraftsperson extends EditRecord
{
    protected static string $resource = CraftspersonResource::class;

    protected function getHeaderActions(): array
    {
        // A törlés a képeket NEM törli — azok a médiatárban maradnak.
        return [DeleteAction::make()];
    }
}
