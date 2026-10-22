<?php

namespace App\Filament\Resources\ProgramManagers;

use App\Enums\NavigationGroup;
use App\Filament\Resources\ProgramManagers\Pages\CreateProgramManager;
use App\Filament\Resources\ProgramManagers\Pages\EditProgramManager;
use App\Filament\Resources\ProgramManagers\Pages\ListProgramManagers;
use App\Filament\Resources\Resource;
use App\Http\Controllers\Bekuldes\JelszoController;
use App\Models\ProgramManager;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Programgazdák: a beküldő űrlap külsős felhasználói (pl. Teleki Téka). NEM admin-felhasználók —
 * a Filamentbe nem lépnek be (külön őr, külön jelszótár).
 * Új fiók → üdvözlő levél a belépési adatokkal; üresen hagyott jelszónál a rendszer generál egyet.
 */
class ProgramManagerResource extends Resource
{
    protected static ?string $model = ProgramManager::class;

    /**
     * Rokon dolgok menücsoportba kerülnek (CLAUDE.md 2/b): Felhasználók → Admin-felhasználók, Programgazdák.
     * Saját menüikon NINCS: a csoport ikonja képviseli (App\Enums\NavigationGroup — Filament-szabály).
     */
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Felhasznalok;

    protected static ?string $navigationLabel = 'Programgazdák';

    protected static ?string $modelLabel = 'programgazda';

    protected static ?string $pluralModelLabel = 'programgazdák';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('Név')->required()->maxLength(255)
                    ->helperText('Személy vagy szervezet, pl. „Teleki Téka”.'),
                TextInput::make('email')->label('E-mail (belépési azonosító)')->email()->required()->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'Ezzel a címmel már van programgazda.']),
                TextInput::make('password')->label('Jelszó')->password()->revealable()
                    ->minLength(JelszoController::MIN_HOSSZ)
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'create'
                        ? 'Üresen hagyva a rendszer generál egyet. A jelszó az üdvözlő levélben megy ki a programgazdának.'
                        : 'Csak ha meg akarod változtatni (levél nem megy róla). Üresen: marad a régi. A programgazda maga is kérhet újat az „Elfelejtett jelszó” linkkel.'),
                Toggle::make('active')->label('Aktív (beléphet)')->default(true)->inline(false),
                Select::make('categories')->label('Kategóriák, ahová beküldhet')
                    ->relationship('categories', 'name_hu')
                    ->multiple()->preload()->searchable()->columnSpanFull()
                    ->helperText('Csak ezekbe küldhet be — a szerver is ellenőrzi.'),
                Textarea::make('note')->label('Megjegyzés')->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Név')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable()->copyable(),
                TextColumn::make('categories.name_hu')->label('Kategóriák')->badge()->wrap(),
                TextColumn::make('program_items_count')->label('Programok')->counts('programItems')->sortable(),
                IconColumn::make('active')->label('Aktív')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('active')->label('Aktív'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProgramManagers::route('/'),
            'create' => CreateProgramManager::route('/create'),
            'edit' => EditProgramManager::route('/{record}/edit'),
        ];
    }
}
