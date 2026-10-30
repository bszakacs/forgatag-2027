<?php

namespace App\Support;

use App\Models\Category;
use App\Models\ProgramItem;
use App\Models\Venue;

/**
 * A programkereső szűrése — EGY helyen: a címsor olvasása, a 2026-os kezdőállapot, és a címsor visszaírása.
 *
 * A szűrés a címsorban él (`/programok?nap=2027-08-27&napszak=este&kategoria=koncert&kategoria=szinhaz&q=jazz`), hogy
 * megosztható, könyvjelzőzhető és a kereső számára is nyitható legyen. A többes választás ISMÉTELT kulcs — pontosan ezt
 * küldi egy JavaScript nélküli GET-űrlap a jelölőnégyzetekből, és ezt küldi a htmx is. (A PHP alapból csak az utolsót
 * tartaná meg, ezért a nyers címsort olvassuk.)
 *
 * A NAP (2026-os viselkedés, CLAUDE.md 2/e — `programkereso/script.js`):
 *  · nincs a címsorban → a KEZDŐÁLLAPOT: a fesztivál alatt „Most zajlik”, előtte a nyitónap, utána az utolsó nap;
 *    keresőszóval „minden nap” (a 2026-os keresés felülírta a napsávot);
 *  · `most` → „Most zajlik” (kizárja a napszakot: a kettő együtt ellentmondó szűrés lenne);
 *  · `mind` → minden nap (a kiválasztott nap újbóli megnyomása, a nap-címke törlése, az „Összes szűrő törlése”);
 *  · dátum → az a nap.
 * A felugró ablak programja (`program=<slug>`) is a címben van (2026: `/programok/?program=<slug>`), így megosztható.
 *
 * Egy szűrésnek EGY címe van (`url()`): az ismeretlen érték és az üres mező kiesik, a sorrend rögzített. A vezérlő a nem
 * ilyen alakú címet erre irányítja át (JS nélkül), a htmx-nek pedig ezt adja vissza a címsorba (HX-Push-Url).
 */
final class ProgramSzures
{
    public const MOST = 'most';

    public const MIND = 'mind';

    /** A napszakok a 2026-os határokkal (a fesztiválnap skáláján): délelőtt 12:00-ig, délután 18:00-ig, utána este. */
    public const NAPSZAKOK = ['delelott', 'delutan', 'este'];

    /** A keresőmező leghosszabb értelmes hossza (a cím ne nőjön a végtelenig). */
    private const Q_MAX = 80;

    /**
     * @param  list<string>  $napszakok  a NAPSZAKOK sorrendjében
     * @param  list<string>  $kategoriak  slugok, a kategóriák sorrendjében
     * @param  list<string>  $helyszinek  slugok, a helyszínek sorrendjében
     * @param  ?int  $idopont  a felugró ablak idősávjához: melyik időpontra kattintottak (a címbe NEM kerül)
     */
    public function __construct(
        public readonly ?string $nap = null,
        public readonly array $napszakok = [],
        public readonly array $kategoriak = [],
        public readonly array $helyszinek = [],
        public readonly string $q = '',
        public readonly ?string $program = null,
        public readonly ?int $idopont = null,
    ) {}

    /** A nyers címsorból (ismételt kulcsokkal). Ami nem érvényes, az kimarad — hibát nem dob. */
    public static function felcimsorbol(?string $queryString): self
    {
        $ertekek = ['nap' => [], 'napszak' => [], 'kategoria' => [], 'helyszin' => [], 'q' => [], 'program' => [], 'idopont' => []];
        foreach (explode('&', (string) $queryString) as $par) {
            if ($par === '') {
                continue;
            }
            [$k, $v] = array_pad(explode('=', $par, 2), 2, '');
            $k = preg_replace('/\[\]$/', '', urldecode($k));
            if (isset($ertekek[$k])) {
                foreach (explode(',', urldecode(str_replace('+', ' ', $v))) as $egy) {
                    $ertekek[$k][] = trim($egy);
                }
            }
        }

        $nap = collect($ertekek['nap'])->first(fn ($n) => in_array($n, [self::MOST, self::MIND], true) || Festival::isProgramDay($n));
        $napszakok = array_values(array_intersect(self::NAPSZAKOK, $ertekek['napszak']));
        if ($nap === self::MOST) {
            $napszakok = [];
        }
        $kategoriak = Category::query()->whereIn('slug', array_filter($ertekek['kategoria']))
            ->orderBy('sort')->orderBy('id')->pluck('slug')->all();
        $helyszinek = Venue::query()->whereIn('slug', array_filter($ertekek['helyszin']))
            ->orderBy('name')->pluck('slug')->all();
        $q = mb_substr(trim(preg_replace('/\s+/u', ' ', implode(' ', $ertekek['q']))), 0, self::Q_MAX);
        $program = collect($ertekek['program'])->filter()->first();
        $program = $program !== null && ProgramItem::query()->publicVisible()->where('slug', $program)->exists() ? $program : null;
        $idopont = (string) end($ertekek['idopont']);

        return new self($nap, $napszakok, $kategoriak, $helyszinek, $q, $program,
            $program !== null && ctype_digit($idopont) ? (int) $idopont : null);
    }

