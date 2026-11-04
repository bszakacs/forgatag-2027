<?php

namespace App\Support;

use App\Models\Gallery;
use App\Models\Media;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Sponsor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * A KEZDŐLAP ADATAI — EGY helyen (PROMPT-14). A jelölés a 2026-os `home-v7/body.html` (resources/views/kezdolap.blade.php),
 * a 2026-os `pages/index.astro` adat-összeszedése pedig ide került, ugyanazokkal a szabályokkal:
 *  · KÉT ÁLLAPOT (tulajdonosi döntés): a Beállítások → Publikus oldal „A kezdőlap állapota” mezője dönti el, látszik-e a
 *    visszaszámláló és a „Kiemelt programok” sáv. Ma „utana” (a 2026-os mai állapot); tavasszal elég átkapcsolni.
 *  · A román kezdőlapon a hírek NEM látszanak (2026-os tulajdonosi döntés, lib/home-sections.ts: a hírek nincsenek románul).
 *  · A partner-sáv a támogatók közül a szervezőket kihagyja: a „Szervezők” szintet egészében és a két szervező egyesületet
 *    a nevük alapján (a kulcsuk: home_org_name_1/2) — így a kizárás akkor is áll, ha a szintet átnevezik.
 *  · A képek azon az arányon és méreten készülnek, amin megjelennek (egy vágás — CLAUDE.md 3.).
 */
class Kezdolap
{
    public const ALLAPOTOK = ['fesztival', 'utana'];

    /** Nyelvenként: a kezdőlap mely szekciói maradnak ki (a 2026-os REJTETT_SZEKCIOK, a „highlight” helyett már a kapcsoló). */
    public const REJTETT_SZEKCIOK = ['hu' => [], 'ro' => ['news']];

    /** A „Forgatag számokban” öt száma — a kulcsok utótagjai, a lapon ebben a sorrendben. */
    public const SZAMOK = ['programs', 'venues', 'days', 'partners', 'meetings'];

    public static function allapot(): string
    {
        $a = Settings::get('public.home_state');
        if (! in_array($a, self::ALLAPOTOK, true)) {
            throw new RuntimeException('Beállítások → Publikus oldal: ismeretlen kezdőlap-állapot: „' . var_export($a, true) . '”.');
        }

        return $a;
    }

    public static function fesztivalAllapot(): bool
    {
        return self::allapot() === 'fesztival';
    }

    public static function rejtett(string $szekcio, ?string $nyelv = null): bool
    {
        return in_array($szekcio, self::REJTETT_SZEKCIOK[$nyelv ?? Nyelv::aktualis()], true);
    }

