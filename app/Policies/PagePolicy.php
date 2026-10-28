<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/** Aloldalak: lásd TartalomPolicy. A rendszerlap nem törölhető — az ok a szürke gomb súgójában látszik. */
class PagePolicy extends TartalomPolicy
{
    public function delete(User $user, Model $record): bool|Response
    {
        /** @var Page $record */
        if (! $user->role->canManageContent()) {
            return false;
        }

        return $record->is_system
            ? Response::deny('Rendszerlap: a publikus oldal kódja a címén keresztül éri el, ezért nem törölhető.')
            : true;
    }
}
