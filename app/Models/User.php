<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<int>|null a kérésen belül egyszer kérdezzük le (a szűkítés minden lekérdezésnél kéri) */
    private ?array $ownedCategoryIdsCache = null;

    protected $attributes = [
        'role' => 'read_only', // új fiók: a legszűkebb jog
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /** Minden szerepkör beléphet; hogy mit lát, azt a policy-k és a sor-szintű szűkítés döntik el. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role instanceof UserRole;
    }

    /** A kategóriagazda kategóriái (más szerepkörnél nincs jelentése). */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /** @return list<int> */
    public function ownedCategoryIds(): array
    {
        return $this->ownedCategoryIdsCache ??= $this->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all();
    }

    public function forgetOwnedCategoryIds(): void
    {
        $this->ownedCategoryIdsCache = null;
    }
}
