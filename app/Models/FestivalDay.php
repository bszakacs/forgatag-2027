<?php

namespace App\Models;

use App\Enums\FestivalDayKind;
use Illuminate\Database\Eloquent\Model;

/**
 * Egy programnap. A felirat a dátumból számolódik, nincs tárolva:
 * hu „aug. 23. (h)”, ro „23 aug. (lu)” — a 2026-os formátum szerint.
 */
class FestivalDay extends Model
{
    protected $fillable = ['date', 'kind', 'note'];

    protected $attributes = [
        'kind' => 'festival',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'kind' => FestivalDayKind::class,
        ];
    }

    public function id(): string
    {
        return $this->date->format('Y-m-d');
    }

    public function weekday(string $locale = 'hu'): string
    {
        return $this->date->locale($locale)->isoFormat('dddd');
    }

    public function label(string $locale = 'hu'): string
    {
        $d = $this->date->locale($locale);
        $rovid = self::ROVID_NAP[$locale][$d->dayOfWeekIso] ?? '';

        return $locale === 'ro'
            ? $d->day . ' ' . $d->isoFormat('MMM') . ' (' . $rovid . ')'
            : $d->isoFormat('MMM') . ' ' . $d->day . '. (' . $rovid . ')';
    }

    /** A 2026-ban használt rövid napnevek (a Carbon rövidítései ettől eltérnek). */
    private const ROVID_NAP = [
        'hu' => [1 => 'h', 2 => 'k', 3 => 'sze', 4 => 'cs', 5 => 'p', 6 => 'szo', 7 => 'v'],
        'ro' => [1 => 'lu', 2 => 'ma', 3 => 'mi', 4 => 'jo', 5 => 'vi', 6 => 'sâ', 7 => 'du'],
    ];
}
