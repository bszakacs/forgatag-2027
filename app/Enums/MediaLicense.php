<?php

namespace App\Enums;

/**
 * A médiatár képeinek jogállása (CLAUDE.md 2/d). A licenc KÉT külön eset:
 * · beküldött kép (külsős forrás) — KÖTELEZŐ, és „Ismeretlen” jogállással NEM publikálható;
 * · adminban feltöltött kép (saját forrás) — nem kötelező, az alapérték „Saját forrás”.
 * A szabály egy helyen: App\Models\Media::jogallasHiba().
 */
enum MediaLicense: string
{
    case Sajat = 'sajat';
    case MindenJogFenntartva = 'minden_jog_fenntartva';
    case Kozkincs = 'kozkincs';
    case Ismeretlen = 'ismeretlen';

    public function label(): string
    {
        return match ($this) {
            self::Sajat => 'Saját forrás (a Forgatag felel érte)',
            self::MindenJogFenntartva => 'Minden jog fenntartva (felhasználási engedéllyel)',
            self::Kozkincs => 'Közkincs',
            self::Ismeretlen => 'Ismeretlen — nem publikálható',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $l) => [$l->value => $l->label()])->all();
    }

    /** A 2026-os rendszer (Directus media_asset.license_type) értékei. */
    public static function from2026(?string $ertek): self
    {
        return match ($ertek) {
            'all_rights_reserved' => self::MindenJogFenntartva,
            'public_domain' => self::Kozkincs,
            default => self::Ismeretlen,
        };
    }
}
