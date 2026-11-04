<?php

namespace App\Support;

use App\Enums\ProgramStatus;
use App\Enums\TranslatableField;
use App\Models\ProgramItem;
use App\Models\ProgramOccurrence;
use App\Models\Venue;
use Carbon\CarbonInterface;

/**
 * A 2026-os Base-lapok (helyszín, kategória, napi nézet, program lapja) közös szabályai — a 2026-os
 * `components/ProgramCard.astro`, `lib/festival.ts shortDay`, `DailyProgramView venueDisplay` átirata, betűre.
 * A kártya jelölése: resources/views/partials/programkartya.blade.php.
 */
class ProgramKartya
{
    /** A programok ehhez a laphoz kellő kapcsolatai (egy helyen, hogy a kártya ne kérdezzen soronként). */
    public const KAPCSOLATOK = ['category', 'extraCategories', 'venue.parent', 'cover', 'translations', 'occurrences'];

    /** 2026: shortDay — „aug. 28.” (mindkét nyelven így volt). */
    public static function rovidNap(CarbonInterface|string $d): string
    {
        $nap = (int) substr($d instanceof CarbonInterface ? $d->format('Y-m-d') : $d, 8, 2);

        return "aug. {$nap}.";
    }

    /** „HH:MM” az adatbázis „HH:MM:SS” alakjából. */
    public static function ora(?string $t): string
    {
        return $t === null ? '' : substr($t, 0, 5);
    }

    /** 2026: venueDisplay — „Vár, Csicsergő 1” → „Vár › Csicsergő 1”. */
    public static function helyszinMegj(string $nev): string
    {
        $i = mb_strpos($nev, ', ');

        return $i === false ? $nev : mb_substr($nev, 0, $i) . ' › ' . mb_substr($nev, $i + 2);
    }

    /**
     * Magyar ábécérend (2026: localeCompare(…, 'hu')) — az intl Collatorral; ahol nincs intl (a telepítő-PHP), ékezet nélküli
     * összevetés (a sorrend ugyanaz, csak az azonos alapbetűk egymás közti rendje lehet más).
     *
     * @template T
     * @param  \Illuminate\Support\Collection<int, T>  $lista
     * @param  callable(T): string  $nev
     * @return \Illuminate\Support\Collection<int, T>
     */
    public static function abc($lista, callable $nev)
    {
        $c = class_exists(\Collator::class) ? new \Collator('hu_HU') : null;

        return $lista->sort(fn ($a, $b) => $c ? $c->compare($nev($a), $nev($b))
            : strcmp(\Illuminate\Support\Str::ascii(mb_strtolower($nev($a))), \Illuminate\Support\Str::ascii(mb_strtolower($nev($b)))))->values();
    }

    public static function helyszinNev(?Venue $v): string
    {
        return $v === null ? '' : Nyelv::valaszt($v->name, $v->name_ro);
    }

    /** A program időpontjai sorrendben (dátum + kezdés), csak a fesztivál napjain. */
    public static function idopontok(ProgramItem $p)
    {
        return $p->occurrences
            ->filter(fn (ProgramOccurrence $o) => Festival::isProgramDay($o->date->format('Y-m-d')))
            ->sortBy(fn (ProgramOccurrence $o) => $o->date->format('Y-m-d') . ' ' . $o->start_time)->values();
    }

    /**
     * A kártya adatai — 2026: ProgramCard.astro frontmatter.
     *
     * @return array<string, mixed>
     */
    public static function adat(ProgramItem $p, ?ProgramOccurrence $occ = null): array
    {
        $occs = self::idopontok($p);
        $sav = fn (ProgramOccurrence $o) => self::rovidNap($o->date) . ' ' . self::ora($o->start_time) . ($o->end_time ? '–' . self::ora($o->end_time) : '');
        $ido = match (true) {
            $occ !== null => $sav($occ),
            $occs->count() === 1 => $sav($occs[0]),
            $occs->count() > 1 => self::rovidNap($occs[0]->date) . ' ' . self::ora($occs[0]->start_time) . ' · +' . ($occs->count() - 1) . ' ' . __('publikus.b_idopont'),
            default => '',
        };
        $statusz = match ($p->status) {
            ProgramStatus::Cancelled => 'off',
            ProgramStatus::Changed => 'chg',
            default => null,
        };
        $jegy = ProgramSzoveg::mezo($p, TranslatableField::TicketInfo)['szoveg'];

        return [
            'href' => Nyelv::ut('/program/' . $p->slug),
            'cim' => ProgramSzoveg::mezo($p, TranslatableField::Title)['szoveg'],
            // 2026: a kategóriacsoport neve, csoport nélkül a kategória neve (a csoportok 2026 júliusában megszűntek).
            'csoportNev' => $p->category ? Nyelv::valaszt($p->category->name_hu, $p->category->name_ro) : '',
            'ido' => $ido,
            'helyszin' => self::helyszinNev($p->venue),
            'datumok' => $occs->map(fn ($o) => $o->date->format('Y-m-d'))->unique()->implode(','),
            'statusz' => $statusz,
            'statuszFelirat' => $statusz === 'off' ? __('publikus.b_elmarad') : ($statusz === 'chg' ? __('publikus.b_valtozott') : ''),
            'belepes' => $p->entry_type?->value ?? 'free',
            'jegy' => $jegy,
        ];
    }

