<?php

namespace App\Models;

use App\Enums\MapZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    protected $fillable = [
        'slug', 'name', 'name_ro', 'short_name', 'address', 'address_ro',
        'gps_lat', 'gps_lng', 'gps_approximate', 'aliases', 'parent_id',
        'map_zone', 'map_x', 'map_y', 'map_no', 'map_edge', 'map_bearing',
    ];

    protected function casts(): array
    {
        return [
            'gps_approximate' => 'boolean',
            'aliases' => 'array',
            'map_zone' => MapZone::class,
            'map_edge' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function programItems(): HasMany
    {
        return $this->hasMany(ProgramItem::class);
    }
}
