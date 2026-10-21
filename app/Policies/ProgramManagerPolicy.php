<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Programgazdák (a beküldő űrlap fiókjai): szuperadmin és koordinátor kezeli. */
class ProgramManagerPolicy extends Policy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageProgramManagers();
    }

    public function view(User $user, Model $record): bool
    {
        return $user->role->canManageProgramManagers();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageProgramManagers();
    }

    public function update(User $user, Model $record): bool
    {
        return $user->role->canManageProgramManagers();
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->role->canManageProgramManagers();
    }
}
