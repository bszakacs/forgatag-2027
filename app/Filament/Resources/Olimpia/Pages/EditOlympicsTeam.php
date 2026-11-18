<?php

namespace App\Filament\Resources\Olimpia\Pages;

use App\Filament\Resources\Olimpia\OlympicsTeamResource;
use App\Models\OlympicsTeam;
use App\Support\Olimpia;
use Filament\Resources\Pages\EditRecord;

class EditOlympicsTeam extends EditRecord
{
    protected static string $resource = OlympicsTeamResource::class;

    /** A szín neve és kódja a választás pillanatában rögzül (ha később átnevezik, a csapatnál látszik, mi volt). */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['color_key'] ?? null) && ($szin = Olimpia::szin($data['color_key'])) !== null) {
            $data['color_name'] = $szin['name'];
            $data['color_hex'] = $szin['hex'];
        }
        // A tárolt lista 7 helyes (mint az űrlapról jövő): a kitöltetlen helyek üresek.
        $tagok = array_values((array) ($data['members'] ?? []));
        $data['members'] = array_map(fn ($i) => $tagok[$i] ?? ['name' => null, 'age' => null], range(0, Olimpia::TAGOK - 1));

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        /** @var OlympicsTeam $r */
        $r = $this->getRecord();

        return OlympicsTeamResource::getUrl('view', ['record' => $r]);
    }
}
