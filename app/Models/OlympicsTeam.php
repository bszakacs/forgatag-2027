<?php

namespace App\Models;

use App\Enums\OlympicsStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Egy Családi Olimpia-csapat (PROMPT-20; 2026: olympics_team). A sorrend az `id` — a várólista ezt követi.
 *
 * A SZÍN-FOGLALÁS ATOMI: az (év, color_key) párra egyedi kulcs van az adatbázisban. VISSZALÉPÉSKOR a szín felszabadul:
 * a mentés a `color_key`-t kiüríti (2026: a „Visszalépett” státuszra futó Directus-flow tette meg — itt a modell, hogy
 * semmilyen úton ne maradhasson foglalva). A `color_name` megmarad: látszik, mi volt a csapat színe.
 */
class OlympicsTeam extends Model
{
    protected $fillable = [
        'year', 'team_name', 'color_key', 'color_name', 'color_hex', 'members', 'captain_phone', 'captain_email',
        'declaration', 'declaration_text', 'status', 'status_manual', 'coordinator_note',
    ];

    protected $casts = [
        'year' => 'integer',
        'members' => 'array',
        'declaration' => 'boolean',
        'status' => OlympicsStatus::class,
        'status_manual' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $t) {
            if ($t->status === OlympicsStatus::Withdrawn) {
                $t->color_key = null;
            }
        });
    }

    /** @return list<array{name: string, age: ?int}> a kitöltött tagok */
    public function tagok(): array
    {
        return array_values(array_filter((array) $this->members, fn ($m) => filled($m['name'] ?? null)));
    }
}
