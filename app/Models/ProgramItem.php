<?php

namespace App\Models;

use App\Enums\EntryType;
use App\Enums\ProgramSource;
use App\Enums\ProgramStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProgramItem extends Model
{
    protected $fillable = [
        'title', 'description', 'organizer_names', 'status', 'entry_type', 'ticket_info',
        'schedule_note', 'category_id', 'venue_id', 'extra_fields', 'source',
        'program_manager_id', 'contact_name', 'contact_email', 'photographer_credit',
        'photo_rights_declared',
    ];

    protected $attributes = [
        'status' => 'draft',
        'source' => 'internal',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProgramStatus::class,
            'entry_type' => EntryType::class,
            'source' => ProgramSource::class,
            'extra_fields' => 'array',
            'photo_rights_declared' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // A slug a címből EGYSZER készül, és utána nem változik (a megosztott linkek ne törjenek el).
        static::creating(function (self $program): void {
            if (blank($program->slug)) {
                $program->slug = self::uniqueSlug($program->title);
            }
        });
    }

    public static function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title) ?: 'program', 180, '');
        $slug = $base;
        for ($i = 2; self::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /** A publikus oldalon megjeleníthető programok (a lista egy helyen: ProgramStatus). */
    public function scopePublicVisible(Builder $query): void
    {
        $query->whereIn('status', ProgramStatus::publicValues());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function extraCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function programManager(): BelongsTo
    {
        return $this->belongsTo(ProgramManager::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(ProgramOccurrence::class)->orderBy('date')->orderBy('start_time');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProgramItemTranslation::class);
    }
}
