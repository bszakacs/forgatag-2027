<?php

namespace App\Filament\Pages;

use App\Enums\MenuItemType;
use App\Enums\NavigationGroup;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page as Aloldal;
use App\Models\User;
use App\Support\MenuTree;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * A menük szerkesztője (/admin/menu) — SAJÁT fa-szerkesztő, harmadik féltől származó fa-bővítmény NÉLKÜL
 * (PROMPT-08: a legjobb jelölt is alig karbantartott; egy elhagyott csomagtól függeni drágább).
 *
 * Átrendezés két úton, ugyanabba az író-útba (App\Support\MenuTree):
 *  · fogd-és-vidd (a Filament saját, beépített Sortable-direktívájával — érintőképernyőn is),
 *  · nyíl-gombok: fel / le / beljebb / kijjebb — mobilon és billentyűzettel ez a biztos út.
 * Nyelvenkénti elrejtés: a HU / RO kapcsoló a sorban, egy kattintás.
 */
class Menuk extends Page
{
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Tartalom;

    protected static ?string $navigationLabel = 'Menü';

    protected static ?string $title = 'Menü';

    protected static ?string $slug = 'menu';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.menuk';

    /** A szerkesztett menü (a címsorban megmarad, újratöltés után is). */
    #[Url(as: 'menu')]
    public ?int $menuId = null;

    /**
     * Újrarajzolás-számláló: a fogd-és-vidd a böngészőben már átrakta az elemeket, ezért minden mentés (vagy
     * elutasított lépés) után a fát a nézet NULLÁRÓL rajzolja újra (wire:key) — így mindig a valós állapot látszik.
     */
    public int $rev = 0;

    public static function canAccess(): bool
    {
        $u = auth('web')->user();

        return $u instanceof User && $u->role->canManageContent();
    }

    public function mount(): void
    {
        $this->menuId = $this->menu()?->id;
    }

    /** @return Collection<int, Menu> */
    public function menus(): Collection
    {
        return Menu::orderBy('sort')->get();
    }

    public function menu(): ?Menu
    {
        return Menu::find($this->menuId) ?? Menu::orderBy('sort')->first();
    }

    /**
     * A fa a nézetnek: a menüpontok a gyerekeikkel (egy lekérdezés).
     *
     * @return Collection<int, MenuItem>
     */
    public function items(): Collection
    {
        $menu = $this->menu();
        if ($menu === null) {
            return collect();
        }
        $mind = MenuItem::with('page')->where('menu_id', $menu->id)->orderBy('sort')->orderBy('id')->get();
        $gyerek = $mind->groupBy(fn (MenuItem $e) => $e->parent_id ?? 0);
        foreach ($mind as $e) {
            $e->setRelation('children', $gyerek[$e->id] ?? collect());
        }

        return $gyerek[0] ?? collect();
    }

    public function valtMenu(int $id): void
    {
        $this->menuId = Menu::findOrFail($id)->id;
    }

    /** Fogd-és-vidd után: a böngésző a TELJES fát küldi (id + children). */
    public function mentFa(array $fa): void
    {
        $this->vedett(fn (Menu $menu) => MenuTree::save($menu, $fa), 'A menü sorrendje elmentve.');
    }

    public function mozgat(int $id, string $irany): void
    {
        $this->vedett(fn (Menu $menu) => MenuTree::move($menu, $id, $irany));
    }

    /** Egy menüpont elrejtése/megmutatása egy nyelven. */
    public function nyelv(int $id, string $nyelv): void
    {
        abort_unless(in_array($nyelv, ['hu', 'ro'], true), 422);
        $this->vedett(function (Menu $menu) use ($id, $nyelv) {
            $e = $this->sajat($menu, $id);
            $mezo = "show_{$nyelv}";
            $e->update([$mezo => ! $e->{$mezo}]);
        });
    }

    /** Jog + a menü + a hiba emberi nyelven (néma hiba tilos). Hiba után a fa a valós állapotból rajzolódik újra. */
    private function vedett(callable $lepes, ?string $siker = null): void
    {
        abort_unless(static::canAccess(), 403);
        $menu = $this->menu() ?? abort(404);
        $this->rev++;
        try {
            $lepes($menu);
            if ($siker) {
                Notification::make()->title($siker)->success()->send();
            }
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Nem sikerült')->body($e->getMessage())->danger()->persistent()->send();
        }
    }

    private function sajat(Menu $menu, int $id): MenuItem
    {
        return MenuItem::where('menu_id', $menu->id)->findOrFail($id);
    }

