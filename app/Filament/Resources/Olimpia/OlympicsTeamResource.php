<?php

namespace App\Filament\Resources\Olimpia;

use App\Enums\NavigationGroup;
use App\Enums\OlympicsStatus;
use App\Filament\Resources\Olimpia\Pages\EditOlympicsTeam;
use App\Filament\Resources\Olimpia\Pages\ListOlympicsTeams;
use App\Filament\Resources\Olimpia\Pages\ViewOlympicsTeam;
use App\Filament\Resources\Resource;
use App\Models\OlympicsTeam;
use App\Support\Festival;
use App\Support\Olimpia;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Családi Olimpia — jelentkezések (PROMPT-20; 2026: olympics_team). A „Jelentkezések” menücsoportban.
 * Jog: UserRole::canManageOlympics (szuperadmin, programkoordinátor, olimpia-koordinátor) — OlympicsTeamPolicy.
 *
 * Új csapat CSAK az űrlapról jön (a nyilatkozatot a csapat fogadja el). A státusz NEM mező: „Visszalépett” (a szín felszabadul) és
 * „Előléptetés a várólistáról” (csak ha van szabad hely) gombbal változik — a sorrend-javítás (OlimpiaJelentkezes::helyreTesz)
 * a kézzel állítotthoz nem nyúl.
 */
class OlympicsTeamResource extends Resource
{
    protected static ?string $model = OlympicsTeam::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Jelentkezesek;

    protected static ?string $navigationLabel = 'Családi Olimpia';

    protected static ?string $modelLabel = 'csapat';

    protected static ?string $pluralModelLabel = 'Családi Olimpia — csapatok';

    protected static ?string $slug = 'olimpia';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'team_name';

