<?php

namespace App\Filament\Resources\ProgramItems;

use App\Filament\Mediatar\MediatarResource;
use App\Enums\ProgramStatus;
use App\Enums\TranslatableField;
use App\Enums\TranslationStatus;
use App\Filament\Resources\ProgramItems\Pages\ListProgramItems;
use App\Filament\Resources\ProgramItems\Pages\ViewProgramItem;
use App\Filament\Resources\Resource;
use App\Models\ProgramItem;
use App\Models\ProgramOccurrence;
use App\Models\ProgramReviewEvent;
use App\Services\ProgramReview;
use App\Support\Festival;
use App\Support\SubmissionForm;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Programok + a jóváhagyási sor. A koordinátor napi munkája ezen a lapon folyik, ezért a lista
 * 375 px-en is használható: mobilon csak a cím (alatta kategória + első nap) és az állapot látszik.
 *
 * A lépések (jóváhagyásra küldés, jóváhagyás, visszaküldés) a ViewProgramItem lapon vannak, a logikájuk
 * az App\Services\ProgramReview-ban. Szerkesztés és új program felvitele: későbbi kör.
 *
 * SOR-SZINTŰ JOG (kategóriagazda): NEM itt fog szűkülni, hanem a ProgramItem modell globális scope-jában
 * — így a lista, a globális kereső, a kapcsolat-kezelők, az export és a fotó-útvonal egyszerre szűkül.
 */
class ProgramItemResource extends Resource
{
    protected static ?string $model = ProgramItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Programok';

    protected static ?string $modelLabel = 'program';

    protected static ?string $pluralModelLabel = 'programok';

    protected static ?int $navigationSort = -10;

    protected static ?string $slug = 'programok';

