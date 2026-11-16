<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\User;
use App\Models\Venue;
use App\Support\Eszkoz;
use App\Support\Terkep;
use App\Support\TerkepMentes;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use InvalidArgumentException;
use UnitEnum;

/**
 * A térkép-szerkesztő (/admin/terkep-szerkeszto, PROMPT-18) — a helyszínek RÁHÚZÁSA a rajzolt Vár/Város térképre.
 * A 2026-os `MapEditorView.astro` felülete (oldalsáv: térképfülek, helyszínlista, kiválasztott jelölő; jobbra a térkép
 * nagyítással), a Directus-belépés NÉLKÜL: a lap a panel része, a mentés a panel saját munkamenetén megy (Livewire-hívás,
 * ugyanaz a belépés) — a 2026-os 401-es hiba (más címen futó lap írt egy másikra) itt szerkezetileg nem létezhet.
 *
 * A felületet a böngésző építi (public/js/terkep-szerkeszto.js) a beágyazott induló adatból; a Livewire nem rajzolja újra
 * (wire:ignore). Szerverhez csak a Mentés megy (mentes()) → App\Support\TerkepMentes (az egyetlen író-út).
 */
class TerkepSzerkeszto extends Page
{
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Tartalom;

    protected static ?string $navigationLabel = 'Térkép-szerkesztő';

    protected static ?string $title = 'Térkép-szerkesztő';

    protected static ?string $slug = 'terkep-szerkeszto';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.terkep-szerkeszto';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        $u = auth('web')->user();

        return $u instanceof User && $u->role->canEditVenues();
    }

    /** A szerkesztő induló állapota (a nézet JSON-ként adja át). @return array<string, mixed> */
    public function config(): array
    {
        return [
            'maps' => collect(Terkep::RAJZOK)->map(fn ($r, $z) => ['zone' => $z, 'label' => $r['hu'] === 'Vár' ? 'Vártérkép' : 'Várostérkép',
                'src' => Eszkoz::url($r['src']), 'w' => $r['w'], 'h' => $r['h']])->values()->all(),
            'iranyok' => Terkep::IRANYOK,
            'venues' => $this->helyszinek(),
            'nyilvanos' => route('hu.terkep'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function helyszinek(): array
    {
        return Venue::query()->orderBy('name')->get()->map(fn (Venue $v) => [
            'id' => $v->id, 'slug' => $v->slug, 'name' => $v->name, 'parent' => $v->parent_id,
            'map_zone' => $v->map_zone?->value ?? 'none',
            'map_x' => $v->map_x === null ? null : (float) $v->map_x,
            'map_y' => $v->map_y === null ? null : (float) $v->map_y,
            'map_no' => $v->map_no, 'map_edge' => (bool) $v->map_edge,
            'map_bearing' => $v->map_bearing === null ? null : (int) $v->map_bearing,
            'short_name' => $v->short_name,
        ])->all();
    }

    /**
     * A Mentés (a böngésző hívja: $wire.mentes(sorok)). A választ a felület írja ki — siker és hiba is látszik.
     *
     * @param  list<array<string, mixed>>  $sorok
     * @return array{ok: bool, n?: int, hiba?: string, venues?: list<array<string, mixed>>}
     */
    public function mentes(array $sorok): array
    {
        // A Livewire-hívás külön kérés: a jogot itt is meg kell kérdezni (nem elég a lap megnyitásakor).
        abort_unless(static::canAccess(), 403);
        try {
            $n = TerkepMentes::ment($sorok);
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'hiba' => $e->getMessage()];
        }

        // A mentett állapotot visszaküldjük: a felület a SZERVER adatával rajzol tovább, nem a saját feltevésével.
        return ['ok' => true, 'n' => $n, 'venues' => $this->helyszinek()];
    }
}
