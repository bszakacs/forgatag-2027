<?php

namespace App\Models;

use App\Enums\EntryType;
use App\Enums\ProgramSource;
use App\Enums\ProgramStatus;
use App\Enums\ReviewAction;
use App\Enums\TranslationStatus;
use App\Models\Scopes\ProgramScope;
use App\Models\Scopes\RowAccess;
use App\Support\BekuldesLevelek;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProgramItem extends Model
{
    /**
     * A jóváhagyási levél eredménye ebben a kérésben (nem tárolt mező): true = kiment, false = nem ment ki,
     * null = nem kellett levél. A jóváhagyó gomb ebből mondja meg, mi történt (néma hiba tilos).
     */
    public ?bool $approvalMailSent = null;

    protected $fillable = [
        'title', 'description', 'organizer_names', 'status', 'entry_type', 'ticket_info',
        'schedule_note', 'category_id', 'venue_id', 'extra_fields', 'source',
        'program_manager_id', 'contact_name', 'contact_email', 'photographer_credit',
        'photo_rights_declared', 'photo_path',
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
        // SOR-SZINTŰ JOG (CLAUDE.md 4.): olvasásra a globális scope, írásra/törlésre az őr — modell-szinten,
        // tehát minden felületre (lista, kereső, kapcsolatok, közvetlen cím, fotó, export, import) egyszerre.
        static::addGlobalScope(new ProgramScope);
        static::saving(function (self $program): void {
            RowAccess::assertCategory($program->category_id);
            if ($program->exists && $program->isDirty('category_id')) {
                RowAccess::assertCategory($program->getOriginal('category_id')); // idegenből se lehessen „áthozni”
            }
        });
        static::deleting(fn (self $program) => RowAccess::assertCategory($program->getOriginal('category_id')));

        // A slug a címből EGYSZER készül, és utána nem változik (a megosztott linkek ne törjenek el).
        static::creating(function (self $program): void {
            if (blank($program->slug)) {
                $program->slug = self::uniqueSlug($program->title);
            }
        });

        // A beküldő a JÓVÁHAGYÁSKOR kap levelet (nem beküldéskor) — bárhonnan történik a jóváhagyás.
        static::updated(function (self $program): void {
            $elozo = $program->getOriginal('status');
            if ($program->wasChanged('status')
                && $program->status === ProgramStatus::Published
                && $program->source === ProgramSource::PublicForm
                && $elozo instanceof ProgramStatus && $elozo->isSubmitterEditable()) {
                $program->approvalMailSent = BekuldesLevelek::jovahagyva($program);
            }
        });
    }

    /** Javíthatja-e még a beküldő (jóváhagyásig igen, utána zárolt). */
    public function isSubmitterEditable(): bool
    {
        return $this->status->isSubmitterEditable();
    }

    /** Megvan-e a fotó FÁJLJA is (nem csak a hivatkozás). A hiányzó fájl nem lehet néma. */
    public function hasPhotoFile(): bool
    {
        if (blank($this->photo_path)) {
            return false;
        }
        try {
            return Storage::disk('local')->exists($this->photo_path);
        } catch (Throwable) {
            return false;
        }
    }

    /** A legutóbbi visszaküldés, ha azóta a beküldő még nem javított és nem történt más lépés. */
    public function openReturn(): ?ProgramReviewEvent
    {
        $utolso = $this->reviewEvents()->reorder('id', 'desc')->first();

        return $utolso?->action === ReviewAction::Returned ? $utolso : null;
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

    /** Programok, amelyeknek van elavult (outdated) fordítása — a jóváhagyási sor ezt feltűnően mutatja. */
    public function scopeWithOutdatedTranslation(Builder $query): void
    {
        $query->whereHas('translations', fn (Builder $t) => $t->where('status', TranslationStatus::Outdated->value));
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

    /** Jóváhagyási napló, időrendben (append-only). */
    public function reviewEvents(): HasMany
    {
        return $this->hasMany(ProgramReviewEvent::class)->orderBy('id');
    }
}