    /**
     * A TÉNYLEGES nap: a címsorban megadott, vagy a 2026-os kezdőállapot (lásd fent). Napszakkal a „Most” nem jár
     * együtt: ha a kezdőállapot „Most” lenne, a napszak választása „minden napot” jelent (2026: S.now = false).
     */
    public function tenylegesNap(): string
    {
        $nap = $this->nap ?? ($this->q !== '' ? self::MIND : self::alapNap());

        return $nap === self::MOST && $this->napszakok ? self::MIND : $nap;
    }

    /**
     * A kezdőállapot napja keresőszó nélkül (2026: startDayIdx a napsáv napjai között — azok a napok, amelyekre VAN
     * program): ha ma ilyen nap, „most”; ha a fesztivál véget ért, az utolsó ilyen nap; különben a nyitónap (vagy az
     * első utána következő programos nap — nem a lista első, előkészítő napja).
     */
    public static function alapNap(): string
    {
        $ma = Festival::festivalMoment()['nap'];
        $napok = ProgramKereso::programosNapok() ?: Festival::dayIds();
        if (in_array($ma, $napok, true)) {
            return self::MOST;
        }
        if ($ma > end($napok)) {
            return (string) end($napok);
        }

        return collect($napok)->first(fn ($n) => $n >= Festival::openingDay()) ?? ($napok[0] ?? Festival::openingDay());
    }

    public function most(): bool
    {
        return $this->tenylegesNap() === self::MOST;
    }

    /** A kiválasztott DÁTUM (a „Most zajlik” és a „minden nap” nem dátum). */
    public function datum(): ?string
    {
        $nap = $this->tenylegesNap();

        return in_array($nap, [self::MOST, self::MIND], true) ? null : $nap;
    }

    /** Hány szűrő aktív (a mobil „Szűrés” gomb jelvénye; 2026: nFilters — a nap és a „most” is szűrő). */
    public function aktivDb(): int
    {
        return ($this->tenylegesNap() !== self::MIND ? 1 : 0) + count($this->napszakok) + count($this->kategoriak)
            + count($this->helyszinek) + ($this->q !== '' ? 1 : 0);
    }

    /**
     * Másolat egyes részek cseréjével (a szűrő-címkék, a felugró ablak linkjei ebből építenek). A felugró ablak
     * programja csak akkor marad, ha kifejezetten megadják.
     *
     * @param  array{nap?: ?string, napszakok?: list<string>, kategoriak?: list<string>, helyszinek?: list<string>, q?: string, program?: ?string}  $mi
     */
    public function mas(array $mi): self
    {
        return new self(
            array_key_exists('nap', $mi) ? $mi['nap'] : $this->nap,
            $mi['napszakok'] ?? $this->napszakok,
            $mi['kategoriak'] ?? $this->kategoriak,
            $mi['helyszinek'] ?? $this->helyszinek,
            $mi['q'] ?? $this->q,
            $mi['program'] ?? null,
        );
    }

    /** A kanonikus címsor-rész (vezető `?`-lel, vagy üres). Az `idopont` nem része: csak a kattintás adata. */
    public function querySzoveg(): string
    {
        $parok = [];
        if ($this->nap !== null) {
            $parok[] = 'nap=' . rawurlencode($this->nap);
        }
        foreach ($this->napszakok as $n) {
            $parok[] = 'napszak=' . $n;
        }
        foreach ($this->kategoriak as $k) {
            $parok[] = 'kategoria=' . rawurlencode($k);
        }
        foreach ($this->helyszinek as $h) {
            $parok[] = 'helyszin=' . rawurlencode($h);
        }
        if ($this->q !== '') {
            $parok[] = 'q=' . rawurlencode($this->q);
        }
        if ($this->program !== null) {
            $parok[] = 'program=' . rawurlencode($this->program);
        }

        return $parok ? '?' . implode('&', $parok) : '';
    }

    /** A szűrés teljes címe az aktuális nyelven (`/programok?...` vagy `/ro/programok?...`). */
    public function url(): string
    {
        return Nyelv::ut('/programok') . $this->querySzoveg();
    }

    /** A nyers címsor kanonikus-e (az `idopont` nem számít: az csak a felugró ablak kattintásának adata). */
    public function kanonikus(string $nyers): bool
    {
        $nyers = preg_replace('/(^|&)idopont=[^&]*/', '', $nyers);

        return '?' . ltrim((string) $nyers, '&') === $this->querySzoveg() || ($nyers === '' && $this->querySzoveg() === '');
    }
}
