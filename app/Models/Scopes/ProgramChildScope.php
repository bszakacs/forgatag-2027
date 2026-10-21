<?php

namespace App\Models\Scopes;

use App\Models\ProgramItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * A program „gyerek”-sorai (időpont, fordítás, napló) ugyanúgy szűkülnek, mint a program: csak az
 * a sor látszik, amelynek a programja látszik. A whereHas a ProgramItem saját szűkítését használja,
 * tehát a szabály egy helyen van (RowAccess).
 */
class ProgramChildScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (RowAccess::categoryIds() !== null) {
            $builder->whereHas('programItem');
        }
    }

    /** Írás-őr a gyerek-modelleknek: idegen program sorához nem lehet hozzányúlni (pl. importtal). */
    public static function guard(Model $child): void
    {
        if (RowAccess::categoryIds() === null) {
            return;
        }
        $id = $child->getAttribute('program_item_id');
        $eredeti = $child->getOriginal('program_item_id');
        foreach (array_unique(array_filter([$id, $eredeti])) as $programId) {
            if (! ProgramItem::whereKey($programId)->exists()) { // szűkített lekérdezés
                throw new AuthorizationException(RowAccess::TILTVA);
            }
        }
    }
}