    /** A visszaszámláló célja: a nyitónap a Beállításokban megadott órában, a fesztivál időzónájával (ISO 8601). */
    public static function visszaszamlaloCel(): string
    {
        $ido = (string) Settings::get('public.countdown_time');
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $ido)) {
            throw new RuntimeException("Beállítások → Publikus oldal: hibás visszaszámláló-időpont: „{$ido}”.");
        }

        return CarbonImmutable::parse(Festival::openingDay() . ' ' . $ido, config('app.timezone'))->toIso8601String();
    }

    /**
     * Egy „számokban” érték a szerkeszthető szövegből: a vezető szám a számláló célja, a maradék az utótag
     * („110+” → 110 és „+”). Ha nem számmal kezdődik, hibával áll meg (különben a lap némán 0-t mutatna).
     *
     * @return array{cel: int, utotag: string}
     */
    public static function szam(string $ertek, string $kulcs): array
    {
        if (! preg_match('/^\s*(\d[\d\s.\x{00A0}]*)(.*)$/u', $ertek, $m)) {
            throw new RuntimeException("Kezdőlap: a(z) „{$kulcs}” értéke számmal kezdődjön (most: „{$ertek}”).");
        }

        return ['cel' => (int) preg_replace('/\D/', '', $m[1]), 'utotag' => trim($m[2])];
    }

    /** A 3 legújabb közzétett hír (2026: a /hirek gyűjtőoldallal azonos kártya). */
    public static function hirek(): Collection
    {
        return NewsItem::query()->publikus()->with('cover')
            ->orderByDesc('published_on')->orderByDesc('id')->limit(3)->get();
    }

    /**
     * Az „Aftermoviek” sáv: a Videó archívum lap ELSŐ videós blokkja (2026-ban az `aftermovie` videócsoport; a 2026-os
     * behozatalban ez lett a lap első blokkja). A blokk nyelvenkénti rejtése itt is hat.
     *
     * @return list<array{id: string, cim: string}>
     */
    public static function videok(): array
    {
        $lap = Page::query()->where('slug', 'videoarchivum')->first();
        $blokk = $lap === null ? null : PageBlock::query()->where('page_id', $lap->id)->where('type', 'videos')
            ->orderBy('sort')->orderBy('id')->first();
        if ($blokk === null || ! $blokk->{'show_' . Nyelv::aktualis()}) {
            return [];
        }

        return collect($blokk->videos ?? [])
            ->filter(fn ($v) => filled($v['youtube_id'] ?? null))
            ->map(fn ($v) => [
                'id' => preg_replace('/[^A-Za-z0-9_-]/', '', (string) $v['youtube_id']),
                'cim' => Nyelv::valaszt($v['title_hu'] ?? null, $v['title_ro'] ?? null),
            ])->values()->all();
    }

    /**
     * Az évenkénti galériák borítója (az első kép, 3:4-re vágva — 2026: 600×800). A képek maguk a galeriaAdat()-ban
     * jönnek, KÜLÖN kérésben, csak ha a látogató megnyit egy évet (2026-ban a lapba ágyazva — telefonon ez a teljes
     * képlista minden betöltéskor, ~470 kép címe).
     *
     * @return list<array{ev: int, borito: ?string}>
     */
    public static function galeriak(): array
    {
        return Gallery::query()->with(['media' => fn ($q) => $q->limit(1)])->orderBy('sort')->orderByDesc('year')->get()
            ->filter(fn (Gallery $g) => $g->media->isNotEmpty())
            ->map(fn (Gallery $g) => [
                'ev' => (int) $g->year,
                'borito' => self::kepUrl($g->media->first(), ['w' => 600, 'h' => 800, 'fit' => 'crop', 'q' => 72]),
            ])->values()->all();
    }

    /**
     * A galéria-ablak adata évenként (2026: a #gallery-data JSON): a kiadás felirata és MINDEN kép — a hasábos
     * bélyegkép (t, 760 széles) és a nagy kép (f, 1600 széles, a valódi méretével).
     *
     * @return array<int, array{edition: string, credit: ?string, images: list<array{t: string, f: string, w: ?int, h: ?int}>}>
     */
    public static function galeriaAdat(string $nyelv): array
    {
        $adat = [];
        foreach (Gallery::query()->with('media')->orderBy('sort')->orderByDesc('year')->get() as $g) {
            if ($g->media->isEmpty()) {
                continue;
            }
            $adat[(int) $g->year] = [
                'edition' => self::kiadas((int) $g->year, $nyelv),
                'credit' => $g->credit,
                'images' => $g->media->map(function (Media $m) {
                    [$w, $h] = self::meretben($m, 1600, null);

                    return [
                        't' => self::kepUrl($m, ['w' => 760, 'q' => 72]),
                        'f' => self::kepUrl($m, ['w' => 1600, 'q' => 78]),
                        'w' => $w,
                        'h' => $h,
                    ];
                })->values()->all(),
            ];
        }

        return $adat;
    }

    /** A Forgatag kiadás-sorszáma az évből (2013 = 1., a 2020-as év kimaradt) — 2026: lib/gallery editionLabel. */
    public static function kiadas(int $ev, string $nyelv): string
    {
        $sorszam = $ev - 2013 + 1 - ($ev > 2020 ? 1 : 0);

        return $nyelv === 'ro' ? "Ediția a {$sorszam}-a" : "{$sorszam}. Vásárhelyi Forgatag";
    }

    /**
     * A partner-sáv: a legutóbbi év támogatói (ma a 2026-osak — 2027-es még nincs; amint felkerül, az jön), szint és
     * sorrend szerint, a szervezők nélkül. A logó a csempe dobozába arányosan (2026: 360×192, „inside”).
     *
     * @param  list<string>  $szervezoNevek
     * @return list<array{nev: string, url: ?string, logo: ?array{src: string, w: int, h: int}}>
     */
    public static function partnerek(array $szervezoNevek): array
    {
        $ev = Sponsor::query()->max('year');
        if ($ev === null) {
            return [];
        }
        $kizart = array_map(self::normal(...), $szervezoNevek);

        return Sponsor::query()->with(['tier', 'logo'])->where('year', $ev)->get()
            ->filter(fn (Sponsor $s) => ! preg_match('/^szervez/iu', (string) $s->tier?->name))
            ->reject(fn (Sponsor $s) => in_array(self::normal($s->name), $kizart, true))
            ->sortBy([fn ($a, $b) => ($a->tier?->sort ?? PHP_INT_MAX) <=> ($b->tier?->sort ?? PHP_INT_MAX), ['sort', 'asc'], ['id', 'asc']])
            ->map(function (Sponsor $s) {
                $logo = null;
                if ($s->logo !== null) {
                    [$w, $h] = self::meretben($s->logo, 360, 192);
                    $logo = ['src' => self::kepUrl($s->logo, ['w' => 360, 'h' => 192, 'fit' => 'max', 'q' => 82]), 'w' => $w ?? 360, 'h' => $h ?? 192];
                }

                return ['nev' => $s->name, 'url' => $s->url ?: null, 'logo' => $logo];
            })->values()->all();
    }

    /** Médiatár-kép címe a megjelenítés méretén (Glide, webp). */
    public static function kepUrl(?Media $m, array $p): ?string
    {
        return $m === null ? null : url($m->getGlideUrl($p + ['fm' => 'webp']));
    }

    /**
     * A kimeneti méret, ha a kép arányosan befér a dobozba (nagyítás nélkül) — a width/height attribútumhoz.
     *
     * @return array{0: ?int, 1: ?int}
     */
    public static function meretben(Media $m, int $maxW, ?int $maxH): array
    {
        if (! $m->width || ! $m->height) {
            return [null, null];
        }
        $s = min(1, $maxW / $m->width, $maxH ? $maxH / $m->height : 1);

        return [(int) round($m->width * $s), (int) round($m->height * $s)];
    }

    /** Név-összevetéshez: kisbetű, ékezet és írásjel nélkül (2026: index.astro norm()). */
    private static function normal(string $s): string
    {
        $s = \Normalizer::normalize(mb_strtolower($s), \Normalizer::FORM_D) ?: mb_strtolower($s);
        $s = preg_replace('/\p{Mn}+/u', '', $s);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
    }
}
