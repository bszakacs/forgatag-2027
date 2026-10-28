<?php

namespace App\Filament\Resources\Galleries;

use App\Enums\Gyujtemeny;
use App\Enums\NavigationGroup;
use App\Filament\Forms\MediaPicker;
use App\Filament\Resources\Galleries\Pages\CreateGallery;
use App\Filament\Resources\Galleries\Pages\EditGallery;
use App\Filament\Resources\Galleries\Pages\ListGalleries;
use App\Filament\Resources\Resource;
use App\Models\Gallery;
use Awcodes\Curator\Components\Tables\CuratorColumn;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Galériák (évenként egy): a képek a médiatárban élnek, a galéria a sorrendjüket tartja.
 * Ami a galéria lapjáról kerül fel, magától a „Galériák” gyűjteménybe kerül (MediaPicker).
 * A „Tartalom” menücsoportban (ikonos csoport elemének nincs saját ikonja).
 */
class GalleryResource extends Resource
{
    protected static ?string $model = Gallery::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Tartalom;

    protected static ?string $navigationLabel = 'Galériák';

    protected static ?string $modelLabel = 'galéria';

    protected static ?string $pluralModelLabel = 'galériák';

    protected static ?string $slug = 'galeriak';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'title_hu';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('title_hu')->label('Cím (magyar)')->required()->maxLength(255),
                TextInput::make('title_ro')->label('Cím (román)')->maxLength(255),
                TextInput::make('year')->label('Év')->numeric()->required()->minValue(2000)->maxValue(2100),
                TextInput::make('credit')->label('Fotósok (kredit)')->maxLength(255),
            ]),
            Section::make('Képek')->columnSpanFull()->schema([
                MediaPicker::make('media')->hiddenLabel()
                    ->relationship('media', 'id')
                    ->orderColumn('sort')
                    ->multiple()
                    ->listDisplay(false)
                    ->gyujtemeny(Gyujtemeny::Galeriak),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('year', 'desc')
            ->columns([
                CuratorColumn::make('media')->label('')->imageSize(48)->limit(1)->ring(0),
                TextColumn::make('title_hu')->label('Cím')->searchable(),
                TextColumn::make('year')->label('Év')->sortable(),
                TextColumn::make('media_count')->label('Képek')->counts('media')->sortable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGalleries::route('/'),
            'create' => CreateGallery::route('/create'),
            'edit' => EditGallery::route('/{record}/edit'),
        ];
    }
}
