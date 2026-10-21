<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Admin-felhasználók: csak a szuperadmin kezeli. Saját magát senki nem törölheti. */
class UserPolicy extends Policy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageUsers();
    }

    public function view(User $user, Model $record): bool
    {
        return $user->role->canManageUsers();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageUsers();
    }

    public function update(User $user, Model $record): bool
    {
        return $user->role->canManageUsers();
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->role->canManageUsers() && ! $record->is($user);
    }
}
