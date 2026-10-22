<?php

namespace App\Support;

use App\Enums\Gyujtemeny;
use App\Enums\MediaLicense;
use App\Enums\MediaOrigin;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Az arculat (téma) fájljai a médiatár „Arculat” gyűjteményében (6. adósság, PROMPT-07).
 *
 * EGY forrás: a fájl ugyanaz marad (public/arculat/fajlok/, lenyomatos név, a téma-adat erre hivatkozik),
 * a médiatár csak egy sort kap róla ('arculat' lemezen) — nem másolat. Cserélni továbbra is a
 * /tema-ban lehet; a médiatárban látszik, de nem törölhető és nem cserélhető (Media őrei).
 */
class ArculatMedia
{
    public const MAPPA = 'fajlok';

    /** Egy arculati fájl felvétele a médiatárba (ha még nincs). Theme::storeAsset hívja minden feltöltésnél. */
    public static function register(string $path): Media
    {
        $disk = Storage::disk(Theme::DISK);
        $media = Media::query()->where('disk', Theme::DISK)->where('path', $path)->first();
        if ($media === null) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $nev = pathinfo($path, PATHINFO_FILENAME);
            [$w, $h] = $ext !== 'svg' ? (@getimagesize($disk->path($path)) ?: [null, null]) : [null, null];
            $media = Media::create([
                'disk' => Theme::DISK,
                'directory' => self::MAPPA,
                'visibility' => 'public',
                'name' => $nev,
                'path' => $path,
                'width' => $w,
                'height' => $h,
                'size' => $disk->size($path),
                'type' => $ext === 'svg' ? 'image/svg+xml' : ($disk->mimeType($path) ?: 'application/octet-stream'),
                'ext' => $ext,
                'title' => 'Arculat: ' . preg_replace('/-[0-9a-f]{12}$/', '', $nev),
                'origin' => MediaOrigin::Arculat,
                'license' => MediaLicense::Sajat,
                'rights_holder' => 'Vásárhelyi Forgatag',
                'legacy_note' => 'Az arculat (téma) fájlja — a /tema-ban cserélhető.',
            ]);
        }
        $media->addToCollection(Gyujtemeny::Arculat);

        return $media;
    }

    /** Minden meglévő arculati fájl felvétele (telepítéskor fut). @return int hány új sor lett */
    public static function syncAll(): int
    {
        $uj = 0;
        foreach (Storage::disk(Theme::DISK)->files(self::MAPPA) as $path) {
            $uj += self::register($path)->wasRecentlyCreated ? 1 : 0;
        }

        return $uj;
    }
}