    public static function getNavigationBadge(): ?string
    {
        if (! \App\Support\Settings::has('festival')) {
            return null; // fesztivál-adat nélkül nincs „idei” év — a menü ettől még működjön
        }

        $n = OlympicsTeam::query()->where('year', Festival::year())->where('status', '!=', OlympicsStatus::Withdrawn->value)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('A csapat')->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('team_name')->label('Csapatnév')->required()->maxLength(120),
                Select::make('color_key')->label('Csapatszín')->required()->native(false)
                    ->options(fn (?OlympicsTeam $record) => self::szinOpciok($record))
                    ->helperText('Csak a még szabad színek választhatók (egy színt egy csapat vihet el).')
                    // Mélységi védelem: az adatbázis egyedi kulcsa a végső döntő, de itt emberi üzenetet adunk.
                    ->unique(table: 'olympics_teams', column: 'color_key', ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, ?OlympicsTeam $record) => $rule->where('year', $record?->year ?? Festival::year()))
                    ->validationMessages(['unique' => 'Ezt a színt közben elvitte egy másik csapat — válassz másikat.'])
                    ->visible(fn (?OlympicsTeam $record) => $record?->status !== OlympicsStatus::Withdrawn),
                Repeater::make('members')->label('Csapattagok (1–5 kötelező, 6–7 opcionális)')->columnSpanFull()
                    ->schema([
                        TextInput::make('name')->label('Név')->required()->minLength(2)->maxLength(120),
                        TextInput::make('age')->label('Életkor')->required()->integer()->minValue(1)->maxValue(120),
                    ])
                    ->columns(2)->minItems(Olimpia::KOTELEZO_TAGOK)->maxItems(Olimpia::TAGOK)->reorderable(false)
                    ->addActionLabel('Új csapattag')
                    // A tárolt listában az üres (6–7.) helyek is ott vannak — a szerkesztőben csak a kitöltöttek.
                    ->formatStateUsing(fn ($state) => array_values(array_filter((array) $state, fn ($m) => filled($m['name'] ?? null))))
                    ->dehydrateStateUsing(fn ($state) => array_values(array_map(fn ($m) => ['name' => trim((string) $m['name']), 'age' => (int) $m['age']], (array) $state))),
            ]),
            Section::make('Csapatkapitány')->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('captain_phone')->label('Telefonszám')->required()->maxLength(40),
                TextInput::make('captain_email')->label('E-mail')->required()->email()->maxLength(190),
            ]),
            Section::make('Belső jegyzet')->columnSpanFull()->schema([
                Textarea::make('coordinator_note')->label('Jegyzet (csak a koordinátorok látják)')->rows(3),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                TextEntry::make('team_name')->label('Csapatnév')->weight('bold'),
                TextEntry::make('status')->label('Állapot')->badge()
                    ->formatStateUsing(fn (OlympicsStatus $state) => $state->label())->color(fn (OlympicsStatus $state) => $state->color()),
                TextEntry::make('color_name')->label('Csapatszín')->html()
                    ->formatStateUsing(fn (OlympicsTeam $record) => self::szinJelzes($record)),
                TextEntry::make('sorszam')->label('Jelentkezési sorrend')
                    ->state(fn (OlympicsTeam $record) => self::sorszam($record) . '. jelentkező (' . $record->created_at?->timezone(config('app.timezone'))->format('Y. m. d. H:i') . ')'),
                TextEntry::make('tagok')->label('Csapattagok')->columnSpanFull()->listWithLineBreaks()->bulleted()
                    ->state(fn (OlympicsTeam $record) => array_map(fn ($m) => $m['name'] . (filled($m['age'] ?? null) ? " ({$m['age']} éves)" : ''), $record->tagok())),
                TextEntry::make('captain_phone')->label('Kapitány telefonszáma')->url(fn (OlympicsTeam $record) => 'tel:' . preg_replace('/[^\d+]/', '', $record->captain_phone)),
                TextEntry::make('captain_email')->label('Kapitány e-mail-címe')->url(fn (OlympicsTeam $record) => 'mailto:' . $record->captain_email),
                TextEntry::make('coordinator_note')->label('Belső jegyzet')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Elfogadott nyilatkozat (a jelentkezéskor, szó szerint)')->columnSpanFull()->collapsed()->schema([
                TextEntry::make('declaration_text')->hiddenLabel(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('id')->label('#')->state(fn (OlympicsTeam $record) => self::sorszam($record))->width('3rem'),
                TextColumn::make('team_name')->label('Csapat')->searchable()->weight('bold')
                    ->description(fn (OlympicsTeam $record) => count($record->tagok()) . ' fő'),
                TextColumn::make('color_name')->label('Szín')->html()->formatStateUsing(fn (OlympicsTeam $record) => self::szinJelzes($record)),
                TextColumn::make('status')->label('Állapot')->badge()
                    ->formatStateUsing(fn (OlympicsStatus $state) => $state->label())->color(fn (OlympicsStatus $state) => $state->color()),
                TextColumn::make('captain_phone')->label('Kapitány')->description(fn (OlympicsTeam $record) => $record->captain_email)->visibleFrom('md'),
                TextColumn::make('created_at')->label('Jelentkezett')->dateTime('Y. m. d. H:i')->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('year')->label('Év')
                    ->options(fn () => OlympicsTeam::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->all() + [Festival::year() => Festival::year()])
                    ->default(Festival::year()),
                SelectFilter::make('status')->label('Állapot')->options(OlympicsStatus::options()),
            ])
            ->recordUrl(fn (OlympicsTeam $record) => self::getUrl('view', ['record' => $record]));
    }

    /** A jelentkezési sorrend az évben (a visszalépettekkel együtt — az eredeti sorrend). */
    public static function sorszam(OlympicsTeam $t): int
    {
        return OlympicsTeam::query()->where('year', $t->year)->where('id', '<=', $t->id)->count();
    }

    public static function szinJelzes(OlympicsTeam $t): HtmlString
    {
        $pont = filled($t->color_hex)
            ? '<span style="display:inline-block;width:.9em;height:.9em;border-radius:50%;vertical-align:-.1em;margin-right:.35em;border:1px solid rgba(127,127,127,.6);background:' . e($t->color_hex) . '"></span>'
            : '';
        $felszabadult = $t->color_key === null ? ' <span style="opacity:.7">(felszabadult)</span>' : '';

        return new HtmlString($pont . e($t->color_name) . $felszabadult);
    }

    /** @return array<string, string> a szabad színek (+ a csapat sajátja) */
    private static function szinOpciok(?OlympicsTeam $record): array
    {
        $ev = $record?->year ?? Festival::year();
        $elvitt = OlympicsTeam::query()->where('year', $ev)->whereNotNull('color_key')
            ->when($record, fn (Builder $q) => $q->whereKeyNot($record->getKey()))->pluck('color_key')->all();
        $ki = [];
        foreach (Olimpia::szinek() as $c) {
            if (! in_array($c['key'], $elvitt, true) && ($c['active'] || $c['key'] === $record?->color_key)) {
                $ki[$c['key']] = $c['name'];
            }
        }
        if ($record?->color_key && ! isset($ki[$record->color_key])) {
            $ki[$record->color_key] = $record->color_name . ' (már nincs a listán)';
        }

        return $ki;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOlympicsTeams::route('/'),
            'view' => ViewOlympicsTeam::route('/{record}'),
            'edit' => EditOlympicsTeam::route('/{record}/edit'),
        ];
    }
}
