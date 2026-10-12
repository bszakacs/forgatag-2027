<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['slug', 'name_hu', 'name_ro', 'sort'];

    /** Programok, amelyeknek ez az ELSŐDLEGES kategóriája. */
    public function programItems(): HasMany
    {
        return $this->hasMany(ProgramItem::class);
    }

    /** Programok, amelyekhez a koordinátor további kategóriaként rendelte. */
    public function extraProgramItems(): BelongsToMany
    {
        return $this->belongsToMany(ProgramItem::class);
    }

    public function programManagers(): BelongsToMany
    {
        return $this->belongsToMany(ProgramManager::class);
    }
}