    /** @return array<int, \Filament\Schemas\Components\Component> */
    private function urlap(): array
    {
        return [
            Grid::make(['default' => 1, 'md' => 2])->schema([
                TextInput::make('label_hu')->label('Felirat (magyar)')->required()->maxLength(255),
                TextInput::make('label_ro')->label('Felirat (román)')->maxLength(255)
                    ->helperText('Ha üres, a román oldalon a magyar felirat látszik.'),
            ]),
            Select::make('type')->label('Hová mutat')->options(MenuItemType::options())->required()->live()->native(false),
            Select::make('page_id')->label('Aloldal')->required()->searchable()
                ->options(fn () => Aloldal::orderBy('title_hu')->get()->mapWithKeys(fn (Aloldal $l) => [$l->id => "{$l->title_hu} ({$l->path()})"]))
                ->visible(fn (Get $get) => $get('type') === MenuItemType::Page->value),
            Grid::make(['default' => 1, 'md' => 2])
                ->visible(fn (Get $get) => $get('type') === MenuItemType::Link->value)
                ->schema([
                    TextInput::make('url')->label('Cím')->required()->maxLength(255)
                        ->helperText('Belső cím (/galeria) vagy teljes cím (https://...).'),
                    Toggle::make('new_tab')->label('Új lapon nyílik')->inline(false),
                ]),
            Grid::make(['default' => 1, 'md' => 2])->schema([
                Toggle::make('show_hu')->label('Látszik a magyar oldalon')->default(true),
                Toggle::make('show_ro')->label('Látszik a román oldalon')->default(true),
            ]),
        ];
    }

    /** A mezők a típushoz igazítva: ami nem tartozik a típushoz, az ne maradjon bent régi értékkel. */
    private static function tisztit(array $adat): array
    {
        $tipus = MenuItemType::from($adat['type']);

        return [
            ...$adat,
            'page_id' => $tipus === MenuItemType::Page ? $adat['page_id'] : null,
            'url' => $tipus === MenuItemType::Link ? $adat['url'] : null,
            'new_tab' => $tipus === MenuItemType::Link && ($adat['new_tab'] ?? false),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('uj')->label('Új menüpont')
                ->visible(fn () => $this->menu() !== null)
                ->schema($this->urlap())
                ->modalSubmitActionLabel('Hozzáadás')
                ->fillForm(['type' => MenuItemType::Link->value, 'show_hu' => true, 'show_ro' => true])
                ->action(function (array $data): void {
                    abort_unless(static::canAccess(), 403);
                    $menu = $this->menu() ?? abort(404);
                    MenuItem::create([...self::tisztit($data), 'menu_id' => $menu->id, 'parent_id' => null,
                        'sort' => (int) MenuItem::where('menu_id', $menu->id)->whereNull('parent_id')->max('sort') + 1]);
                    Notification::make()->title('A menüpont a menü végére került — húzd vagy nyilazd a helyére.')->success()->send();
                }),
        ];
    }

    public function szerkesztAction(): Action
    {
        return Action::make('szerkeszt')->label('Szerkesztés')->iconButton()->icon('heroicon-o-pencil-square')
            ->modalHeading(fn (array $arguments) => 'Menüpont: ' . ($this->sajat($this->menu(), (int) $arguments['id'])->label_hu))
            ->schema($this->urlap())
            ->modalSubmitActionLabel('Mentés')
            ->fillForm(fn (array $arguments) => $this->sajat($this->menu(), (int) $arguments['id'])
                ->only(['label_hu', 'label_ro', 'page_id', 'url', 'new_tab', 'show_hu', 'show_ro']) + [
                    'type' => $this->sajat($this->menu(), (int) $arguments['id'])->type->value,
                ])
            ->action(function (array $data, array $arguments): void {
                abort_unless(static::canAccess(), 403);
                $this->sajat($this->menu(), (int) $arguments['id'])->update(self::tisztit($data));
                Notification::make()->title('Menüpont elmentve.')->success()->send();
            });
    }

    public function torolAction(): Action
    {
        return DeleteAction::make('torol')->label('Törlés')->iconButton()->icon('heroicon-o-trash')->color('danger')
            ->record(fn (array $arguments) => $this->sajat($this->menu(), (int) $arguments['id']))
            ->modalDescription(function (array $arguments) {
                $db = MenuItem::where('parent_id', (int) $arguments['id'])->count();

                return $db > 0 ? "Az almenüje is törlődik ({$db} menüpont)." : 'A menüpont törlődik. A hozzá kötött aloldal megmarad.';
            });
    }
}
