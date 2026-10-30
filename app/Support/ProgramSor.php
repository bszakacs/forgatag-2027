<?php

namespace App\Support;

use App\Models\ProgramItem;
use App\Models\ProgramOccurrence;

/**
 * Egy találati sor: egy IDŐPONT a programjával, az időkkel a fesztiválnap skáláján (Festival::scaledMinutes).
 * Egy több napos kiállítás így minden napján külön sor — ahogy 2026-ban is.
 */
final class ProgramSor
{
    /** „Egész nap nyitva”: legalább 5 óra, és legkésőbb 13:00-kor indul (2026-os szabály: a hosszból ÉS a kezdésből). */
    private const EGESZ_NAP_PERC = 300;

    private const EGESZ_NAP_LEGKESOBB = 13 * 60;

    public readonly int $kezd;

    public readonly ?int $veg;

    public function __construct(public readonly ProgramOccurrence $idopont)
    {
        $this->kezd = Festival::scaledMinutes($idopont->start_time) ?? 0;
        $this->veg = Festival::scaledEnd($this->kezd, $idopont->end_time);
    }

    public function program(): ProgramItem
    {
        return $this->idopont->programItem;
    }

    public function nap(): string
    {
        return $this->idopont->date->format('Y-m-d');
    }

    /** Éjfél utáni kezdés (a nap végén, „éjjel” jelzéssel — hogy a pénteki „0.30” ne legyen félreérthető). */
    public function ejjel(): bool
    {
        return $this->kezd >= 1440;
    }

    public function egeszNap(): bool
    {
        return $this->veg !== null && $this->veg - $this->kezd >= self::EGESZ_NAP_PERC && $this->kezd <= self::EGESZ_NAP_LEGKESOBB;
    }

    /** reggel / delutan / este / egesznap — a lista csoportjai. */
    public function napszak(): string
    {
        return match (true) {
            $this->egeszNap() => 'egesznap',
            $this->kezd < 720 => 'delelott',
            $this->kezd < 1080 => 'delutan',
            default => 'este',
        };
    }

    /** Zajlik-e a fesztiválnap adott percében. Vég nélküli tétel egy órán át számít zajlónak (2026-os szabály). */
    public function zajlik(int $perc): bool
    {
        return $this->kezd <= $perc && ($this->veg ?? $this->kezd + 60) > $perc;
    }
}
