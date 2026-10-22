<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Egy év képgalériája. A képek a médiatárban élnek; a galéria csak a sorrendjüket tartja. */
class Gallery extends Model
{
    protected $fillable = ['year', 'title_hu', 'title_ro', 'credit', 'sort', 'legacy_id'];

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'gallery_media', 'gallery_id', 'media_id')
            ->withPivot('sort')->orderByPivot('sort');
    }
}
