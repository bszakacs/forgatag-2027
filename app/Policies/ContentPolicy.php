<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Képes tartalmak (galériák, támogatók, gyűjtemények): ugyanaz a kör, mint a médiatárnál. */
class ContentPolicy extends Policy
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
        return $user->role->canManageMedia();
    }

    public function deleteAny(User $user): bool
    {
        return $user->role->canManageMedia();
    }

    public function reorder(User $user): bool
    {
        return $user->role->canManageMedia();
    }
}
