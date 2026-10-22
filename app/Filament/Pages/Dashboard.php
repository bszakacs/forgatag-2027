<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Az admin nyitólapja. Az arculat-szerkesztő CSAK a /tema-hoz fér hozzá (UserRole::canSeeDashboard):
 * neki a nyitólap (belépés után is ide jön a Filament) egyenesen az Arculat lapra irányít, és a menüben
 * sem látszik.
 */
class Dashboard extends BaseDashboard
{
    /** A menüben MINDIG az első (tulajdonosi kérés, 2026-10-22) — minden más pont sorszáma ennél nagyobb. */
    protected static ?int $navigationSort = -100;

    public static function shouldRegisterNavigation(): bool
    {
        return self::user()?->role->canSeeDashboard() ?? false;
    }

    public function mount(): void
    {
        if (! (self::user()?->role->canSeeDashboard() ?? false)) {
            $this->redirect(Tema::getUrl());
        }
    }

    private static function user(): ?User
    {
        $u = auth('web')->user();

        return $u instanceof User ? $u : null;
    }
}
