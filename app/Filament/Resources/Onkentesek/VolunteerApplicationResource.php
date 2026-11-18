<?php

namespace App\Filament\Resources\Onkentesek;

use App\Enums\NavigationGroup;
use App\Enums\VolunteerStatus;
use App\Filament\Resources\Onkentesek\Pages\EditVolunteerApplication;
use App\Filament\Resources\Onkentesek\Pages\ListVolunteerApplications;
use App\Filament\Resources\Onkentesek\Pages\ViewVolunteerApplication;
use App\Filament\Resources\Resource;
use App\Models\VolunteerApplication;
use App\Support\Festival;
use App\Support\Onkentes;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Önkéntes-jelentkezések (PROMPT-20; 2026: volunteer_application) — KISKORÚAK személyes adatai.
 * Jog: CSAK szuperadmin és önkéntes-koordinátor (UserRole::canManageVolunteers → VolunteerApplicationPolicy). A programkoordinátor
 * NEM látja (a menüben sem, közvetlen címmel sem).
 *
 * A jelentkező adatai nem szerkeszthetők (ő adta meg, ő nyilatkozott); a koordinátor az ÁLLAPOTOT és a belső jegyzetet állítja.
 * A dokumentumok a zárt tárból, jog-ellenőrzéssel (Admin\OnkentesFajlController) — nincs nyilvános címük.
 */
class VolunteerApplicationResource extends Resource
{
    protected static ?string $model = VolunteerApplication::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Jelentkezesek;

    protected static ?string $navigationLabel = 'Önkéntesek';

    protected static ?string $modelLabel = 'önkéntes-jelentkezés';

    protected static ?string $pluralModelLabel = 'önkéntes-jelentkezések';

    protected static ?string $slug = 'onkentesek';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'full_name';

    /** A globális keresőben se jelenjen meg név vagy egyéb személyes adat. */
    protected static bool $isGloballySearchable = false;

