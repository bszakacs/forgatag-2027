<?php

namespace App\Filament\Resources\Aloldalak\Pages;

use App\Filament\Resources\Aloldalak\PageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        // Rendszerlapnál a gomb szürke, a súgója mondja meg, miért (PagePolicy). A képek a médiatárban maradnak.
        return [DeleteAction::make()->authorizationTooltip()];
    }
}
