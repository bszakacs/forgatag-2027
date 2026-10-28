<?php

namespace App\Filament\Mediatar;

use App\Enums\Gyujtemeny;
use App\Enums\MediaOrigin;
use App\Filament\Mediatar\Pages\ListMedia;
use App\Models\Media;
use App\Models\MediaCollection;
use Awcodes\Curator\Facades\Curator;
use Awcodes\Curator\Resources\Media\Tables\MediaTable;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * A médiatár táblája: a Curator rácsa/listája + a gyűjtemény-szűrés (a baloldali lista, ListMedia::szukit)
 * + tömeges „Áthelyezés gyűjteménybe” és „Hozzáadás gyűjteményhez” (CLAUDE.md 2/d: elsőre ez elég,
 * fogd-és-vidd csak ha kevésnek bizonyul).
 */
class MediatarTable extends MediaTable
{
    public static function configure(Table $table): Table
    {
        $livewire = $table->getLivewire();

        return parent::configure($table)
            // A Filament a paraméter NEVÉBŐL adja az értéket: $query kell (más névvel üres Builder jönne).
            ->modifyQueryUsing(fn (Builder $query) => $livewire instanceof ListMedia ? $livewire->szukit($query->with('collections')) : $query)
            ->searchable(['title', 'caption', 'description', 'rights_holder', 'photographer_credit', 'legacy_note'])
            ->recordActions([
                EditAction::make(),
                Curator::configureDeleteAction(DeleteAction::make()),
            ])
            ->toolbarActions([
                self::athelyez(),
                self::hozzaad(),
                self::kivesz($livewire),
                Curator::configureDeleteAction(DeleteBulkAction::make()->authorizeIndividualRecords()),
            ])
            // Mobilon is két oszlop (egy oszlopban a Curator-kártya lapos csík lenne).
            ->contentGrid(fn () => $livewire->layoutView === 'grid' ? ['default' => 2, 'md' => 3, 'lg' => 4, 'xl' => 5, '2xl' => 6] : null)
            ->defaultPaginationPageOption(48)
            ->paginationPageOptions([24, 48, 96]);
    }

    private static function gyujtemenyMezo(): Select
    {
        return Select::make('gyujtemeny')->label('Gyűjtemény')->required()
            ->options(fn () => MediaCollection::orderBy('sort')->orderBy('name')->pluck('name', 'id'))
            ->searchable()
            ->createOptionForm([TextInput::make('name')->label('Új gyűjtemény neve')->required()->maxLength(100)])
            ->createOptionUsing(fn (array $data) => MediaCollection::create(['name' => $data['name']])->id);
    }

    /** Áthelyezés: a kijelölt képek PONTOSAN ebbe az egy gyűjteménybe kerülnek (a többiből kikerülnek). */
    private static function athelyez(): BulkAction
    {
        return BulkAction::make('athelyez')->label('Áthelyezés gyűjteménybe')->icon('heroicon-o-folder-arrow-down')
            ->authorize(fn () => MediatarResource::canCreate())
            ->schema([self::gyujtemenyMezo()])
            ->action(function (Collection $records, array $data): void {
                $kihagyott = 0;
                foreach ($records as $m) {
                    /** @var Media $m */
                    if ($m->origin === MediaOrigin::Arculat) {
                        // Az arculat fájlja az „Arculat” gyűjteményből nem vehető ki (csak hozzáadható máshoz is).
                        $m->collections()->syncWithoutDetaching([(int) $data['gyujtemeny']]);
                        $kihagyott++;

                        continue;
                    }
                    $m->collections()->sync([(int) $data['gyujtemeny']]);
                }
                $nev = MediaCollection::find($data['gyujtemeny'])?->name;
                Notification::make()->success()
                    ->title(($records->count() - $kihagyott) . " kép áthelyezve ide: „{$nev}”.")
                    ->body($kihagyott ? "{$kihagyott} arculati fájl az „Arculat” gyűjteményben is megmaradt." : null)
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /** Hozzáadás: a kijelölt képek ebbe a gyűjteménybe IS bekerülnek (egy kép több gyűjteményben lehet). */
    private static function hozzaad(): BulkAction
    {
        return BulkAction::make('hozzaad')->label('Hozzáadás gyűjteményhez')->icon('heroicon-o-plus')
            ->authorize(fn () => MediatarResource::canCreate())
            ->schema([self::gyujtemenyMezo()])
            ->action(function (Collection $records, array $data): void {
                foreach ($records as $m) {
                    $m->collections()->syncWithoutDetaching([(int) $data['gyujtemeny']]);
                }
                $nev = MediaCollection::find($data['gyujtemeny'])?->name;
                Notification::make()->success()->title($records->count() . " kép hozzáadva ehhez: „{$nev}”.")->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /** Kivétel a most nézett gyűjteményből (csak ha egy gyűjteményt nézünk). */
    private static function kivesz(mixed $livewire): BulkAction
    {
        return BulkAction::make('kivesz')->label('Kivétel ebből a gyűjteményből')->icon('heroicon-o-minus')->color('gray')
            ->authorize(fn () => MediatarResource::canCreate())
            ->visible(fn () => $livewire instanceof ListMedia && $livewire->kivalasztottGyujtemeny() !== null)
            ->requiresConfirmation()
            ->modalDescription('A képek NEM törlődnek — csak ebből a gyűjteményből kerülnek ki.')
            ->action(function (Collection $records) use ($livewire): void {
                $c = $livewire->kivalasztottGyujtemeny();
                $kihagyott = 0;
                foreach ($records as $m) {
                    if ($m->origin === MediaOrigin::Arculat && $c->slug === Gyujtemeny::Arculat->value) {
                        $kihagyott++;

                        continue;
                    }
                    $m->collections()->detach($c->id);
                }
                Notification::make()->success()
                    ->title(($records->count() - $kihagyott) . " kép kivéve ebből: „{$c->name}”.")
                    ->body($kihagyott ? "{$kihagyott} arculati fájl az „Arculat” gyűjteményben marad (a /tema használja)." : null)
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
