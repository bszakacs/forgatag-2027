<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramOccurrence extends Model
{
    protected $fillable = ['program_item_id', 'date', 'start_time', 'end_time', 'note'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    public function programItem(): BelongsTo
    {
        return $this->belongsTo(ProgramItem::class);
    }

    /** Éjfélen átnyúlik-e (a befejezés a következő naptári nap hajnalán van, de a nap ugyanaz). */
    public function crossesMidnight(): bool
    {
        return $this->end_time !== null && $this->end_time < $this->start_time;
    }
}
