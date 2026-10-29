<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Egy aloldal egy szerkeszthető szövege (vagy képe) a sablon kulcsán — a 2026-os `page.home_sections`
 * megfelelője. A megépült publikus sablonok kulcsait App\Support\LapSablon írja le: ott a sablon CSAK innen
 * olvas (beépített szöveg nincs; románul az üres mező helyén a magyar látszik).
 */
class PageText extends Model
{
    public $timestamps = false;

    protected $fillable = ['page_id', 'key', 'value_hu', 'value_ro', 'media_id', 'sort'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
