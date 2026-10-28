<?php

namespace App\Filament\Resources\NewsItems;

use App\Enums\ContentStatus;
use App\Enums\Gyujtemeny;
use App\Enums\NavigationGroup;
use App\Filament\Forms\MediaPicker;
use App\Filament\Resources\NewsItems\Pages\CreateNewsItem;
use App\Filament\Resources\NewsItems\Pages\EditNewsItem;
use App\Filament\Resources\NewsItems\Pages\ListNewsItems;
use App\Filament\Resources\Resource;
use App\Models\NewsItem;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Hírek — kétnyelvű, KÉZZEL fordított saját tartalom (CLAUDE.md 5.), a „Tartalom” menücsoportban.
 * A borító a médiatárból; ami innen kerül fel, magától a „Hírek” gyűjteménybe kerül (MediaPicker).
 * A 2026-ból tesztelésre behozott hír TESZT jelvényt visel (App\Support\Tesztadat — egy lépésben törölhető).
 */
class NewsItemResource extends Resource
{
    protected static ?string $model = NewsItem::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Tartalom;

    protected static ?string $navigationLabel = 'Hírek';

    protected static ?string $modelLabel = 'hír';

    protected static ?string $pluralModelLabel = 'hírek';

    protected static ?string $slug = 'hirek';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title_hu';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                Text::make('Ez egy 2026-ból tesztelésre behozott hír. Élesítés előtt törlődik (Beállítások → Tesztadatok).')
                    ->color('danger')->weight('bold')->columnSpanFull()
                    ->visible(fn (?NewsItem $record) => $record?->is_test === true),
                Select::make('status')->label('Állapot')->options(ContentStatus::options())->required()
                    ->default(ContentStatus::Draft->value)->native(false),
                DatePicker::make('published_on')->label('Megjelenés dátuma')->native(false)->displayFormat('Y. m. d.')
                    ->default(now())->required(),
                MediaPicker::make('cover_media_id')->label('Borítókép')
                    ->relationship('cover', 'id')
                    ->gyujtemeny(Gyujtemeny::Hirek)
                    ->columnSpanFull(),
            ]),
            Section::make('Magyar')->columnSpanFull()->schema([
                TextInput::make('title_hu')->label('Cím')->required()->maxLength(255),
                Textarea::make('excerpt_hu')->label('Kivonat')->rows(3)->helperText('A listában és a megosztáskor látszik.'),
                MarkdownEditor::make('body_hu')->label('Szöveg'),
            ]),
            Section::make('Román')->columnSpanFull()->description('Kézzel fordítva. Ha üres, a román oldalon a magyar szöveg látszik.')->schema([
                TextInput::make('title_ro')->label('Cím')->maxLength(255),
                Textarea::make('excerpt_ro')->label('Kivonat')->rows(3),
                MarkdownEditor::make('body_ro')->label('Szöveg'),
            ]),
            Section::make()->columnSpanFull()->schema([
                TextInput::make('slug')->label('Cím a linkben (slug)')->disabled()->dehydrated(false)
                    ->helperText('A címből készül az első mentéskor, és utána nem változik (a megosztott linkek ne törjenek el).'),
            ])->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_on', 'desc')
            ->columns([
                ImageColumn::make('borito')->label('')->imageWidth(48)->imageHeight(48)
                    ->state(fn (NewsItem $r) => $r->cover ? url($r->cover->getGlideUrl(['w' => 96, 'h' => 96, 'fit' => 'crop', 'fm' => 'webp'])) : null)
                    ->extraImgAttributes(['style' => 'object-fit:cover;border-radius:.375rem']),
                TextColumn::make('title_hu')->label('Cím')->searchable()->wrap(),
                // Az állapot és a TESZT jelölés egy oszlopban, egymás alatt (375 px-en így fér el).
                TextColumn::make('allapot')->label('Állapot')->badge()->listWithLineBreaks()
                    ->state(fn (NewsItem $r) => array_values(array_filter([$r->is_test ? 'TESZT' : null, $r->status->label()])))
                    ->color(fn (string $state) => match ($state) {
                        'TESZT' => 'danger',
                        ContentStatus::Published->label() => ContentStatus::Published->color(),
                        default => ContentStatus::Draft->color(),
                    })
                    ->tooltip(fn (NewsItem $r) => $r->is_test ? 'Tesztelésre behozott 2026-os hír — élesítés előtt törlődik (Beállítások → Tesztadatok).' : null),
                TextColumn::make('published_on')->label('Megjelenés')->date('Y. m. d.')->sortable()->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Állapot')->options(ContentStatus::options()),
                TernaryFilter::make('is_test')->label('Tesztadat')->trueLabel('Csak a teszthírek')->falseLabel('Teszthírek nélkül'),
            ])
            ->recordActions([EditAction::make()->iconButton()->tooltip('Szerkesztés')]); // ikon: 375 px-en is elfér (a sor maga is link)
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsItems::route('/'),
            'create' => CreateNewsItem::route('/create'),
            'edit' => EditNewsItem::route('/{record}/edit'),
        ];
    }
}
