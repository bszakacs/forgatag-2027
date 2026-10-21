<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** A programok sor-szintű szűkítése: az elsődleges kategória dönt (RowAccess). */
class ProgramScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $ids = RowAccess::categoryIds();
        if ($ids !== null) {
            $builder->whereIn($model->qualifyColumn('category_id'), $ids);
        }
    }
}
