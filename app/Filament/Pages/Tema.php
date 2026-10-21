<?php

namespace App\Filament\Pages;

use App\Http\Controllers\Admin\TemaController;
use App\Models\User;
use App\Support\Theme;
use App\Support\ThemeCss;
use App\Support\ThemeEditorSpec;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * Az Arculat-szerkesztő (/admin/tema) — a téma 11 szekciója, élő előnézettel.
 *
 * Saját menüpont (PROMPT-06, kivételesen): saját szerepköre van (arculat-szerkesztő), és nem a
 * Beállítások közé való — aki a színeket állítja, ne férjen a programbeküldés kapcsolójához.
 *
 * A felület KLIENSOLDALI (public/js/tema-szerkeszto.js + tema-css.js): a csúszka húzása a böngészőben
 * írja át az előnézetet, szerver-körfordulás nélkül. Szerverhez (Admin\TemaController) csak mentés,
 * publikálás, feltöltés és gyári/verzió-betöltés megy. A Livewire NEM rajzolja újra (wire:ignore).
 */
class Tema extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static ?string $navigationLabel = 'Arculat';

    protected static ?string $title = 'Arculat';

    protected static ?string $slug = 'tema';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.tema';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        $u = auth('web')->user();

        return $u instanceof User && $u->role->canEditTheme();
    }

    /** A szerkesztő induló állapota (a nézet JSON-ként adja át a böngészőnek). @return array<string, mixed> */
    public function config(): array
    {
        $kint = Theme::current();
        $draft = Theme::draft();

        return [
            'published' => ['id' => $kint->id, 'data' => $kint->data, 'css' => ThemeCss::generate($kint->data)],
            'draft' => $draft ? [
                'data' => json_decode($draft->data, true),
                'base_version_id' => $draft->base_version_id,
                'saved_by' => $draft->saved_by,
                'updated_at' => $draft->updated_at,
            ] : null,
            'history' => Theme::history(10)->map(fn ($v) => TemaController::verzioSor($v, $kint->id))->all(),
            'spec' => ThemeEditorSpec::forClient(),
            'urls' => [
                'elonezet' => route('filament.admin.tema.elonezet'),
                'vazlat' => route('filament.admin.tema.vazlat'),
                'publikalas' => route('filament.admin.tema.publikalas'),
                'feltoltes' => route('filament.admin.tema.feltoltes'),
                'gyari' => route('filament.admin.tema.gyari'),
                'verzio' => route('filament.admin.tema.verzio', ['version' => '__ID__']),
                'arculat' => asset(Theme::DISK) . '/',
            ],
            'csrf' => csrf_token(),
        ];
    }
}
