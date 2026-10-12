<?php

namespace App\Models;

use App\Enums\TranslatableField;
use App\Enums\TranslationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramItemTranslation extends Model
{
    protected $fillable = [
        'program_item_id', 'locale', 'field', 'translated_text', 'source_text_hash', 'status', 'check_note',
    ];

    protected $attributes = [
        'locale' => 'ro',
        'status' => 'machine_translated',
    ];

    protected function casts(): array
    {
        return [
            'field' => TranslatableField::class,
            'status' => TranslationStatus::class,
        ];
    }

    public function programItem(): BelongsTo
    {
        return $this->belongsTo(ProgramItem::class);
    }

    public static function hashSource(?string $text): string
    {
        return hash('sha256', (string) $text);
    }
}