    protected static ?string $recordTitleAttribute = 'title';

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'organizer_names'];
    }

    /** A menüben: hány program vár jóváhagyásra. */
    public static function getNavigationBadge(): ?string
    {
        $db = ProgramItem::where('status', ProgramStatus::PendingReview->value)->count();

        return $db > 0 ? (string) $db : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return ProgramStatus::PendingReview->color();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Jóváhagyásra vár';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['category', 'venue.parent', 'programManager'])
            ->withMin('occurrences', 'date')
            ->withCount(['translations as outdated_translations_count' => fn (Builder $t) => $t->where('status', TranslationStatus::Outdated->value)]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // A sor eleje: ami jóváhagyásra vár, aztán a vázlatok; azon belül a legrégebben beérkezett elöl.
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw('CASE status WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END', ProgramStatus::queueValues())
                ->orderBy('created_at'))
            ->columns([
                // A program képe a neve előtt — ugyanaz a kinézet, mint a médiatár és a támogatók listájában: egyforma
                // négyzetes doboz, best-fit (tulajdonosi kérés, 2026-10-28). Jóváhagyás után a médiatár-borító, előtte a
                // zárt tárban lévő beküldött fotó kis előnézete (csak belépett adminnak). Fotó nélkül: üres, szürke doboz.
                ImageColumn::make('kep')->label('')->imageWidth(48)->imageHeight(48)
                    ->state(fn (ProgramItem $r) => self::thumbUrl($r))
                    ->defaultImageUrl(self::NINCS_FOTO)
                    ->extraImgAttributes(fn (ProgramItem $r) => [
                        'style' => 'object-fit:contain;padding:3px;border-radius:.375rem;background:#fff;box-shadow:inset 0 0 0 1px rgb(0 0 0 / .1)',
                        'alt' => $r->hasPhotoFile() || $r->cover ? $r->title : 'Nincs fotó',
                        'title' => $r->hasPhotoFile() || $r->cover ? null : 'Nincs fotó',
                    ]),
                TextColumn::make('title')->label('Program')->searchable(['title', 'organizer_names'])->sortable()
                    ->weight('medium')->wrap()
                    ->description(fn (ProgramItem $r) => collect([
                        $r->category?->name_hu,
                        $r->occurrences_min_date ? Festival::dayLabel(CarbonImmutable::parse($r->occurrences_min_date)) : null,
                    ])->filter()->implode(' · ')),
                // Az állapot mellett FELTŰNŐEN: elavult fordítás (a román szöveg téves adatot tartalmazhat).
                TextColumn::make('status')->label('Állapot')->badge()
                    ->state(fn (ProgramItem $r) => array_values(array_filter([
                        $r->status->label(),
                        $r->outdated_translations_count > 0 ? '⚠ Elavult fordítás' : null,
                    ])))
                    ->color(fn (string $state, ProgramItem $r) => $state === $r->status->label() ? $r->status->color() : 'danger'),
                TextColumn::make('occurrences_min_date')->label('Első nap')->sortable()
                    ->formatStateUsing(fn ($state) => $state ? Festival::dayLabel(CarbonImmutable::parse($state)) : '—')
                    ->visibleFrom('md'),
                TextColumn::make('venue.name')->label('Helyszín')->wrap()->visibleFrom('lg'),
                TextColumn::make('programManager.name')->label('Beküldő')->placeholder('belső')->visibleFrom('lg'),
                TextColumn::make('created_at')->label('Beérkezett')->date('Y. m. d.')->sortable()->visibleFrom('lg'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Állapot')->multiple()
                    ->options(collect(ProgramStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()),
                SelectFilter::make('category')->label('Kategória')
                    ->relationship('category', 'name_hu', fn (Builder $query) => $query->orderBy('sort'))
                    ->searchable()->preload(),
                SelectFilter::make('venue')->label('Helyszín')
                    ->relationship('venue', 'name', fn (Builder $query) => $query->orderBy('name'))
                    ->searchable()->preload(),
                SelectFilter::make('nap')->label('Nap')
                    ->options(fn () => self::dayOptions())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('occurrences', fn (Builder $o) => $o->whereDate('date', $data['value']))
                        : $query),
                TernaryFilter::make('foto')->label('Fotó')
                    ->trueLabel('Van fotó')->falseLabel('Nincs fotó')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('photo_path'),
                        false: fn (Builder $query) => $query->whereNull('photo_path'),
                    ),
                Filter::make('elavult')->label('Csak az elavult fordításúak')->toggle()
                    ->query(fn (Builder $query) => $query->withOutdatedTranslation()),
            ])
            ->recordUrl(fn (ProgramItem $r) => self::getUrl('view', ['record' => $r]));
    }

    /** @return array<string, string> */
    private static function dayOptions(): array
    {
        try {
            return collect(Festival::days())->mapWithKeys(fn ($d) => [
                $d['id'] => Festival::dayLabel($d['date']) . ($d['prep'] ? ' – előkészítő' : ''),
            ])->all();
        } catch (Throwable) {
            return []; // hiányzó fesztivál-beállítás: a szűrő üres, a lista működik
        }
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            // --- Figyelmeztetések a lap tetején (nem lehetnek némák) ---
            Callout::make('Ez a program még nem hagyható jóvá')->danger()->columnSpanFull()
                ->description(fn (ProgramItem $r) => self::lista(ProgramReview::approvalBlockers($r)))
                ->visible(fn (ProgramItem $r) => $r->status->isSubmitterEditable() && ProgramReview::approvalBlockers($r) !== []),
            Callout::make('Elavult román fordítás')->danger()->columnSpanFull()
                ->description('A magyar szöveg a fordítás óta megváltozott. A régi román szöveg téves adatot (időpont, ár, helyszín) tartalmazhat — a román oldalon addig a magyar szöveg látszik. Nézesd át a fordítóval.')
                ->visible(fn (ProgramItem $r) => $r->translations->contains('status', TranslationStatus::Outdated)),
            Callout::make('Visszaküldve javításra — a beküldő még nem javított')->warning()->columnSpanFull()
                ->description(fn (ProgramItem $r) => ($e = $r->openReturn())
                    ? $e->created_at->format('Y. m. d. H:i') . ', ' . ($e->actor_name ?? '—') . ': „' . $e->note . '”'
                    : null)
                ->visible(fn (ProgramItem $r) => $r->openReturn() !== null),

            Section::make('Fotó')->columnSpanFull()->schema([
                ImageEntry::make('foto')->label('Fotó')->hiddenLabel()
                    ->state(fn (ProgramItem $r) => $r->hasPhotoFile() ? self::photoUrl($r) : null)
                    ->imageHeight('auto')->imageWidth('100%')
                    ->extraImgAttributes(['style' => 'max-height:70vh;object-fit:contain;border-radius:.5rem', 'alt' => 'A program beküldött fotója'])
                    ->url(fn (ProgramItem $r) => $r->hasPhotoFile() ? self::photoUrl($r) : null, shouldOpenInNewTab: true)
                    ->visible(fn (ProgramItem $r) => $r->hasPhotoFile()),
                TextEntry::make('foto_hiany')->label('Fotó')->hiddenLabel()->color('danger')->weight('bold')
                    ->state(fn (ProgramItem $r) => blank($r->photo_path) ? 'Nincs fotó.' : 'A fotó fájlja hiányzik a tárhelyről.')
                    ->visible(fn (ProgramItem $r) => ! $r->hasPhotoFile()),
                TextEntry::make('photographer_credit')->label('Fotós')->placeholder('—')->inlineLabel(),
                TextEntry::make('photo_rights_declared')->label('Felhasználási jog')->inlineLabel()
                    ->state(fn (ProgramItem $r) => $r->photo_rights_declared ? 'A beküldő nyilatkozott róla' : 'NINCS nyilatkozat')
                    ->color(fn (ProgramItem $r) => $r->photo_rights_declared ? null : 'danger'),
                // Jóváhagyáskor a fotó a zárt tárból a médiatárba kerül (App\Services\ProgramPhoto) — itt látszik, hogy megtörtént.
                TextEntry::make('mediatar')->label('Médiatár')->inlineLabel()
                    ->state(fn (ProgramItem $r) => $r->cover
                        ? 'Közzétéve a médiatárban („Programok”) — licenc: ' . $r->cover->license->label() . ', jogtulajdonos: ' . ($r->cover->rights_holder ?? '—')
                        : 'Még nincs a médiatárban (a fotó a zárt tárban van, jóváhagyáskor kerül át).')
                    ->url(fn (ProgramItem $r) => $r->cover && auth()->user()?->can('update', $r->cover)
                        ? MediatarResource::getUrl('edit', ['record' => $r->cover]) : null),
            ]),

            Section::make('Program')->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                TextEntry::make('title')->label('Cím')->weight('bold')->columnSpanFull(),
                TextEntry::make('status')->label('Állapot')->badge()
                    ->formatStateUsing(fn (ProgramStatus $state) => $state->label())
                    ->color(fn (ProgramStatus $state) => $state->color()),
                TextEntry::make('category.name_hu')->label('Kategória')->placeholder('—'),
                TextEntry::make('extraCategories.name_hu')->label('További kategóriák')->badge()->placeholder('—'),
                TextEntry::make('helyszin')->label('Helyszín')
                    ->state(fn (ProgramItem $r) => $r->venue ? ($r->venue->parent ? $r->venue->parent->name . ' › ' : '') . $r->venue->name : null)
                    ->placeholder('—'),
                TextEntry::make('organizer_names')->label('Szervező(k)')->placeholder('—'),
                TextEntry::make('entry_type')->label('Belépés')
                    ->formatStateUsing(fn ($state) => $state?->label())->placeholder('—'),
                TextEntry::make('ticket_info')->label('Jegyinformáció')->placeholder('—')->columnSpanFull(),
                TextEntry::make('description')->label('Leírás')->prose()->columnSpanFull()
                    ->formatStateUsing(fn (?string $state) => new HtmlString(nl2br(e((string) $state)))),
                TextEntry::make('extra')->label('Kategória-specifikus adatok')->columnSpanFull()
                    ->state(fn (ProgramItem $r) => self::extraLines($r))
                    ->listWithLineBreaks()->bulleted()->placeholder('—'),
            ]),

            Section::make('Időpontok')->columnSpanFull()->schema([
                TextEntry::make('idopontok')->label('Időpontok')->hiddenLabel()
                    ->state(fn (ProgramItem $r) => $r->occurrences->map(fn (ProgramOccurrence $o) => self::occurrenceLine($o))->all())
                    ->listWithLineBreaks()->bulleted()
                    ->placeholder(new HtmlString('<span style="color:rgb(var(--danger-600))">Nincs időpont.</span>')),
                TextEntry::make('schedule_note')->label('Megjegyzés az időponthoz')->placeholder('—')->inlineLabel(),
            ]),

            Section::make('Beküldő')->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                TextEntry::make('source')->label('Forrás')->formatStateUsing(fn ($state) => $state?->label()),
                TextEntry::make('programManager.name')->label('Programgazda')->placeholder('—'),
                TextEntry::make('contact_name')->label('Kapcsolattartó')->placeholder('—'),
                TextEntry::make('contact_email')->label('E-mail')->placeholder('—')->copyable()
                    ->url(fn (ProgramItem $r) => filled($r->contact_email) ? 'mailto:' . $r->contact_email : null),
                TextEntry::make('created_at')->label('Beérkezett')->dateTime('Y. m. d. H:i'),
                TextEntry::make('updated_at')->label('Utoljára módosítva')->dateTime('Y. m. d. H:i'),
            ]),

            // A fordítás helye — előkészítve. Az ELAVULT sor pirosan, feltűnően (CLAUDE.md 5.).
            Section::make('Román fordítás')->columnSpanFull()->collapsible()->schema([
                TextEntry::make('forditasok')->label('Román fordítás')->hiddenLabel()
                    ->state(fn (ProgramItem $r) => self::translationLines($r))
                    ->listWithLineBreaks()->html()
                    ->placeholder('Még nincs fordítás. (A gépi fordítás a fordítási körben indul — cím, leírás, jegyinformáció.)'),
            ]),

            Section::make('Jóváhagyási napló')->columnSpanFull()->collapsible()->schema([
                TextEntry::make('naplo')->label('Jóváhagyási napló')->hiddenLabel()
                    ->state(fn (ProgramItem $r) => $r->reviewEvents->map(fn (ProgramReviewEvent $e) => e(
                        $e->created_at->format('Y. m. d. H:i') . ' — ' . $e->action->label()
                        . ($e->actor_name ? ' (' . $e->actor_name . ')' : '')
                        . ($e->note ? ': „' . $e->note . '”' : '')
                    ))->all())
                    ->listWithLineBreaks()->bulleted()->placeholder('Még nincs bejegyzés.'),
            ]),
        ]);
    }

    /** A nem nyilvános fotó admin-útvonala (csak belépett adminnak szolgál ki). */
    /** Üres, szürke doboz áthúzott képpel — ha nincs fotó (a lista ne „ugráljon”). */
    public const NINCS_FOTO = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%239ca3af%22 stroke-width=%221.5%22%3E%3Crect x=%223%22 y=%225%22 width=%2218%22 height=%2214%22 rx=%222%22/%3E%3Cpath d=%22M3 19 21 5%22/%3E%3C/svg%3E';

    /** A lista kis képe: közzétett borító (médiatár, Glide) vagy a beküldött fotó kis előnézete (zárt tár). */
    public static function thumbUrl(ProgramItem $r): ?string
    {
        if ($r->cover) {
            return url($r->cover->getGlideUrl(['w' => 96, 'h' => 96, 'fit' => 'contain', 'fm' => 'webp']));
        }

        return $r->hasPhotoFile() ? self::photoUrl($r) . '&kis=1' : null;
    }

    public static function photoUrl(ProgramItem $r): string
    {
        // A ?v= a fájlnévből: ha a beküldő cseréli a fotót, a böngésző nem a régit mutatja.
        return route('filament.admin.program-foto', ['program' => $r->id, 'v' => substr(md5((string) $r->photo_path), 0, 8)]);
    }

    private static function occurrenceLine(ProgramOccurrence $o): string
    {
        $ido = substr((string) $o->start_time, 0, 5) . ($o->end_time ? '–' . substr((string) $o->end_time, 0, 5) : '');

        return Festival::dayLabel(CarbonImmutable::parse($o->date)) . ', ' . $ido
            . ($o->crossesMidnight() ? ' (éjfél után ér véget)' : '')
            . ($o->note ? ' — ' . $o->note : '');
    }

    /** @return list<string> „Felirat: érték” a kategória extra mezőiből (a feliratok egy forrásból: SubmissionForm) */
    private static function extraLines(ProgramItem $r): array
    {
        $ertekek = (array) ($r->extra_fields ?? []);
        $sorok = [];
        foreach (SubmissionForm::extraFieldsFor($r->category?->slug) as $mezo) {
            $v = $ertekek[$mezo['key']] ?? null;
            if ($v === null || $v === '' || $v === false) {
                continue;
            }
            $sorok[] = $mezo['label'] . ': ' . ($v === true ? 'igen' : $v);
        }

        return $sorok;
    }

    /** @return list<string> HTML-sorok; az elavult piros és vastag */
    private static function translationLines(ProgramItem $r): array
    {
        $cimke = [
            TranslatableField::Title->value => 'Cím',
            TranslatableField::Description->value => 'Leírás',
            TranslatableField::TicketInfo->value => 'Jegyinformáció',
        ];

        return $r->translations->sortBy(fn ($t) => array_search($t->field->value, array_keys($cimke), true))
            ->map(function ($t) use ($cimke) {
                $elavult = $t->status === TranslationStatus::Outdated;
                $fej = '<strong>' . e($cimke[$t->field->value] ?? $t->field->value) . '</strong> · '
                    . ($elavult
                        ? '<strong style="color:rgb(var(--danger-600))">⚠ ' . e($t->status->label()) . '</strong>'
                        : e($t->status->label()));

                return $fej . '<br>' . nl2br(e((string) $t->translated_text));
            })->values()->all();
    }

    /** @param list<string> $sorok */
    private static function lista(array $sorok): HtmlString
    {
        return new HtmlString('<ul style="list-style:disc;padding-left:1.25rem">'
            . implode('', array_map(fn ($s) => '<li>' . e($s) . '</li>', $sorok)) . '</ul>');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProgramItems::route('/'),
            'view' => ViewProgramItem::route('/{record}'),
        ];
    }
}
