<?php

namespace App\Filament\Mediatar\Pages;

use App\Filament\Mediatar\MediatarResource;
use App\Models\Media;
use App\Models\MediaCollection;
use App\Support\MediatarImport;
use Awcodes\Curator\Actions\MultiUploadAction;
use Awcodes\Curator\Resources\Media\Pages\ListMedia as CuratorListMedia;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use RuntimeException;

/**
 * A médiatár listája: baloldalt a gyűjtemények (a „mappa”-érzet — CLAUDE.md 2/d), jobbra a képek.
 * Mobilon a gyűjtemények a képek fölé, vízszintesen görgethető sorba kerülnek.
 */
class ListMedia extends CuratorListMedia
{
    protected static string $resource = MediatarResource::class;

    /** A kiválasztott gyűjtemény: null = minden kép, 'rendezetlen', vagy egy gyűjtemény azonosítója. */
    #[Url(as: 'gyujtemeny')]
    public ?string $gyujtemeny = null;

    public const RENDEZETLEN = 'rendezetlen';

    /** A médiatár a teljes szélességet használja (a Filament alapja ~1280 px — széles képernyőn üres sáv marad). */
    protected Width|string|null $maxContentWidth = Width::Full;

    public function getTitle(): string
    {
        return 'Médiatár';
    }

    /**
     * Rács ⇄ lista. A Curator csak átírja a nézetet, de a táblázat (oszlopok) a kérés ELEJÉN már felépült a régi
     * nézettel — így a váltás után rácskártyák maradtak listában. Itt újraépítjük, hogy ugyanabban a kérésben jó legyen.
     */
    public function changeLayoutView(): void
    {
        parent::changeLayoutView();
        session(['mediatar.nezet' => $this->layoutView]); // újratöltés után is ez maradjon
        $this->table = $this->table($this->makeTable());
    }

    public function mount(): void
    {
        parent::mount();
        $this->layoutView = session('mediatar.nezet', $this->layoutView) === 'list' ? 'list' : 'grid';
        $this->table = $this->table($this->makeTable());
    }

    /**
     * A Filament a táblázatot a tulajdonságok betöltése ELŐTT építi fel (alapérték: rács). Ha a nézet lista,
     * a betöltés után újra kell építeni — különben minden további kérés rácsot rajzolna listanézetben is.
     */
    public function hydrate(): void
    {
        if ($this->layoutView === 'list') {
            $this->table = $this->table($this->makeTable());
        }
    }

    public function updatedGyujtemeny(): void
    {
        $this->resetPage();
        $this->deselectAllTableRecords();
    }

    public function valaszt(?string $gyujtemeny): void
    {
        $this->gyujtemeny = $gyujtemeny;
        $this->updatedGyujtemeny();
    }

    /** A lista szűkítése a kiválasztott gyűjteményre — a MediatarTable hívja. */
    public function szukit(Builder $q): Builder
    {
        return match (true) {
            $this->gyujtemeny === null => $q,
            $this->gyujtemeny === self::RENDEZETLEN => $q->rendezetlen(),
            default => $q->inCollection((int) $this->gyujtemeny),
        };
    }

    public function kivalasztottGyujtemeny(): ?MediaCollection
    {
        return ctype_digit((string) $this->gyujtemeny) ? MediaCollection::find((int) $this->gyujtemeny) : null;
    }

    /** @return array<int, array{id: ?string, name: string, count: int, system: bool}> a baloldali lista */
    public function gyujtemenyLista(): array
    {
        $lista = [
            ['id' => null, 'name' => 'Minden kép', 'count' => Media::count(), 'system' => true],
            ['id' => self::RENDEZETLEN, 'name' => 'Rendezetlen', 'count' => Media::query()->rendezetlen()->count(), 'system' => true],
        ];
        foreach (MediaCollection::withCount('media')->orderBy('sort')->orderBy('name')->get() as $c) {
            $lista[] = ['id' => (string) $c->id, 'name' => $c->name, 'count' => $c->media_count, 'system' => $c->is_system];
        }

        return $lista;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('mediatar.behozatal-jelentes')->viewData(['jelentes' => MediatarImport::utolsoJelentes()]),
            Grid::make(['default' => 1, 'lg' => 4, 'xl' => 5, '2xl' => 6])->schema([
                View::make('mediatar.gyujtemenyek')->columnSpan(['default' => 1, 'lg' => 1]),
                Group::make([EmbeddedTable::make()])->columnSpan(['default' => 1, 'lg' => 3, 'xl' => 4, '2xl' => 5]),
            ]),
        ]);
    }

    public function getHeaderActions(): array
    {
        $kivalasztott = $this->kivalasztottGyujtemeny();

        return [
            ...parent::getHeaderActions(),
            Action::make('ujGyujtemeny')->label('Új gyűjtemény')->color('gray')->icon('heroicon-o-folder-plus')
                ->authorize(fn () => MediatarResource::canCreate())
                ->schema([TextInput::make('name')->label('Név')->required()->maxLength(100)])
                ->action(function (array $data): void {
                    $c = MediaCollection::create(['name' => $data['name']]);
                    $this->valaszt((string) $c->id);
                    Notification::make()->success()->title("Létrejött: „{$c->name}”.")->send();
                }),
            Action::make('atnevez')->label('Gyűjtemény átnevezése')->color('gray')
                ->visible($kivalasztott !== null && ! $kivalasztott->is_system)
                ->authorize(fn () => MediatarResource::canCreate())
                ->fillForm(fn () => ['name' => $kivalasztott?->name])
                ->schema([TextInput::make('name')->label('Új név')->required()->maxLength(100)])
                ->action(function (array $data) use ($kivalasztott): void {
                    $kivalasztott->update(['name' => $data['name']]);
                    Notification::make()->success()->title('Átnevezve.')->send();
                }),
            Action::make('gyujtemenyTorles')->label('Gyűjtemény törlése')->color('danger')
                ->visible($kivalasztott !== null && ! $kivalasztott->is_system)
                ->authorize(fn () => MediatarResource::canCreate())
                ->requiresConfirmation()
                ->modalDescription(fn () => 'A gyűjteményben lévő ' . $kivalasztott?->media()->count()
                    . ' kép NEM törlődik — csak ebből a gyűjteményből kerül ki (ha másikban sincs, „Rendezetlen” lesz).')
                ->action(function () use ($kivalasztott): void {
                    try {
                        $kivalasztott->delete();
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();

                        return;
                    }
                    $this->valaszt(null);
                    Notification::make()->success()->title('A gyűjtemény törölve; a képek megmaradtak.')->send();
                }),
        ];
    }
}
