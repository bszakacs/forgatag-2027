<?php

namespace App\Models;

use App\Enums\Gyujtemeny;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A médiatár gyűjteménye — „mappa”, de csak szűrőként (CLAUDE.md 2/d). Egy kép több gyűjteményben is lehet.
 * A rendszer-gyűjteményeket (App\Enums\Gyujtemeny) a kód használja: nem törölhetők, nem nevezhetők át.
 */
class MediaCollection extends Model
{
    protected $fillable = ['slug', 'name', 'is_system', 'sort'];

    protected $casts = ['is_system' => 'boolean'];

    /** @var array<string, int> */
    private static array $ids = [];

    protected static function booted(): void
    {
        static::creating(function (self $c): void {
            $c->slug = filled($c->slug) ? $c->slug : self::uniqueSlug($c->name);
        });
        static::updating(function (self $c): void {
            if ($c->getOriginal('is_system') && $c->isDirty(['slug', 'name', 'is_system'])) {
                throw new RuntimeException("A(z) „{$c->getOriginal('name')}” rendszer-gyűjtemény nem nevezhető át.");
            }
        });
        static::deleting(function (self $c): void {
            if ($c->is_system) {
                throw new RuntimeException("A(z) „{$c->name}” rendszer-gyűjtemény nem törölhető.");
            }
        });
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'media_collection_media', 'media_collection_id', 'media_id');
    }

    /** Egy rendszer-gyűjtemény azonosítója. Ha hiányzik, az hiba (a migráció hozza létre) — nem néma. */
    public static function idFor(Gyujtemeny $g): int
    {
        return self::$ids[$g->value] ??= (int) (self::query()->where('slug', $g->value)->value('id')
            ?? throw new RuntimeException("Hiányzik a(z) „{$g->label()}” rendszer-gyűjtemény (media_collections)."));
    }

    public static function flush(): void
    {
        self::$ids = [];
    }

    private static function uniqueSlug(string $name): string
    {
        $alap = Str::slug($name) ?: 'gyujtemeny';
        $slug = $alap;
        for ($i = 2; self::where('slug', $slug)->exists(); $i++) {
            $slug = "{$alap}-{$i}";
        }

        return $slug;
    }
}
