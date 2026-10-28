<?php

namespace App\Models;

use App\Enums\Gyujtemeny;
use App\Enums\MediaLicense;
use App\Enums\MediaOrigin;
use Awcodes\Curator\Models\Media as CuratorMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A médiatár egy eleme (CLAUDE.md 2/d) — a Curator modellje + a MI mezőink: jogtulajdonos, licenc,
 * fotós-kredit, eredet, gyűjtemények. Az adat a mi táblánkban él; ha a Curator elhal, csak a
 * böngésző-felületet kell pótolni.
 *
 * Két őr, modell-szinten (minden felületre egyszerre hat):
 * · JOGÁLLÁS: beküldött (külsős) képnél a licenc kötelező, és „Ismeretlen” nem lehet (jogallasHiba);
 *   adminban feltöltött képnél nem áll az útba.
 * · HASZNÁLAT: amíg egy galéria, támogató, program vagy a téma használja, nem törölhető (usages) —
 *   a logó/borító nem tűnhet el némán.
 */
class Media extends CuratorMedia
{
    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'size' => 'integer',
        'curations' => 'array',
        'exif' => 'array',
        'license' => MediaLicense::class,
        'origin' => MediaOrigin::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $m): void {
            if (($hiba = $m->jogallasHiba()) !== null) {
                throw new RuntimeException($hiba);
            }
            // Az arculat fájljait a /tema kezeli (lenyomatos név, a téma-adat hivatkozik rájuk): a médiatárból
            // nem cserélhetők és nem nevezhetők át.
            if ($m->exists && $m->origin === MediaOrigin::Arculat && $m->isDirty(['path', 'name', 'disk'])) {
                throw new RuntimeException('Az arculat fájlja a médiatárból nem cserélhető és nem nevezhető át — az Arculat-szerkesztőben (/admin/tema) cseréld.');
            }
        });

        static::deleting(function (self $m): void {
            if (($ok = $m->torlesAkadaly()) !== null) {
                throw new RuntimeException($ok);
            }
        });
    }

    /** A Curator saját mezői mellé a mieink is tömegesen írhatók. */
    public function getFillable(): array
    {
        return [...parent::getFillable(), 'rights_holder', 'license', 'photographer_credit', 'origin', 'legacy_id', 'legacy_note'];
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(MediaCollection::class, 'media_collection_media', 'media_id', 'media_collection_id');
    }

    /** Rendezetlen = egyetlen gyűjteményben sincs (nem tárolt gyűjtemény, lásd App\Enums\Gyujtemeny). */
    public function scopeRendezetlen(Builder $q): Builder
    {
        return $q->whereDoesntHave('collections');
    }

    public function scopeInCollection(Builder $q, MediaCollection|Gyujtemeny|int $c): Builder
    {
        $id = match (true) {
            $c instanceof MediaCollection => $c->id,
            $c instanceof Gyujtemeny => MediaCollection::idFor($c),
            default => $c,
        };

        return $q->whereHas('collections', fn (Builder $w) => $w->whereKey($id));
    }

    public function addToCollection(Gyujtemeny|MediaCollection $c): void
    {
        $this->collections()->syncWithoutDetaching([$c instanceof MediaCollection ? $c->id : MediaCollection::idFor($c)]);
    }

    public function isIn(Gyujtemeny $g): bool
    {
        return $this->collections()->whereKey(MediaCollection::idFor($g))->exists();
    }

    /**
     * A jogállás szabálya (CLAUDE.md 2/d) — EGY helyen. Null, ha rendben; különben emberi nyelvű ok.
     * Beküldött kép: licenc kötelező, nem lehet „Ismeretlen”, és kell jogtulajdonos.
     */
    public function jogallasHiba(): ?string
    {
        if ($this->origin !== MediaOrigin::Bekuldes) {
            return null;
        }
        if ($this->license === null || $this->license === MediaLicense::Ismeretlen) {
            return 'Beküldött (külsős) képnél a licenc kötelező, és „Ismeretlen” jogállású kép nem publikálható.';
        }
        if (blank($this->rights_holder)) {
            return 'Beküldött (külsős) képnél a jogtulajdonos megadása kötelező.';
        }

        return null;
    }

    /**
     * Mi használja ezt a képet. A törlés-őr és a felület is ebből dolgozik.
     *
     * @return list<string> emberi nyelvű felsorolás
     */
    public function usages(): array
    {
        if (! $this->exists) {
            return [];
        }

        // Egy kérésen belül egyszer (a lista sorában a „Hol használjuk” oszlop ÉS a törlés-jog is kérdezi).
        return $this->usagesCache ??= $this->collectUsages();
    }

    /** @var list<string>|null */
    private ?array $usagesCache = null;

    /** @return list<string> */
    private function collectUsages(): array
    {
        $hol = [];
        $galeriak = DB::table('gallery_media')->join('galleries', 'galleries.id', '=', 'gallery_media.gallery_id')
            ->where('media_id', $this->id)->pluck('galleries.title_hu');
        foreach ($galeriak as $g) {
            $hol[] = "galéria: {$g}";
        }
        foreach (DB::table('sponsors')->where('logo_media_id', $this->id)->pluck('name') as $s) {
            $hol[] = "támogató logója: {$s}";
        }
        // Nyers lekérdezés szándékosan: a program sor-szintű szűkítése itt ne rejtse el a használatot
        // (különben egy kategóriagazda „nem használt”-nak látna egy idegen program borítóját).
        foreach (DB::table('program_items')->where('cover_media_id', $this->id)->pluck('title') as $p) {
            $hol[] = "program borítója: {$p}";
        }
        if ($this->origin === MediaOrigin::Arculat) {
            $hol[] = 'az arculat (téma) fájlja';
        }

        return $hol;
    }

    /** Null, ha törölhető; különben az ok. */
    public function torlesAkadaly(): ?string
    {
        $hol = $this->usages();

        return $hol === [] ? null
            : 'Nem törölhető, mert használatban van — ' . implode('; ', array_slice($hol, 0, 3)) . (count($hol) > 3 ? ' és még ' . (count($hol) - 3) . ' helyen' : '') . '.';
    }
}