    public static function getNavigationBadge(): ?string
    {
        if (! \App\Support\Settings::has('festival')) {
            return null; // fesztivál-adat nélkül nincs „idei” év — a menü ettől még működjön
        }

        $n = VolunteerApplication::query()->where('year', Festival::year())->where('status', VolunteerStatus::New->value)->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Új (még fel nem keresett) jelentkezés';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                Select::make('status')->label('Állapot')->required()->native(false)
                    ->options(VolunteerStatus::options()),
                Textarea::make('coordinator_note')->label('Belső jegyzet (csak a koordinátorok látják)')->rows(4),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('A jelentkező')->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                TextEntry::make('full_name')->label('Név')->weight('bold'),
                TextEntry::make('status')->label('Állapot')->badge()
                    ->formatStateUsing(fn (VolunteerStatus $state) => $state->label())->color(fn (VolunteerStatus $state) => $state->color()),
                TextEntry::make('age')->label('Életkor')
                    ->formatStateUsing(fn (VolunteerApplication $record) => $record->age . ' év' . ($record->kiskoru() ? ' — ' . Onkentes::korhatar() . ' év alatti' : ''))
                    ->color(fn (VolunteerApplication $record) => $record->kiskoru() ? 'danger' : null)->weight(fn (VolunteerApplication $record) => $record->kiskoru() ? 'bold' : null),
                TextEntry::make('email')->label('E-mail')->url(fn (VolunteerApplication $record) => 'mailto:' . $record->email),
                TextEntry::make('was_volunteer')->label('Volt már önkéntes?')->formatStateUsing(fn (bool $state) => $state ? 'Igen' : 'Nem'),
                TextEntry::make('prev_experience')->label('Hol / mit önkénteskedett?')->placeholder('—'),
                TextEntry::make('motivation')->label('Miért szeretne önkéntes lenni?')->columnSpanFull(),
                TextEntry::make('created_at')->label('Jelentkezett')->dateTime('Y. m. d. H:i'),
            ]),
            Section::make('Mikor és hol segítene?')->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                TextEntry::make('available_days')->label('Napok')->listWithLineBreaks()->bulleted()
                    ->state(fn (VolunteerApplication $record) => array_map(fn ($d) => Onkentes::napFeliratDatumbol((string) $d), (array) $record->available_days)),
                TextEntry::make('areas')->label('Területek')->listWithLineBreaks()->bulleted(),
                TextEntry::make('has_license')->label('Jogosítvány')->formatStateUsing(fn (bool $state) => $state ? 'Van' : 'Nincs'),
            ]),
            Section::make('Egészség, étkezés, vészhelyzet')->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                TextEntry::make('health_notes')->label('Egészségügyi tudnivaló')->columnSpanFull(),
                TextEntry::make('etrend')->label('Étkezési érzékenység')
                    ->state(fn (VolunteerApplication $record) => implode(', ', array_merge((array) $record->dietary, filled($record->dietary_other) ? ['Egyéb: ' . $record->dietary_other] : [])) ?: 'nincs'),
                TextEntry::make('vesz')->label('Vészhelyzeti kontakt')
                    ->state(fn (VolunteerApplication $record) => $record->emergency_name . ' — ' . $record->emergency_phone),
            ]),
            Section::make('Dokumentumok (zárt tárban)')->columnSpanFull()
                ->description(fn (VolunteerApplication $record) => $record->kiskoru()
                    ? Onkentes::korhatar() . ' év alatt a szülői beleegyezés és a kézzel írt motivációs levél kötelező.'
                    : 'Nem kötelező (' . Onkentes::korhatar() . ' év felett).')
                ->schema(array_map(fn (string $kulcs) => TextEntry::make("dok_{$kulcs}")->label(VolunteerApplication::FAJLOK[$kulcs][2])
                    ->state(fn (VolunteerApplication $record) => self::dokumentumLink($record, $kulcs))->html(), array_keys(VolunteerApplication::FAJLOK))),
            Section::make('Belső')->columnSpanFull()->schema([
                TextEntry::make('coordinator_note')->label('Belső jegyzet')->placeholder('—'),
                TextEntry::make('privacy_text')->label('Elfogadott adatkezelési hozzájárulás (szó szerint)'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('full_name')->label('Név')->searchable()->weight('bold')
                    ->description(fn (VolunteerApplication $record) => $record->age . ' év' . ($record->kiskoru() ? ' · ' . Onkentes::korhatar() . ' alatti' : '')),
                TextColumn::make('status')->label('Állapot')->badge()
                    ->formatStateUsing(fn (VolunteerStatus $state) => $state->label())->color(fn (VolunteerStatus $state) => $state->color()),
                TextColumn::make('available_days')->label('Napok')->visibleFrom('md')
                    ->state(fn (VolunteerApplication $record) => count((array) $record->available_days) . ' nap'),
                TextColumn::make('email')->label('E-mail')->visibleFrom('md'),
                TextColumn::make('created_at')->label('Jelentkezett')->dateTime('Y. m. d. H:i')->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('year')->label('Év')
                    ->options(fn () => VolunteerApplication::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->all() + [Festival::year() => Festival::year()])
                    ->default(Festival::year()),
                SelectFilter::make('status')->label('Állapot')->options(VolunteerStatus::options()),
                TernaryFilter::make('kiskoru')->label('Korhatár alatti')
                    ->queries(
                        true: fn (Builder $q) => $q->where('age', '<', Onkentes::korhatar()),
                        false: fn (Builder $q) => $q->where('age', '>=', Onkentes::korhatar()),
                    ),
            ])
            ->recordUrl(fn (VolunteerApplication $record) => self::getUrl('view', ['record' => $record]));
    }

    private static function dokumentumLink(VolunteerApplication $a, string $kulcs): HtmlString
    {
        if ($a->vanFajl($kulcs)) {
            return new HtmlString('<a href="' . e(route('filament.admin.onkentes-dokumentum', ['jelentkezes' => $a->id, 'tipus' => $kulcs]))
                . '" target="_blank" rel="noopener" style="text-decoration:underline">Megnyitás (' . e(strtoupper(Onkentes::FAJL_TIPUSOK[$a->{VolunteerApplication::FAJLOK[$kulcs][1]}] ?? '?')) . ')</a>');
        }
        $ut = VolunteerApplication::FAJLOK[$kulcs][0];
        if (filled($a->{$ut})) {
            return new HtmlString('<span style="color:rgb(220,38,38);font-weight:700">A fájl hiányzik a tárhelyről.</span>');
        }

        return new HtmlString($a->kiskoru()
            ? '<span style="color:rgb(220,38,38);font-weight:700">Nincs feltöltve — pedig kötelező lett volna.</span>'
            : '—');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVolunteerApplications::route('/'),
            'view' => ViewVolunteerApplication::route('/{record}'),
            'edit' => EditVolunteerApplication::route('/{record}/edit'),
        ];
    }
}
