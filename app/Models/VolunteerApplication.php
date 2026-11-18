<?php

namespace App\Models;

use App\Enums\VolunteerStatus;
use App\Support\Onkentes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Egy önkéntes-jelentkezés (PROMPT-20; 2026: volunteer_application). KISKORÚAK adatai is: név, életkor, egészségügyi
 * megjegyzés, szülői hozzájárulás. A két dokumentum a ZÁRT tárban (storage/app/private/onkentesek/<év>/), véletlen
 * fájlnévvel — webről nem érhető el, csak az adminból (Admin\OnkentesFajlController, jog-ellenőrzéssel).
 * Törléskor a fájlok is törlődnek (ne maradjon gazdátlan személyes adat).
 */
class VolunteerApplication extends Model
{
    /** A két dokumentum: kulcs → [útvonal-mező, típus-mező, felirat]. */
    public const FAJLOK = [
        'szuloi' => ['parental_consent_path', 'parental_consent_mime', 'Szülői beleegyezés'],
        'motivacios' => ['motivation_letter_path', 'motivation_letter_mime', 'Kézzel írt motivációs levél'],
    ];

    protected $fillable = [
        'year', 'full_name', 'age', 'email', 'was_volunteer', 'prev_experience', 'motivation', 'available_days', 'areas',
        'has_license', 'health_notes', 'dietary', 'dietary_other', 'emergency_name', 'emergency_phone',
        'parental_consent_path', 'parental_consent_mime', 'motivation_letter_path', 'motivation_letter_mime',
        'privacy_consent', 'privacy_text', 'status', 'coordinator_note',
    ];

    /** A fájlok útvonala ne kerüljön tömbbe/JSON-ba (pl. hibaüzenetbe, naplóba). */
    protected $hidden = ['parental_consent_path', 'motivation_letter_path'];

    protected $casts = [
        'year' => 'integer',
        'age' => 'integer',
        'was_volunteer' => 'boolean',
        'available_days' => 'array',
        'areas' => 'array',
        'has_license' => 'boolean',
        'dietary' => 'array',
        'privacy_consent' => 'boolean',
        'status' => VolunteerStatus::class,
    ];

    protected static function booted(): void
    {
        static::deleted(function (self $a) {
            foreach (self::FAJLOK as [$ut]) {
                if (filled($a->{$ut})) {
                    Storage::disk('local')->delete($a->{$ut});
                }
            }
        });
    }

    public function kiskoru(): bool
    {
        return $this->age < Onkentes::korhatar();
    }

    public function vanFajl(string $kulcs): bool
    {
        $ut = self::FAJLOK[$kulcs][0] ?? null;

        return $ut !== null && filled($this->{$ut}) && Storage::disk('local')->exists($this->{$ut});
    }
}
