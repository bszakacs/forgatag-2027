<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Szponzorcsomag-szint. Év nélkül: a 2027-es kezdőlista (seed). year=2026: a 2026-os rendszer szintjei
 * (a 2026-os támogatókkal együtt behozva) — más nevekkel, mint a kezdőlista, ezért külön.
 */
class SponsorTier extends Model
{
    protected $fillable = ['name', 'name_ro', 'sort', 'year', 'legacy_id'];
}
