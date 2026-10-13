<?php

namespace App\Filament\Resources\ProgramManagers\Pages;

use App\Filament\Resources\ProgramManagers\ProgramManagerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProgramManagers extends ListRecords
{
    protected static string $resource = ProgramManagerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
