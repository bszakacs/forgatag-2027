<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Hír — kétnyelvű, KÉZZEL fordított saját tartalom (CLAUDE.md 5.). A borító a médiatárban él.
 * `is_test`: a 2026-ból tesztelésre behozott hír — TESZT jelvénnyel látszik, és egy lépésben törölhető
 * (App\Support\Tesztadat). Adminban készült hír soha nem teszt (a mező nem tölthető tömegesen).
 */
class NewsItem extends Model
{
    protected $fillable = ['slug', 'title_hu', 'title_ro', 'excerpt_hu', 'excerpt_ro', 'body_hu', 'body_ro',
        'cover_media_id', 'status', 'published_on'];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_on' => 'date',
            'is_test' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // A slug a címből EGYSZER készül (a megosztott linkek ne törjenek el).
        static::creating(function (self $hir): void {
            if (blank($hir->slug)) {
                $alap = Str::slug($hir->title_hu) ?: 'hir';
                $slug = $alap;
                for ($i = 2; self::where('slug', $slug)->exists(); $i++) {
                    $slug = "{$alap}-{$i}";
                }
                $hir->slug = $slug;
            }
        });
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }
}
