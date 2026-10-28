<?php

namespace App\Policies;

use App\Enums\MediaOrigin;
use App\Models\Media;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Médiatár: szuperadmin és koordinátor (UserRole::canManageMedia). A törlés a használatban lévő képre
 * akkor is tilos (Media::torlesAkadaly) — a gomb szürke, a súgója mondja meg az okot; a modell-őr hibával áll meg.
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

    public function delete(User $user, Model $record): bool|Response
    {
        /** @var Media $record */
        if (! $user->role->canManageMedia()) {
            return false;
        }
        // Az ok a felületen is látszik (a szürke Törlés gomb súgója) — ne tűnjön el némán a gomb.
        if ($record->origin === MediaOrigin::Arculat) {
            return Response::deny('Az arculat (téma) fájlja — nem törölhető, az Arculat-szerkesztőben cserélhető.');
        }

        return ($ok = $record->torlesAkadaly()) === null ? true : Response::deny($ok);
    }

    public function deleteAny(User $user): bool
    {
        return $user->role->canManageMedia();
    }
}
