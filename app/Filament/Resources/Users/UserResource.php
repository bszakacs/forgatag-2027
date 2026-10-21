<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Resource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Az admin (Filament) felhasználói, szerepkörrel (App\Enums\UserRole — ott a jogosultsági mátrix).
 * A kategóriagazda a hozzárendelt kategóriáit kapja (sor-szintű jog: App\Models\Scopes\RowAccess).
 * A programgazdák NEM itt vannak (külön jelszótár, külön lap ugyanebben a menücsoportban).
 */
class UserResource extends Resource
{
    public const MIN_JELSZO = 12;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Felhasználók';

    protected static ?string $navigationLabel = 'Admin-felhasználók';

    protected static ?string $modelLabel = 'admin-felhasználó';

    protected static ?string $pluralModelLabel = 'admin-felhasználók';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('Név')->required()->maxLength(255),
                TextInput::make('email')->label('E-mail (belépési azonosító)')->email()->required()->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'Ezzel a címmel már van admin-felhasználó.']),
                Select::make('role')->label('Szerepkör')->required()
                    ->options(UserRole::options())->default(UserRole::ReadOnly->value)->native(false)->live()
                    // Saját szerepkörét senki nem változtathatja (különben kizárhatja magát, akár az utolsó szuperadmint is).
                    ->disabled(fn (?User $record) => $record?->is(auth('web')->user()) ?? false)
                    ->helperText(fn (Get $get) => self::szerep($get('role'))?->description()),
                Select::make('categories')->label('Kategóriái (csak ezek programjait látja)')
                    ->relationship('categories', 'name_hu', fn (Builder $query) => $query->orderBy('sort'))
                    ->multiple()->preload()->searchable()->columnSpanFull()
                    ->visible(fn (Get $get) => self::szerep($get('role')) === UserRole::CategoryOwner)
                    ->required(fn (Get $get) => self::szerep($get('role')) === UserRole::CategoryOwner)
                    ->validationMessages(['required' => 'Kategória nélkül a kategóriagazda semmit nem lát — jelölj be legalább egyet.']),
                TextInput::make('password')->label('Jelszó')->password()->revealable()
                    ->minLength(self::MIN_JELSZO)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'create'
                        ? 'Legalább ' . self::MIN_JELSZO . ' karakter. Levél nem megy róla — add át személyesen.'
                        : 'Csak ha meg akarod változtatni. Üresen: marad a régi.'),
            ]),
        ]);
    }

    /** Az űrlap állapotában a szerepkör hol szöveg, hol enum — egy helyen alakítjuk. */
    private static function szerep(mixed $state): ?UserRole
    {
        return $state instanceof UserRole ? $state : UserRole::tryFrom((string) $state);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Név')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable()->copyable(),
                TextColumn::make('role')->label('Szerepkör')->badge()->formatStateUsing(fn (UserRole $state) => $state->label()),
                TextColumn::make('categories.name_hu')->label('Kategóriái')->badge()->wrap()->visibleFrom('md'),
                TextColumn::make('created_at')->label('Létrehozva')->date('Y. m. d.')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
