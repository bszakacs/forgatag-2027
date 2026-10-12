<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A beküldő űrlap külsős felhasználója (programgazda). Nem lép be az adminba.
 */
#[Hidden(['password', 'remember_token'])]
class ProgramManager extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'active', 'note'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /** Az e-mail-cím mindig kisbetűvel tárolódik (a belépésnél így hasonlítunk). */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    /** A kategóriák, amelyekbe beküldhet — ezt a szerver is ellenőrzi. */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function programItems(): HasMany
    {
        return $this->hasMany(ProgramItem::class);
    }
}
