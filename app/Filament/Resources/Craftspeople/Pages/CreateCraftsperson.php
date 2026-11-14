<?php

namespace App\Filament\Resources\Craftspeople\Pages;

use App\Filament\Resources\Craftspeople\CraftspersonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCraftsperson extends CreateRecord
{
    protected static string $resource = CraftspersonResource::class;
}
