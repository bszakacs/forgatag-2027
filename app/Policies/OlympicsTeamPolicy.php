<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Családi Olimpia-csapatok (UserRole::canManageOlympics). Új csapat CSAK a publikus űrlapról jön (az adminban nincs „Új”):
 * a nyilatkozatot a csapat fogadja el, nem a koordinátor.
 */
class OlympicsTeamPolicy extends Policy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canManageOlympics();
    }

    public function view(User $user, Model $record): bool
    {
        return $user->role->canManageOlympics();
    }

    public function update(User $user, Model $record): bool
    {
        return $user->role->canManageOlympics();
    }

    public function delete(User $user, Model $record): bool|Response
    {
        return $user->role->canManageOlympics();
    }
}
