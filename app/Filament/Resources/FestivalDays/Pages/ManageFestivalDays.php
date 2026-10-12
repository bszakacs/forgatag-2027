<?php

namespace App\Filament\Resources\FestivalDays\Pages;

use App\Filament\Resources\FestivalDays\FestivalDayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFestivalDays extends ManageRecords
{
    protected static string $resource = FestivalDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
