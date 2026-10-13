<?php

namespace App\Filament\Resources\ProgramItems\Pages;

use App\Enums\ProgramStatus;
use App\Filament\Resources\ProgramItems\ProgramItemResource;
use App\Models\ProgramItem;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProgramItems extends ListRecords
{
    protected static string $resource = ProgramItemResource::class;

    /** A fülek a jóváhagyási sor nézetei; a státuszlisták egy forrásból (ProgramStatus). */
    public function getTabs(): array
    {
        return [
            'teendo' => Tab::make('Teendő')
                ->badge(fn () => ProgramItem::whereIn('status', ProgramStatus::queueValues())->count() ?: null)
                ->badgeColor(ProgramStatus::PendingReview->color())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ProgramStatus::queueValues())),
            'kint' => Tab::make('Közzétéve')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ProgramStatus::publicValues())),
            'mind' => Tab::make('Mind'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'teendo';
    }
}
