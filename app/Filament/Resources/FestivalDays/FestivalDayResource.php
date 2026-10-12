<?php

namespace App\Filament\Resources\FestivalDays;

use App\Enums\FestivalDayKind;
use App\Filament\Resources\FestivalDays\Pages\ManageFestivalDays;
use App\Models\FestivalDay;
use App\Models\ProgramOccurrence;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Programnapok (fesztivál- és előkészítő napok) — az egyetlen forrásuk ez a lista.
 * Olyan nap, amelyre már van programidőpont, nem törölhető és a dátuma nem írható át,
 * különben a programok némán „nap nélkül” maradnának.
 */
class FestivalDayResource extends Resource
{
    protected static ?string $model = FestivalDay::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'programnap';

    protected static ?string $pluralModelLabel = 'Fesztiválnapok';

    protected static ?string $navigationLabel = 'Fesztiválnapok';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->label('Dátum')
                    ->required()
                    ->displayFormat('Y. m. d.')
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'Ez a nap már szerepel a listában.'])
                    ->disabled(fn (?FestivalDay $record) => $record !== null && self::vanProgramja($record))
                    ->helperText(fn (?FestivalDay $record) => $record !== null && self::vanProgramja($record)
                        ? 'Erre a napra már van program, ezért a dátum nem módosítható.'
                        : null),
                Select::make('kind')
                    ->label('Típus')
                    ->options(FestivalDayKind::class)
                    ->default(FestivalDayKind::Festival)
                    ->required()
                    ->helperText('Az előkészítő napokra is lehet programot beküldeni. A fesztivál kezdete és vége a „Fesztiválnap” típusú napokból adódik.'),
                TextInput::make('note')
                    ->label('Megjegyzés (belső)')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        // Tömeges törlés szándékosan nincs: azzal a lenti védelem megkerülhető lenne.
        return $table
            ->defaultSort('date')
            ->paginated(false)
            ->columns([
                TextColumn::make('date')->label('Dátum')->date('Y. m. d.')->sortable(),
                TextColumn::make('label_hu')->label('Felirat (HU)')->state(fn (FestivalDay $r) => $r->label('hu')),
                TextColumn::make('label_ro')->label('Felirat (RO)')->state(fn (FestivalDay $r) => $r->label('ro'))->visibleFrom('md'),
                TextColumn::make('kind')->label('Típus')->badge()
                    ->color(fn (FestivalDayKind $state) => $state === FestivalDayKind::Prep ? 'gray' : 'warning'),
                TextColumn::make('programok')->label('Időpontok')
                    ->state(fn (FestivalDay $r) => ProgramOccurrence::whereDate('date', $r->date)->count()),
                TextColumn::make('note')->label('Megjegyzés')->placeholder('—')->visibleFrom('md'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (FestivalDay $record) => self::vanProgramja($record))
                    ->tooltip(fn (FestivalDay $record) => self::vanProgramja($record)
                        ? 'Erre a napra már van program — előbb a programokat kell áttenni.'
                        : null),
            ]);
    }

    public static function vanProgramja(FestivalDay $nap): bool
    {
        return ProgramOccurrence::whereDate('date', $nap->getOriginal('date'))->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFestivalDays::route('/'),
        ];
    }
}
