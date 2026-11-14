<?php

namespace App\Filament\Resources\Craftspeople;

use App\Enums\Gyujtemeny;
use App\Enums\NavigationGroup;
use App\Filament\Forms\MediaPicker;
use App\Filament\Resources\Craftspeople\Pages\CreateCraftsperson;
use App\Filament\Resources\Craftspeople\Pages\EditCraftsperson;
use App\Filament\Resources\Craftspeople\Pages\ListCraftspeople;
use App\Filament\Resources\Resource;
use App\Models\Craftsperson;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Kézművesek — a „Tartalom” menücsoportban (tulajdonosi döntés, 2026-11-14; ikonos csoport elemének nincs saját ikonja).
 * A Kézműves vásár lap kártyái és felugró ablaka ebből épül. A kép a médiatárban él; ami innen kerül fel, magától a
 * „Kézművesek” gyűjteménybe kerül. A bemutatkozás bekezdései üres sorral (sortöréssel) válnak el — mint 2026-ban.
 */
class CraftspersonResource extends Resource
{
    protected static ?string $model = Craftsperson::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Tartalom;

    protected static ?string $navigationLabel = 'Kézművesek';

    protected static ?string $modelLabel = 'kézműves';

    protected static ?string $pluralModelLabel = 'kézművesek';

    protected static ?string $slug = 'kezmuvesek';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('name')->label('Név')->required()->maxLength(255),
                Toggle::make('visible')->label('Látszik a Kézműves vásár lapon')->default(true)->inline(false),
                MediaPicker::make('image_media_id')->label('Kép')
                    ->relationship('image', 'id')
                    ->gyujtemeny(Gyujtemeny::Kezmuvesek)
                    ->befejezoFelirat('Beállítás a kézműves képeként')
                    ->columnSpanFull(),
            ]),
            Section::make('Magyar')->columnSpanFull()->schema([
                Textarea::make('description_hu')->label('Bemutatkozás')->autosize()
                    ->helperText('A bekezdéseket sortörés választja el. A kártyán az eleje látszik, a felugró ablakban az egész.'),
            ]),
            Section::make('Román')->columnSpanFull()->description('Kézzel fordítva. Ha üres, a román oldalon a magyar szöveg látszik.')->schema([
                Textarea::make('description_ro')->label('Bemutatkozás')->autosize(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                ImageColumn::make('image')->label('')->imageWidth(64)->imageHeight(48)
                    ->state(fn (Craftsperson $r) => $r->image ? url($r->image->getGlideUrl(['w' => 128, 'h' => 96, 'fit' => 'crop', 'fm' => 'webp'])) : null)
                    ->extraImgAttributes(['style' => 'object-fit:cover;border-radius:.375rem']),
                TextColumn::make('name')->label('Név')->searchable(),
                IconColumn::make('visible')->label('Látszik')->boolean(),
                TextColumn::make('description_ro')->label('Román')->formatStateUsing(fn (?string $state) => filled($state) ? 'van' : '—')
                    ->default('—')->visibleFrom('md'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCraftspeople::route('/'),
            'create' => CreateCraftsperson::route('/create'),
            'edit' => EditCraftsperson::route('/{record}/edit'),
        ];
    }
}