    /**
     * A kategóriaspecifikus extra mezők a program lapján — a 2026-os lib/extra-fields.ts EXTRA_DEFS + extraItems() BETŰRE
     * (a kulcsok a beküldő űrlap mezőkulcsai; a sorrend ez a lista).
     */
    public const EXTRA = [
        'performer_name' => ['Fellépő / zenekar', 'Artist / formație', 'text'],
        'speaker_bios' => ['Előadók', 'Invitați', 'long'],
        'exhibition_hours' => ['Nyitvatartás', 'Program de vizitare', 'long'],
        'vernissage_at' => ['Megnyitó', 'Vernisaj', 'text'],
        'age_limit' => ['Korhatár', 'Limită de vârstă', 'text'],
        'duration_min' => ['Időtartam', 'Durată', 'number', ['perc', 'minute']],
        'ticket_price' => ['Jegyár', 'Preț bilet', 'text'],
        'booking_link' => ['Jegyfoglalás', 'Rezervare bilete', 'link'],
        'age_group' => ['Korosztály', 'Grupă de vârstă', 'text'],
        'capacity' => ['Létszámkorlát', 'Număr limitat de locuri', 'number', ['fő', 'persoane']],
        'pre_registration' => ['Előzetes jelentkezés szükséges', 'Este necesară înscrierea prealabilă', 'flag'],
        'registration_link' => ['Regisztráció', 'Înscriere', 'link'],
        'meeting_point' => ['Találkozási pont', 'Punct de întâlnire', 'text'],
        'map_link' => ['Térkép', 'Hartă', 'link'],
        'rules_link' => ['Játékszabályzat', 'Regulament', 'link'],
        'is_book_launch' => ['Könyvbemutató', 'Lansare de carte', 'flag'],
        'book_author' => ['Szerző', 'Autor', 'text'],
        'book_author_bio' => ['A szerzőről', 'Despre autor', 'long'],
        'book_title' => ['Könyv címe', 'Titlul cărții', 'text'],
        'book_publisher' => ['Kiadó', 'Editură', 'text'],
    ];

    /** @return list<array{label: string, kind: string, value: string, href?: string}> */
    public static function extrak(?array $extra): array
    {
        $ki = [];
        $ro = Nyelv::aktualis() === 'ro';
        foreach (self::EXTRA as $kulcs => $d) {
            $raw = $extra[$kulcs] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            $cimke = $ro ? $d[1] : $d[0];
            if ($d[2] === 'flag') {
                if ($raw === true) {
                    $ki[] = ['label' => $cimke, 'kind' => 'flag', 'value' => $cimke];
                }
            } elseif ($d[2] === 'link') {
                $href = trim((string) $raw);
                if (preg_match('~^https?://~i', $href)) {
                    $ki[] = ['label' => $cimke, 'kind' => 'link', 'value' => $cimke, 'href' => $href];
                }
            } elseif ($d[2] === 'number') {
                if (is_numeric($raw)) {
                    $ki[] = ['label' => $cimke, 'kind' => 'number', 'value' => ($raw + 0) . ' ' . $d[3][$ro ? 1 : 0]];
                }
            } else {
                $ki[] = ['label' => $cimke, 'kind' => $d[2], 'value' => trim((string) $raw)];
            }
        }

        return $ki;
    }

    /** A belépés felirata (2026: t(locale, entryType)). */
    public static function belepesFelirat(string $tipus): string
    {
        return match ($tipus) {
            'ticketed' => __('publikus.b_jegyes'),
            'donation' => __('publikus.b_adomany'),
            default => __('publikus.b_ingyenes'),
        };
    }
}
