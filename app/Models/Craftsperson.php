<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kézműves (a Kézműves vásár lapján). Saját tartalom: a román bemutatkozás kézzel fordított. A kép a médiatárban él. */
class Craftsperson extends Model
{
    protected $table = 'craftspeople';

    protected $fillable = ['name', 'description_hu', 'description_ro', 'image_media_id', 'sort', 'visible', 'legacy_id'];

    protected $casts = ['visible' => 'boolean', 'sort' => 'integer'];

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    /** A publikus lap sorrendje (a 2026-os: sort szerint; egyezésnél név szerint, hogy a sorrend ne legyen véletlen). */
    public function scopeLathato(Builder $q): Builder
    {
        return $q->where('visible', true)->orderBy('sort')->orderBy('name');
    }
}
