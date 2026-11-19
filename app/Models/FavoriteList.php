<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Egy látogató kedvenclistája (PROMPT-21). Nincs gazdája, nincs személyes adata: a `token` (32 véletlen hexa karakter)
 * a megosztható link része, aki ismeri, látja és szerkeszti. Írni CSAK az App\Support\Kedvencek-en át (a változtatások
 * műveletenként, tranzakcióban — két eszköz egyidejű mentése sem veszít el semmit).
 */
class FavoriteList extends Model
{
    protected $fillable = ['token', 'program_ids'];

    protected function casts(): array
    {
        return ['program_ids' => 'array'];
    }
}
