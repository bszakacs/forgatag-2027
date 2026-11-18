<?php

namespace App\Models;

use App\Enums\TranslatableField;
use App\Enums\TranslationStatus;
use App\Models\Concerns\RowRestrictedChild;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramItemTranslation extends Model
{
    use RowRestrictedChild;

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

    /** A magyar forrás lenyomata — a 2026-os translate.mjs-sel azonos (sha256 a két végén levágott szövegből). */
    public static function hashSource(?string $text): string
    {
        return hash('sha256', trim((string) $text));
    }

    /** Naprakész-e: a fordítás a program MOSTANI magyar szövegéből készült. */
    public function isCurrentFor(ProgramItem $program): bool
    {
        return $this->source_text_hash === self::hashSource($program->{$this->field->value});
    }
}
