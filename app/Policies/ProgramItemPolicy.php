<?php

namespace App\Policies;

use App\Models\ProgramItem;
use App\Models\Scopes\RowAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Programok. A sor-szintű szűkítés a modellen van (RowAccess) — itt a rekordos kérdéseknél MÉG EGYSZER
 * megnézzük a kategóriát (mélységi védelem: ha valaha egy lekérdezés megkerülné a szűrőt, a jog akkor is nemet mond).
 * Létrehozás/szerkesztés/törlés az adminban még nincs — ezért tiltott.
 */
class ProgramItemPolicy extends Policy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canSeePrograms();
    }

    public function view(User $user, Model $record): bool
    {
        return $user->role->canSeePrograms() && $this->sor($record);
    }

    public function review(User $user, ProgramItem $record): bool
    {
        return $user->role->canReviewPrograms() && $this->sor($record);
    }

    public function approve(User $user, ProgramItem $record): bool
    {
        return $user->role->canApprovePrograms() && $this->sor($record);
    }

    private function sor(Model $record): bool
    {
        return RowAccess::allowsCategory($record->getAttribute('category_id'));
    }
}
