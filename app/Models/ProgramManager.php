<?php

namespace App\Models;

use App\Support\BekuldesLevelek;
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

    /**
     * Az elfelejtett-jelszó levele — magyarul, a rendszer saját levélútján (Notifier), nem a Laravel
     * angol alapértelmezett értesítőjével.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        BekuldesLevelek::jelszoLink($this, $token);
    }

    /** Generált jelszó, ha a koordinátor üresen hagyja a mezőt (a 2026-os karakterkészlet: nincs 0/O/1/l/I). */
    public static function generatePassword(int $hossz = 12): string
    {
        $karakterek = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
        $jelszo = '';
        for ($i = 0; $i < $hossz; $i++) {
            $jelszo .= $karakterek[random_int(0, strlen($karakterek) - 1)];
        }

        return $jelszo;
    }
}
