<?php

namespace App\Models;

use App\Models\Concerns\RowRestrictedChild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Napi kiemelés: egy program egy napon, a koordinátor sorrendjében (a programkereső kiemelt sávja). */
class ProgramHighlight extends Model
{
    use RowRestrictedChild;

    protected $fillable = ['program_item_id', 'date', 'sort'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }

    public function programItem(): BelongsTo
    {
        return $this->belongsTo(ProgramItem::class);
    }
}
