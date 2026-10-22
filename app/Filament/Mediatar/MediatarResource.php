<?php

namespace App\Filament\Mediatar;

use App\Enums\NavigationGroup;
use Awcodes\Curator\Resources\Media\MediaResource;
use BackedEnum;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * A médiatár admin-lapja (CLAUDE.md 2/d) — a Curator erőforrása magyar feliratokkal, a „Médiatár”
 * menücsoportban (App\Enums\NavigationGroup; ikonos csoport elemének nincs saját ikonja).
 * Beállítás: config/curator.php; a saját mezők és szabályok: App\Models\Media.
 */
class MediatarResource extends MediaResource
{
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'mediatar';

    public static function getModelLabel(): string
    {
        return 'kép / fájl';
    }

    public static function getPluralModelLabel(): string
    {
        return 'képek és fájlok';
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }

    public static function getNavigationLabel(): string
    {
        return 'Képek és fájlok';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavigationGroup::Mediatar;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return null;
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }
}
