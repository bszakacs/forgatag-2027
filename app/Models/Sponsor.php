<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Támogató — évhez kötve (a 2026-os támogatók nem 2027-esek). A logó a médiatárban él. */
class Sponsor extends Model
{
    protected $fillable = ['name', 'year', 'sponsor_tier_id', 'url', 'logo_media_id', 'sort', 'legacy_id'];

    public function tier(): BelongsTo
    {
        return $this->belongsTo(SponsorTier::class, 'sponsor_tier_id');
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }
}
