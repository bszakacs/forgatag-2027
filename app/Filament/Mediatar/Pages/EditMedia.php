<?php

namespace App\Filament\Mediatar\Pages;

use App\Filament\Mediatar\MediatarResource;
use Awcodes\Curator\Resources\Media\Pages\EditMedia as CuratorEditMedia;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class EditMedia extends CuratorEditMedia
{
    protected static string $resource = MediatarResource::class;

    /** A modell őrei (jogállás, arculat) emberi nyelvű hibával állnak meg — a lapon is így látszódjon. */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title('Nem mentettem')->body($e->getMessage())->persistent()->send();

            throw new Halt();
        }
    }
}
