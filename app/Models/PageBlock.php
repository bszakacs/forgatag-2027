<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Egy aloldal blokkja: szöveg, kép, felhívás (gomb), elválasztó, videók (YouTube-lista — a 2026-os
 * videócsoport). Nyelvenként elrejthető (2026: locale_visibility).
 */
class PageBlock extends Model
{
    public const TYPES = [
        'richtext' => 'Szöveg',
        'image' => 'Kép',
        'cta' => 'Felhívás (gomb)',
        'divider' => 'Elválasztó',
        'videos' => 'Videók (YouTube)',
    ];

    protected $fillable = ['page_id', 'sort', 'type', 'title_hu', 'title_ro', 'body_hu', 'body_ro', 'media_id',
        'cta_label_hu', 'cta_label_ro', 'cta_url', 'videos', 'show_hu', 'show_ro'];

    protected $attributes = ['show_hu' => true, 'show_ro' => true];

    protected function casts(): array
    {
        return [
            'videos' => 'array',
            'show_hu' => 'boolean',
            'show_ro' => 'boolean',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
