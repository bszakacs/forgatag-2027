<?php

namespace App\Filament\Mediatar;

use Awcodes\Curator\Resources\Media\MediaResource;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * A médiatár admin-lapja (CLAUDE.md 2/d) — a Curator erőforrása magyar feliratokkal. Önálló menüpont
 * (2026-10-28 óta: a galériák és a támogatók a „Tartalom” csoportba kerültek, egyelemű csoport nem maradt).
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
        return 'Médiatár';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return null;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedPhoto;
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }
}
