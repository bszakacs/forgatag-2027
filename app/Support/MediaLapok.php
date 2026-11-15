<?php

namespace App\Support;

use App\Models\Gallery;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Sponsor;

/**
 * A maradék lapok 2. részének adata (PROMPT-17): Galéria, Videó archívum, Támogatók — EGY helyen. A 2026-os
 * GalleryView / VideoArchiveView / SponsorsView számolásai betűre; a képek a médiatárból (Glide, webp).
 * A galéria-ablak képlistája és a kiadás-felirat a kezdőlappal KÖZÖS (Kezdolap::galeriaAdat, /galeria-adat.json).
 */
class MediaLapok
{
    /**
     * A Galéria lap év-rácsa: minden év a borítójával (az első kép, 800 széles — 2026: getImage width 800), legújabb elöl.
     *
     * @return list<array{ev: int, borito: ?string}>
     */
    public static function galeriaEvek(): array
    {
        return Gallery::query()->with(['media' => fn ($q) => $q->limit(1)])->orderByDesc('year')->get()
            ->filter(fn (Gallery $g) => $g->media->isNotEmpty())
            ->map(fn (Gallery $g) => ['ev' => (int) $g->year, 'borito' => Kezdolap::kepUrl($g->media->first(), ['w' => 800, 'q' => 72])])
            ->values()->all();
    }

    /**
     * A Videó archívum három sávja — 2026-ban három RÖGZÍTETT sáv volt (a kód a csoport slugja szerint rajzolta őket, saját
     * kinézettel és horgonnyal). 2027-ben a videók a lap videós blokkjaiban élnek (Tartalom → Aloldalak → Videó archívum), és
     * a SORRENDJÜK adja a sávot: 1. = Aftermovie-k (világos, fekvő), 2. = Vásárhely színe-java (sötét, álló), 3. = Mesterségem
     * (narancs, fekvő). A horgonyok (#…) a 2026-osak — a kezdőlap gombjai ezekre visznek. A nyelvenkénti rejtés hat (2026: a
     * két sorozat románul nem látszott). A harmadik utáni videós blokkot a lap nem mutatja (2026-ban sem volt negyedik sáv) —
     * erről a nyitólap figyelmeztet (tobbletBlokkok).
     */
    public const SAVOK = [
        ['horgony' => 'aftermovie', 'osztaly' => 'blk light', 'irany' => 'land', 'kulcs' => 'videos_aftermovie_title', 'kiemelt' => 'videos_aftermovie_title_accent'],
        ['horgony' => 'vasarhely-szine-java', 'osztaly' => 'blk', 'irany' => 'port', 'kulcs' => 'videos_szinejava_title', 'kiemelt' => 'videos_szinejava_title_accent'],
        ['horgony' => 'mestersegem-vasarhelyi', 'osztaly' => 'blk orange', 'irany' => 'land', 'kulcs' => 'videos_mestersegem_title', 'kiemelt' => 'videos_mestersegem_title_accent'],
    ];

    /** @return list<array{horgony: string, osztaly: string, irany: string, kulcs: string, kiemelt: string, cim: string, videok: list<array{id: string, cim: string}>}> */
    public static function videoSavok(string $nyelv): array
    {
        $blokkok = self::videosBlokkok();
        $ki = [];
        foreach (self::SAVOK as $i => $sav) {
            $b = $blokkok[$i] ?? null;
            if ($b === null || ! $b->{"show_{$nyelv}"}) {
                continue;
            }
            $videok = collect($b->videos ?? [])->filter(fn ($v) => filled($v['youtube_id'] ?? null))
                ->map(fn ($v) => [
                    'id' => preg_replace('/[^A-Za-z0-9_-]/', '', (string) $v['youtube_id']),
                    'cim' => trim((string) Nyelv::valaszt($v['title_hu'] ?? null, $v['title_ro'] ?? null)),
                ])->values()->all();
            if ($videok === []) {
                continue; // 2026: üres csoport → nincs sáv
            }
            $ki[] = $sav + ['cim' => (string) Nyelv::valaszt($b->title_hu, $b->title_ro), 'videok' => $videok];
        }

        return $ki;
    }

    /**
     * Egy videó csempéje — a 2026-os lib/video videoArchiveCard BETŰRE (a kezdőlap aftermovie-sávja és a Videó archívum közös):
     * YouTube-borító a maxres → sd → hq tartalékkal, lejátszás-jel; a kattintást a közös video.js ([data-yt]) kezeli.
     *
     * @param  array{id: string, cim: string}  $v
     */
    public static function videoKartya(array $v): string
    {
        $id = $v['id'];
        $lepes = "var s=(+this.dataset.s||0);if(s<2){s++;this.dataset.s=s;this.src='https://i.ytimg.com/vi/{$id}/'+['maxresdefault','sddefault','hqdefault'][s]+'.jpg';}";

        return '<article class="va-card"><button class="va-thumb" type="button" data-yt="' . $id . '" aria-label="'
            . e(__('publikus.kp_video_lejatszasa') . ($v['cim'] !== '' ? ': ' . $v['cim'] : '')) . '">'
            . '<img class="va-img" src="https://i.ytimg.com/vi/' . $id . '/maxresdefault.jpg" alt="" loading="lazy" '
            . 'onload="if(this.naturalWidth&&this.naturalWidth<=120){' . $lepes . '}" '
            . 'onerror="' . $lepes . 'else{this.onerror=null;}">'
            . '<span class="va-play"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4.5 L20 12 L7 19.5 Z"/></svg></span></button>'
            . ($v['cim'] !== '' ? '<h3 class="va-title">' . e($v['cim']) . '</h3>' : '') . '</article>';
    }

