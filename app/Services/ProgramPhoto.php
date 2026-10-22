<?php

namespace App\Services;

use App\Enums\Gyujtemeny;
use App\Enums\MediaLicense;
use App\Enums\MediaOrigin;
use App\Models\Media;
use App\Models\ProgramItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * A beküldött programfotó útja (CLAUDE.md 2/d): a beküldés a ZÁRT tárba ment (storage/app/private,
 * program_items.photo_path), és csak JÓVÁHAGYÁSKOR kerül át a nyilvános médiatárba — a „Programok”
 * gyűjteménybe, a licenc-, jogtulajdonos- és fotós-adataival. Így egy visszautasított program fotója
 * sosem lesz nyilvánosan elérhető.
 *
 * A beküldött eredeti a zárt tárban MARAD (az admin „Fotó teljes méretben” gombja és a beküldő
 * nyilatkozatának bizonyítéka); a nyilvános példány a médiatárban él, a program erre mutat (cover_media_id).
 */
class ProgramPhoto
{
    /**
     * A jóváhagyási lépés hívja, a zárolt tranzakción belül, a státuszváltás ELŐTT: ha ez nem sikerül,
     * a jóváhagyás sem történik meg (és a levél sem megy ki). Hiba → kivétel, emberi nyelven.
     */
    public static function publish(ProgramItem $p): Media
    {
        if ($p->cover_media_id !== null && ($meglevo = Media::find($p->cover_media_id)) !== null) {
            return $meglevo; // már közzétett (pl. ismételt jóváhagyás)
        }
        $zart = Storage::disk('local');
        if (blank($p->photo_path) || ! $zart->exists($p->photo_path)) {
            throw new ReviewStepRefused('A fotó fájlja hiányzik a zárt tárból — a jóváhagyás nem történt meg.');
        }

        $ext = strtolower(pathinfo($p->photo_path, PATHINFO_EXTENSION)) ?: 'jpg';
        $nev = (string) Str::uuid();
        $cel = "{$nev}.{$ext}";
        $tar = Storage::disk('mediatar');

        try {
            $tar->writeStream($cel, $zart->readStream($p->photo_path));
            if ($tar->size($cel) !== $zart->size($p->photo_path)) {
                throw new RuntimeException('a másolat mérete eltér az eredetitől');
            }
            [$w, $h] = @getimagesize($tar->path($cel)) ?: [null, null];

            $media = Media::create([
                'disk' => 'mediatar',
                'directory' => null,
                'visibility' => 'public',
                'name' => $nev,
                'path' => $cel,
                'width' => $w,
                'height' => $h,
                'size' => $tar->size($cel),
                'type' => $zart->mimeType($p->photo_path) ?: 'image/jpeg',
                'ext' => $ext,
                'title' => $p->title,
                'alt' => $p->title,
                'origin' => MediaOrigin::Bekuldes,
                // A beküldő a beküldéskor nyilatkozott a felhasználási jogról (approvalBlockers ellenőrzi):
                // a jog nála marad, a Forgatag engedélyt kapott → „minden jog fenntartva”.
                'license' => $p->photo_rights_declared ? MediaLicense::MindenJogFenntartva : MediaLicense::Ismeretlen,
                'rights_holder' => self::jogtulajdonos($p),
                'photographer_credit' => $p->photographer_credit,
                'legacy_note' => "Beküldött programfotó, jóváhagyva: {$p->title}",
            ]);
            $media->addToCollection(Gyujtemeny::Programok);
        } catch (Throwable $e) {
            $tar->delete($cel); // ne maradjon gazdátlan nyilvános fájl
            if ($e instanceof ReviewStepRefused) {
                throw $e;
            }
            report($e);

            throw new ReviewStepRefused('A fotót nem sikerült a médiatárba áttenni (' . $e->getMessage() . ') — a jóváhagyás nem történt meg.');
        }

        $p->cover_media_id = $media->id;

        return $media;
    }

    /** A jog gazdája: aki beküldte és nyilatkozott (a programgazda szervezete), ennek hiányában a szervező. */
    private static function jogtulajdonos(ProgramItem $p): ?string
    {
        return $p->programManager?->name ?: ($p->organizer_names ?: $p->contact_name);
    }
}
