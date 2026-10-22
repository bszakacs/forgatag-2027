<?php

namespace App\Policies;

use App\Enums\MediaOrigin;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Médiatár: szuperadmin és koordinátor (UserRole::canManageMedia). A törlés a használatban lévő képre
 * akkor is tilos (Media::torlesAkadaly) — a gomb ne is látszódjon, a modell-őr pedig hibával áll meg.
 */
class MediaPolicy extends Policy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageMedia();
    }

    public function view(User $user, Model $record): bool
    {
        return $user->role->canManageMedia();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageMedia();
    }

    public function update(User $user, Model $record): bool
    {
        return $user->role->canManageMedia();
    }

    public function delete(User $user, Model $record): bool
    {
        /** @var Media $record */
        return $user->role->canManageMedia() && $record->origin !== MediaOrigin::Arculat && $record->torlesAkadaly() === null;
    }

    public function deleteAny(User $user): bool
    {
        return $user->role->canManageMedia();
    }
}