    /**
     * Egy támogató csempéje — a 2026-os lib/sponsor-tile sponsorTile BETŰRE (a Támogatók lap és a kezdőlapi partner-sáv közös):
     * logóval vagy — logó nélkül — a nevével; URL nélkül nem link.
     *
     * @param  array{nev: string, url: ?string, logo: ?array{src: string, w: int, h: int}}  $p
     */
    public static function tamogatoCsempe(array $p): string
    {
        $belso = $p['logo']
            ? '<img src="' . e($p['logo']['src']) . '" alt="' . e($p['nev']) . '" width="' . $p['logo']['w'] . '" height="' . $p['logo']['h'] . '" loading="lazy" decoding="async" />'
            : '<span class="tmg-name">' . e($p['nev']) . '</span>';

        return $p['url'] ? '<a class="tmg-item" href="' . e($p['url']) . '" target="_blank" rel="noopener noreferrer">' . $belso . '</a>' : '<div class="tmg-item">' . $belso . '</div>';
    }

    /** A Videó archívum lap harmadik utáni videós blokkjai (a lap nem mutatja őket — a nyitólap figyelmeztet). */
    public static function tobbletBlokkok(): int
    {
        return max(0, count(self::videosBlokkok()) - count(self::SAVOK));
    }

    /** @return list<PageBlock> */
    private static function videosBlokkok(): array
    {
        $lap = Page::query()->where('slug', 'videoarchivum')->first();

        return $lap === null ? [] : PageBlock::query()->where('page_id', $lap->id)->where('type', 'videos')
            ->orderBy('sort')->orderBy('id')->get()->all();
    }

    /** A csempe logó-doboza szintméretenként (2026: LOGO_BOX — a CSS-csempe kétszerese, retina; „inside”). */
    public const LOGO_DOBOZ = ['is-xl' => [840, 400], 'is-lg' => [640, 320], 'is-md' => [500, 276], 'is-sm' => [400, 240]];

    /**
     * A Támogatók lap: a LEGUTÓBBI év támogatói (2026-ban a támogatók nem voltak évhez kötve — most ma a 2026-osak; amint
     * 2027-es kerül fel, az jön), az adott év szintjei szerint, a szint sorrendjében. Üres szint nem jelenik meg. A csempe
     * mérete a (nem üres) szint HELYÉBŐL jön: 1. → is-xl, 2. → is-lg, 3. → is-md, a többi → is-sm (2026: sizeClass).
     *
     * @return list<array{nev: string, osztaly: string, tamogatok: list<array{nev: string, url: ?string, logo: ?array{src: string, w: int, h: int}}>}>
     */
    public static function tamogatoSzintek(string $nyelv): array
    {
        $ev = Sponsor::query()->max('year');
        if ($ev === null) {
            return [];
        }
        $szintek = Sponsor::query()->with(['tier', 'logo'])->where('year', $ev)->whereNotNull('sponsor_tier_id')->get()
            ->groupBy('sponsor_tier_id')
            ->sortBy(fn ($csoport) => [$csoport->first()->tier->sort, $csoport->first()->tier->id])
            ->values();

        return $szintek->map(function ($csoport, $i) use ($nyelv) {
            $osztaly = ['is-xl', 'is-lg', 'is-md'][$i] ?? 'is-sm';
            [$bw, $bh] = self::LOGO_DOBOZ[$osztaly];
            $tier = $csoport->first()->tier;

            return [
                'nev' => $nyelv === 'ro' && filled($tier->name_ro) ? $tier->name_ro : $tier->name,
                'osztaly' => $osztaly,
                'tamogatok' => $csoport->sortBy([['sort', 'asc'], ['id', 'asc']])->map(function (Sponsor $s) use ($bw, $bh) {
                    $logo = null;
                    if ($s->logo !== null && ! $s->logo->tulNagy()) { // túl nagy logóból nincs változat → a név látszik (üres doboz helyett)
                        [$w, $h] = Kezdolap::meretben($s->logo, $bw, $bh);
                        $logo = ['src' => Kezdolap::kepUrl($s->logo, ['w' => $bw, 'h' => $bh, 'fit' => 'max', 'q' => 82]), 'w' => $w ?? $bw, 'h' => $h ?? $bh];
                    }

                    return ['nev' => $s->name, 'url' => $s->url ?: null, 'logo' => $logo];
                })->values()->all(),
            ];
        })->all();
    }
}
