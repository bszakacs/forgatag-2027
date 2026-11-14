<?php

namespace App\Filament\Resources\Craftspeople\Pages;

use App\Filament\Resources\Craftspeople\CraftspersonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCraftspeople extends ListRecords
{
    protected static string $resource = CraftspersonResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
