<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Az admin menücsoportjai — EGY helyen (CLAUDE.md 2/b: rokon dolgok csoportba, nem külön menüpontba).
 *
 * MINDEN csoportnak kell ikon: az oldalsáv asztali nézetben is ikonokra csukható
 * (AdminPanelProvider::sidebarCollapsibleOnDesktop), és ikon nélkül becsukva a csoport felirata
 * eltűnne, az elemei pedig rendezetlenül szóródnának egymás alá. Ikonos csoport elemeinek NEM lehet
 * saját ikonja (Filament-szabály) — becsukva a csoport ikonja egy legördülőt nyit az elemekkel.
 */
enum NavigationGroup: string implements HasIcon, HasLabel
{
    // A sorrend a menü sorrendje (a Filament a felsorolás sorrendjében rakja ki a csoportokat).
    case Mediatar = 'mediatar';
    case Felhasznalok = 'felhasznalok';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mediatar => 'Médiatár',
            self::Felhasznalok => 'Felhasználók',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Mediatar => Heroicon::OutlinedPhoto,
            self::Felhasznalok => Heroicon::OutlinedUsers,
        };
    }
}
