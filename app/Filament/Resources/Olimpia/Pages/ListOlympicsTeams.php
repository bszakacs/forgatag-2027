<?php

namespace App\Filament\Resources\Olimpia\Pages;

use App\Filament\Resources\Olimpia\OlympicsTeamResource;
use App\Support\Olimpia;
use Filament\Resources\Pages\ListRecords;

class ListOlympicsTeams extends ListRecords
{
    protected static string $resource = OlympicsTeamResource::class;

    /** A kapacitás egy pillantásra (a koordinátornak ez a fő kérdés: van-e szabad hely, ki a következő). */
    public function getSubheading(): ?string
    {
        $a = Olimpia::allapot();
        $varolista = \App\Models\OlympicsTeam::query()->where('year', Olimpia::ev())->where('status', \App\Enums\OlympicsStatus::Waitlist->value)->count();

        return Olimpia::ev() . ' — induló hely: ' . ($a['csapatok'] - $varolista) . ' / ' . $a['max'] . ' · várólistán: ' . $varolista
            . ' · a jelentkezés ' . (Olimpia::nyitva() ? 'NYITVA' : 'zárva') . ' (Beállítások → Családi Olimpia)';
    }
}
