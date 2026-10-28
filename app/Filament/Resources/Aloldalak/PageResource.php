<?php

namespace App\Filament\Resources\Aloldalak;

use App\Enums\ContentStatus;
use App\Enums\Gyujtemeny;
use App\Enums\NavigationGroup;
use App\Filament\Forms\MediaPicker;
use App\Filament\Resources\Aloldalak\Pages\CreatePage;
use App\Filament\Resources\Aloldalak\Pages\EditPage;
use App\Filament\Resources\Aloldalak\Pages\ListPages;
use App\Filament\Resources\Resource;
use App\Models\Page;
use App\Models\PageBlock;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Aloldalak — a „Tartalom” menücsoportban. Cím, bevezető, nyitókép, a sablon szerkeszthető szövegei
 * (PageText) és a blokkok (PageBlock). A rendszerlap (galéria, programkereső...) slugja nem írható át, és
 * nem törölhető (Page::booted + PagePolicy).
 *
 * ⚠ A 2026-os rendszerlapok szövegének nagy része a sablonba volt égetve (pl. a fesztiválrendnek csak 18%-a
 * szerkeszthető) — itt CSAK a szerkeszthető kulcsok látszanak; a többi a publikus sablonok körében kerül át
 * (ALLAPOT.md, „Ami ezután jön”).
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Tartalom;

    protected static ?string $navigationLabel = 'Aloldalak';

    protected static ?string $modelLabel = 'aloldal';

    protected static ?string $pluralModelLabel = 'aloldalak';

    protected static ?string $slug = 'aloldalak';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title_hu';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                Text::make('Rendszerlap: a publikus oldal kódja rajzolja, ezért a címe (slug) nem írható át, és nem törölhető. Itt a címe, a bevezetője, a nyitóképe és a szerkeszthető szövegei állíthatók.')
                    ->columnSpanFull()
                    ->visible(fn (?Page $record) => $record?->is_system === true),
                TextInput::make('slug')->label('Cím a linkben (slug)')->required()->maxLength(100)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->validationMessages(['regex' => 'Csak kisbetű, szám és kötőjel (pl. „rolunk”).'])
                    ->unique(ignoreRecord: true)
                    ->prefix('/')
                    ->disabled(fn (?Page $record) => $record?->is_system === true),
                Select::make('status')->label('Állapot')->options(ContentStatus::options())->required()
                    ->default(ContentStatus::Draft->value)->native(false),
                MediaPicker::make('hero_media_id')->label('Nyitókép')
                    ->relationship('hero', 'id')
                    ->gyujtemeny(Gyujtemeny::Lapok)
                    ->columnSpanFull(),
            ]),
            Section::make('Magyar')->columnSpanFull()->schema([
                TextInput::make('title_hu')->label('Cím')->required()->maxLength(255),
                Textarea::make('lead_hu')->label('Bevezető')->rows(3),
                Textarea::make('seo_description_hu')->label('Leírás a keresőknek')->rows(2)->maxLength(300),
            ]),
            Section::make('Román')->columnSpanFull()->description('Kézzel fordítva. Ha üres, a román oldalon a magyar szöveg látszik.')->schema([
                TextInput::make('title_ro')->label('Cím')->maxLength(255),
                Textarea::make('lead_ro')->label('Bevezető')->rows(3),
                Textarea::make('seo_description_ro')->label('Leírás a keresőknek')->rows(2)->maxLength(300),
            ]),
            Section::make('Szerkeszthető szövegek')->columnSpanFull()->collapsible()->collapsed()
                ->description('A lap sablonjának átírható szövegei és képei. Üres mező = a sablon beépített szövege marad.')
                ->visible(fn (?Page $record) => $record !== null && $record->texts()->exists())
                ->schema([
                    Repeater::make('texts')->label('Szerkeszthető szövegek')->hiddenLabel()->relationship()->orderColumn('sort')
                        ->addable(false)->deletable(false)->reorderable(false)
                        ->itemLabel(fn (array $state) => $state['key'] ?? null)
                        ->collapsible()
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                Textarea::make('value_hu')->label('Magyar')->rows(2)
                                    ->visible(fn (Get $get) => blank($get('media_id'))),
                                Textarea::make('value_ro')->label('Román')->rows(2)
                                    ->visible(fn (Get $get) => blank($get('media_id'))),
                            ]),
                            MediaPicker::make('media_id')->label('Kép')
                                ->relationship('media', 'id')
                                ->gyujtemeny(Gyujtemeny::Lapok)
                                ->visible(fn (Get $get) => filled($get('media_id'))),
                        ]),
                ]),
            Section::make('Blokkok')->columnSpanFull()
                ->description('A lap tartalma blokkokból áll; sorrendjük a fogóval húzva változik. Egy blokk nyelvenként elrejthető.')
                ->schema([
                    Repeater::make('blocks')->label('Blokkok')->hiddenLabel()->relationship()->orderColumn('sort')
                        ->addActionLabel('Blokk hozzáadása')
                        ->collapsible()->collapsed()
                        ->itemLabel(fn (array $state) => self::blokkCim($state))
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 3])->schema([
                                Select::make('type')->label('Típus')->options(PageBlock::TYPES)->required()->live()->native(false),
                                Toggle::make('show_hu')->label('Látszik a magyar oldalon')->default(true)->inline(false),
                                Toggle::make('show_ro')->label('Látszik a román oldalon')->default(true)->inline(false),
                            ]),
                            Grid::make(['default' => 1, 'md' => 2])
                                ->visible(fn (Get $get) => in_array($get('type'), ['richtext', 'image', 'cta', 'videos'], true))
                                ->schema([
                                    TextInput::make('title_hu')->label('Cím (magyar)')->maxLength(255),
                                    TextInput::make('title_ro')->label('Cím (román)')->maxLength(255),
                                ]),
                            Grid::make(['default' => 1, 'md' => 2])
                                ->visible(fn (Get $get) => in_array($get('type'), ['richtext', 'cta'], true))
                                ->schema([
                                    MarkdownEditor::make('body_hu')->label('Szöveg (magyar)'),
                                    MarkdownEditor::make('body_ro')->label('Szöveg (román)'),
                                ]),
                            MediaPicker::make('media_id')->label('Kép')
                                ->relationship('media', 'id')
                                ->gyujtemeny(Gyujtemeny::Lapok)
                                ->visible(fn (Get $get) => $get('type') === 'image'),
                            Grid::make(['default' => 1, 'md' => 3])
                                ->visible(fn (Get $get) => $get('type') === 'cta')
                                ->schema([
                                    TextInput::make('cta_label_hu')->label('Gomb felirata (magyar)')->maxLength(255),
                                    TextInput::make('cta_label_ro')->label('Gomb felirata (román)')->maxLength(255),
                                    TextInput::make('cta_url')->label('Hová visz')->maxLength(255)
                                        ->helperText('Belső cím (/programok) vagy teljes cím (https://...).'),
                                ]),
                            Repeater::make('videos')->label('Videók')
                                ->visible(fn (Get $get) => $get('type') === 'videos')
                                ->addActionLabel('Videó hozzáadása')
                                ->itemLabel(fn (array $state) => $state['title_hu'] ?? null)
                                ->collapsible()->collapsed()
                                ->schema([
                                    Grid::make(['default' => 1, 'md' => 3])->schema([
                                        TextInput::make('title_hu')->label('Cím (magyar)')->required()->maxLength(255),
                                        TextInput::make('title_ro')->label('Cím (román)')->maxLength(255),
                                        TextInput::make('youtube_id')->label('YouTube-azonosító')->required()
                                            ->regex('/^[A-Za-z0-9_-]{11}$/')
                                            ->validationMessages(['regex' => 'A YouTube-azonosító 11 karakter (a link „v=” utáni része).'])
                                            ->helperText('A link „v=” utáni része, pl. 2rgLZmOLN5A.'),
                                    ]),
                                ]),
                        ]),
                ]),
        ]);
    }

    /** A blokk címsora a becsukott listában: típus + cím (+ ha rejtett valamelyik nyelven). */
    private static function blokkCim(array $state): string
    {
        $cim = PageBlock::TYPES[$state['type'] ?? ''] ?? 'Új blokk';
        if (filled($state['title_hu'] ?? null)) {
            $cim .= ' · ' . $state['title_hu'];
        }
        if (($state['type'] ?? null) === 'videos') {
            $cim .= ' (' . count($state['videos'] ?? []) . ' videó)';
        }
        $rejtett = array_keys(array_filter(['magyar' => ! ($state['show_hu'] ?? true), 'román' => ! ($state['show_ro'] ?? true)]));

        return $rejtett === [] ? $cim : $cim . ' — rejtve: ' . implode(', ', $rejtett);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('title_hu')->label('Cím')->searchable()->wrap()
                    ->description(fn (Page $r) => $r->path()),
                TextColumn::make('is_system')->label('Fajta')->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Rendszerlap' : 'Saját lap')
                    ->color(fn (bool $state) => $state ? 'gray' : 'info')->visibleFrom('md'),
                TextColumn::make('status')->label('Állapot')->badge()
                    ->formatStateUsing(fn (ContentStatus $state) => $state->label())
                    ->color(fn (ContentStatus $state) => $state->color()),
                TextColumn::make('blocks_count')->label('Blokk')->counts('blocks')->visibleFrom('md'),
            ])
            ->recordActions([EditAction::make()->iconButton()->tooltip('Szerkesztés')]); // ikon: 375 px-en is elfér (a sor maga is link)
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
