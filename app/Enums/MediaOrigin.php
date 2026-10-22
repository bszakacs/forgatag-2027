<?php

namespace App\Enums;

/** Honnan került a kép a médiatárba — ebből dől el, kötelező-e a licenc (MediaLicense). */
enum MediaOrigin: string
{
    /** Adminban feltöltve: saját forrás, a licenc nem kötelező. */
    case Admin = 'admin';
    /** Publikus beküldő űrlapról, jóváhagyáskor áthozva a zárt tárból: a licenc KÖTELEZŐ. */
    case Bekuldes = 'bekuldes';
    /** A 2026-os rendszerből (Directus) behozva — lásd Media::legacy_note az eredethez. */
    case Import2026 = 'import2026';
    /** Az arculat (téma) fájlja: a /tema kezeli, a médiatárban csak látszik. */
    case Arculat = 'arculat';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Adminban feltöltve',
            self::Bekuldes => 'Beküldő űrlapról (külsős)',
            self::Import2026 => '2026-os rendszerből behozva',
            self::Arculat => 'Arculat (téma-fájl)',
        };
    }
}
