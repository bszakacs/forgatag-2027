<?php

namespace App\Filament\Resources\ProgramManagers\Pages;

use App\Filament\Resources\ProgramManagers\ProgramManagerResource;
use App\Models\ProgramManager;
use App\Support\BekuldesLevelek;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * Új programgazda: ha a jelszó üres, a rendszer generál egyet; mentés után üdvözlő levél megy ki
 * a belépési adatokkal és a kategóriák linkjeivel (2026-ban is így ment — enélkül minden fiókot
 * kézzel kellene körbetelefonálni).
 * NÉMA HIBA TILOS: ha a levél nem ment ki, a koordinátor tartós figyelmeztetést lát a jelszóval,
 * hogy személyesen átadhassa.
 */
class CreateProgramManager extends CreateRecord
{
    protected static string $resource = ProgramManagerResource::class;

    /** A levélbe kerülő jelszó — csak a kérés idejére, nem tárolódik olvashatóan. */
    private string $jelszo = '';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['password'] = filled($data['password'] ?? null) ? $data['password'] : ProgramManager::generatePassword();
        $this->jelszo = $data['password'];

        return $data;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null; // a saját értesítés az afterCreate-ben jön (a levél sikerétől függ)
    }

    protected function afterCreate(): void
    {
        /** @var ProgramManager $beku */
        $beku = $this->record->fresh();

        if (BekuldesLevelek::udvozlo($beku, $this->jelszo)) {
            Notification::make()->success()
                ->title('A programgazda létrejött')
                ->body("Az üdvözlő levél a belépési adatokkal kiment ide: {$beku->email}.")
                ->send();
        } else {
            Notification::make()->warning()->persistent()
                ->title('A fiók létrejött, de az üdvözlő levél NEM ment ki')
                ->body("Add át neki személyesen a belépési adatokat — e-mail: {$beku->email} · jelszó: {$this->jelszo}. (A hiba oka a szerver naplójában.)")
                ->send();
        }
        $this->jelszo = '';
    }
}
