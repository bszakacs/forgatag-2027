<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** A téma egy publikált verziója. APPEND-ONLY: módosítani és törölni nem lehet (App\Support\Theme). */
class ThemeVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['data', 'css_file', 'user_id', 'published_by', 'comment', 'contrast_override'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('A téma publikált verziója nem módosítható — új publikálás kell.'));
        static::deleting(fn () => throw new LogicException('A téma publikált verziója nem törölhető.'));
    }
}
