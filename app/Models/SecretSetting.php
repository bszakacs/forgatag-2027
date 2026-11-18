<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Egy titkos beállítás (App\Support\Titkok). Az érték TITKOSÍTVA áll az adatbázisban (`encrypted` cast, APP_KEY).
 * A `value` rejtett: tömbbé/JSON-ná alakítva sem kerül ki (napló, Livewire-állapot, export). Olvasni CSAK a
 * Titkok osztályon át szabad, írni CSAK a Titkok::beallit-on át (jogosultság-ellenőrzéssel).
 */
class SecretSetting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by'];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return ['value' => 'encrypted'];
    }
}
