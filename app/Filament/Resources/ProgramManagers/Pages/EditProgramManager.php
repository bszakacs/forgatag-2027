<?php

namespace App\Filament\Resources\ProgramManagers\Pages;

use App\Filament\Resources\ProgramManagers\ProgramManagerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProgramManager extends EditRecord
{
    protected static string $resource = ProgramManagerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A törölt programgazda programjai megmaradnak (a beküldő mezőjük kiürül).
            DeleteAction::make()->modalDescription('A programgazda programjai megmaradnak, csak a beküldő adata ürül ki. Ha csak átmenetileg nem léphet be, inkább kapcsold ki az „Aktív” kapcsolót.'),
        ];
    }
}
