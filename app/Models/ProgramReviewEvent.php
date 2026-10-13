<?php

namespace App\Models;

use App\Enums\ProgramStatus;
use App\Enums\ReviewAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** A jóváhagyási napló egy sora. APPEND-ONLY: módosítani és törölni nem lehet. */
class ProgramReviewEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'program_item_id', 'action', 'from_status', 'to_status', 'user_id', 'program_manager_id', 'actor_name', 'note',
    ];

    protected function casts(): array
    {
        return [
            'action' => ReviewAction::class,
            'from_status' => ProgramStatus::class,
            'to_status' => ProgramStatus::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('A jóváhagyási napló sora nem módosítható.'));
        static::deleting(fn () => throw new LogicException('A jóváhagyási napló sora nem törölhető.'));
    }

    public function programItem(): BelongsTo
    {
        return $this->belongsTo(ProgramItem::class);
    }
}
