<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Egy menü (Főmenü, Archívum). A menüpontok fája: MenuItem (parent_id), átrendezés: App\Support\MenuTree. */
class Menu extends Model
{
    protected $fillable = ['slug', 'title_hu', 'title_ro', 'sort'];

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort');
    }
}
