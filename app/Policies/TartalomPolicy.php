<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/** A „Tartalom” saját tartalmai (hír, aloldal, menü): szuperadmin és koordinátor (UserRole::canManageContent). */
class TartalomPolicy extends Policy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageContent();
    }

    public function view(User $user, Model $record): bool
    {
        return $user->role->canManageContent();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageContent();
    }

    public function update(User $user, Model $record): bool
    {
        return $user->role->canManageContent();
    }

    public function delete(User $user, Model $record): bool|Response
    {
        return $user->role->canManageContent();
    }

    public function deleteAny(User $user): bool
    {
        return $user->role->canManageContent();
    }

    public function reorder(User $user): bool
    {
        return $user->role->canManageContent();
    }
}
