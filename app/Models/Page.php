<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * Aloldal: cím, bevezető, SEO-leírás, nyitókép + szerkeszthető szövegek (PageText) + blokkok (PageBlock).
 *
 * RENDSZERLAP (`is_system`): a publikus oldal kódja a slugja alapján rajzolja (galéria, programkereső...).
 * A slugja nem írható át, és nem törölhető — modell-szinten őrizve, hogy semmilyen felületről ne csússzon át.
 * ⚠ A 2026-os rendszerlapok szövegének NAGY RÉSZE a sablonba volt égetve (ALLAPOT.md) — itt csak a
 * szerkeszthető kulcsok vannak; a többi a publikus sablonok körében kerül át.
 */
class Page extends Model
{
    protected $fillable = ['slug', 'title_hu', 'title_ro', 'lead_hu', 'lead_ro', 'seo_description_hu',
        'seo_description_ro', 'hero_media_id', 'status', 'sort'];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'is_system' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $lap): void {
            if ($lap->getOriginal('is_system') && $lap->isDirty('slug')) {
                throw new RuntimeException("A(z) „{$lap->getOriginal('slug')}” rendszerlap: a címe (slug) nem írható át, mert a publikus oldal ezen keresztül éri el.");
            }
        });
        static::deleting(function (self $lap): void {
            if ($lap->is_system) {
                throw new RuntimeException("A(z) „{$lap->slug}” rendszerlap nem törölhető.");
            }
        });
    }

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    public function texts(): HasMany
    {
        return $this->hasMany(PageText::class)->orderBy('sort');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlock::class)->orderBy('sort');
    }

    /** A publikus útvonal (a kezdőlapé a gyökér). */
    public function path(): string
    {
        return $this->slug === 'home' ? '/' : '/' . $this->slug;
    }
}
