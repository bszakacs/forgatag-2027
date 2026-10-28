<?php

namespace App\Filament\Resources\Sponsors;

use App\Enums\Gyujtemeny;
use App\Filament\Forms\MediaPicker;
use App\Filament\Resources\Resource;
use App\Filament\Resources\Sponsors\Pages\CreateSponsor;
use App\Filament\Resources\Sponsors\Pages\EditSponsor;
use App\Filament\Resources\Sponsors\Pages\ListSponsors;
use App\Models\Sponsor;
use App\Models\SponsorTier;
use App\Support\Festival;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Támogatók (CLAUDE.md 2/b: saját menüpontot kaphat — rendszeresen szerkesztik). Évhez kötve: a 2026-os
 * támogatók nem 2027-esek. A logó a médiatárban él; ami innen kerül fel, magától a „Támogatók”
 * gyűjteménybe kerül. A szint az adott év szintlistájából választható (a 2026-os szintek másképp hívtak).
 */
class SponsorResource extends Resource
{
    protected static ?string $model = Sponsor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?string $navigationLabel = 'Támogatók';

    protected static ?string $modelLabel = 'támogató';

    protected static ?string $pluralModelLabel = 'támogatók';

    protected static ?string $slug = 'tamogatok';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('name')->label('Név')->required()->maxLength(255),
                TextInput::make('year')->label('Év')->numeric()->required()->minValue(2000)->maxValue(2100)
                    ->default(fn () => Festival::year())->live(),
                Select::make('sponsor_tier_id')->label('Szint')
                    ->options(fn (Get $get) => self::szintek((int) $get('year')))
                    ->helperText('Az adott év szintlistájából.'),
                TextInput::make('url')->label('Honlap')->url()->maxLength(255),
                MediaPicker::make('logo_media_id')->label('Logó')
                    ->relationship('logo', 'id')
                    ->gyujtemeny(Gyujtemeny::Tamogatok)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    /** @return array<int, string> */
    private static function szintek(int $ev): array
    {
        $q = SponsorTier::query()->orderBy('sort');
        $ev2026 = SponsorTier::where('year', $ev)->exists();

        return ($ev2026 ? $q->where('year', $ev) : $q->whereNull('year'))->pluck('name', 'id')->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                // Minden logó EGYFORMA négyzetes dobozban, best-fit (a teljes logó látszik, nincs kivágás) — mint a
                // médiatár listanézete (tulajdonosi kérés, 2026-10-28).
                ImageColumn::make('logo')->label('')->imageWidth(48)->imageHeight(48)
                    ->state(fn (Sponsor $r) => $r->logo ? url($r->logo->getGlideUrl(['w' => 96, 'h' => 96, 'fit' => 'contain', 'fm' => 'webp'])) : null)
                    ->extraImgAttributes(['style' => 'object-fit:contain;padding:3px;border-radius:.375rem;background:#fff;box-shadow:inset 0 0 0 1px rgb(0 0 0 / .1)']),
                TextColumn::make('name')->label('Név')->searchable()->sortable(),
                TextColumn::make('tier.name')->label('Szint')->sortable(),
                TextColumn::make('year')->label('Év')->sortable(),
            ])
            ->filters([
                SelectFilter::make('year')->label('Év')
                    ->options(fn () => Sponsor::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->all()),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSponsors::route('/'),
            'create' => CreateSponsor::route('/create'),
            'edit' => EditSponsor::route('/{record}/edit'),
        ];
    }
}
