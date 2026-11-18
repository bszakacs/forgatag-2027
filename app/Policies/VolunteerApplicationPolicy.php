<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Önkéntes-jelentkezések — KISKORÚAK személyes adatai (UserRole::canManageVolunteers: CSAK szuperadmin és önkéntes-
 * koordinátor). A dokumentumok letöltése is ezt kérdezi (Admin\OnkentesFajlController). Új jelentkezés csak az űrlapról.
 */
class VolunteerApplicationPolicy extends Policy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageVolunteers();
    }

    public function view(User $user, Model $record): bool
    {
        return $user->role->canManageVolunteers();
    }

    public function update(User $user, Model $record): bool
    {
        return $user->role->canManageVolunteers();
    }

    public function delete(User $user, Model $record): bool|Response
    {
        return $user->role->canManageVolunteers();
    }
}
