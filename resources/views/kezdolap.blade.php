@extends('layouts.app')
@use('App\Support\Kezdolap')
@use('App\Support\Nyelv')
@use('App\Support\Settings')
@use('App\Support\Theme')
@php
    /**
     * A KEZDŐLAP (PROMPT-14) — a 2026-os `home-v7/body.html` jelölése BETŰRE (CLAUDE.md 2/e): ugyanazok az osztályok,
     * ugyanabban a sorrendben, a `data-hs`/`data-img` kulcsokkal (a szövegek és képek: Tartalom → Aloldalak → Kezdőlap;
     * App\Support\LapSablon 'home'). A stílus a 2026-os style.css (public/css/kezdolap.css), a közös modulok a saját
     * fájljukban (galeria, video, tamogatok, kezdolap-kartyak). Ami a 2026-os build a vázba írt (hírek, galéria-sáv,
     * aftermovie-sáv, partner-sáv), azt itt a szerver rajzolja, a 2026-os modulok jelölésével (lib/news, lib/gallery,
     * lib/video, lib/sponsor-tile + lib/rail).
     *
     * KÉT ÁLLAPOT, EGY KÓD (tulajdonosi döntés): $fesztival (Beállítások → Publikus oldal) — a visszaszámláló és a
     * „Kiemelt programok” sáv csak ekkor kerül a lapra. A fesztivál előtti jelölés a 2026-08-26 előtti body.html (git 122d8b3^).
     *
     * Ami a 2026-os mai kezdőlapról SZÁNDÉKOSAN hiányzik (a v7 landing átvételekor esett ki): canvas fény-részecskék,
     * „Pörgess egy programot!” sorsoló, futó névsor, aurora-réteg — nem jönnek vissza (PROMPT-14, 3.).
     *
     * Teljesítmény (telefon az első nézet): a nyitókép első diája <picture> (mobilon a kijelző arányára vágott, kisebb kép),
     * a többi dia csak a lap betöltése után töltődik; minden más kép `loading="lazy"`; a galéria képlistája külön kérés.
     */
    $ny = Nyelv::aktualis();
    $kep = fn (string $kulcs, array $p) => Kezdolap::kepUrl($sz->kep($kulcs), $p + ['q' => 76]);
    $diak = collect(range(1, 5))->map(fn ($i) => $sz->kep("home_hero_slide_{$i}"))->filter()->values();
    $szam = fn (string $k) => Kezdolap::szam($sz->szoveg("home_stats_value_{$k}"), "home_stats_value_{$k}");
    $ezres = fn (int $n) => number_format($n, 0, '', "\u{00A0}");
    $fenyek = ['var(--doodle-pink)', 'var(--doodle-lime)', 'var(--doodle-yellow)', 'var(--doodle-blue)', 'var(--doodle-purple)',
        'color-mix(in srgb,var(--doodle-red) 70%,#fff)', 'var(--accent-a)'];
    $nyilVissza = '<svg viewBox="0 0 46 34" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M24 8 L14 17 L24 26"/><path d="M36 8 L26 17 L36 26"/><circle cx="9" cy="17" r="2.4" fill="var(--accent-a)" stroke="none"/></svg>';
    $nyilTovabb = '<svg viewBox="0 0 46 34" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 8 L32 17 L22 26"/><path d="M10 8 L20 17 L10 26"/><circle cx="37" cy="17" r="2.4" fill="var(--accent-a)" stroke="none"/></svg>';
    $gomb = '<svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="14" class="mk-lime" fill="none" stroke-width="6"/><circle cx="20" cy="20" r="5" class="mk-yellow"/></svg>';
    if ($fesztival) {
        $cel = \Carbon\CarbonImmutable::parse(Kezdolap::visszaszamlaloCel());
        $hatra = max(0, $cel->getTimestamp() - now()->getTimestamp());
    }
@endphp
@section('leiras', Nyelv::valaszt($lap->seo_description_hu, $lap->seo_description_ro) ?: Nyelv::valaszt(Settings::get('public.footer_text_hu'), Settings::get('public.footer_text_ro')))
@section('canonical', url(Nyelv::ut('/')))
@section('ter', 'sajat')
@push('styles')
@if ($diak->isNotEmpty())
{{-- Az első dia a lap legnagyobb képe (LCP): a böngésző már a fej olvasásakor kérje, a kijelzőhöz illő változatot. --}}
<link rel="preload" as="image" href="{{ $kep('home_hero_slide_1', ['w' => 750, 'h' => 1400, 'fit' => 'crop', 'q' => 72]) }}" media="(max-width:720px)" fetchpriority="high">
<link rel="preload" as="image" href="{{ $kep('home_hero_slide_1', ['w' => 1920]) }}" media="(min-width:721px)" fetchpriority="high">
@endif
{{-- A SORREND a 2026-os lapé (index.astro styleTag): a landing, utána a közös modulok — az azonos erősségű szabályok közül a
     későbbi nyer (pl. a csúszka 16 px-es térköze a landing 4 px-e fölött). A 2027-es kiegészítés a végén. --}}
<link rel="stylesheet" href="@eszkoz('css/kezdolap.css')">
<link rel="stylesheet" href="@eszkoz('css/galeria.css')">
<link rel="stylesheet" href="@eszkoz('css/rail.css')">
<link rel="stylesheet" href="@eszkoz('css/video.css')">
<link rel="stylesheet" href="@eszkoz('css/kezdolap-kartyak.css')">
<link rel="stylesheet" href="@eszkoz('css/tamogatok.css')">
<link rel="stylesheet" href="@eszkoz('css/kezdolap-szerver.css')">
@endpush
@push('scripts')
<script src="@eszkoz('js/rail.js')"></script>
<script src="@eszkoz('js/kezdolap.js')"></script>
<script src="@eszkoz('js/galeria.js')"></script>
<script src="@eszkoz('js/video.js')"></script>
@endpush

@section('content')
{!! Theme::motifSprite() !!}
<div class="fgl-atmo" aria-hidden="true"></div>

<!-- =========================== HERO =========================== -->
<section class="hero" data-section="hero" data-allapot="{{ $fesztival ? 'fesztival' : 'utana' }}">
  <div class="slides" id="slides" data-slot="hero-slides">
    @foreach ($diak as $i => $dia)
      @if ($i === 0)
        <div class="slide on" data-img="home_hero_slide_{{ $i + 1 }}"><picture>
          <source media="(max-width:720px)" srcset="{{ Kezdolap::kepUrl($dia, ['w' => 750, 'h' => 1400, 'fit' => 'crop', 'q' => 72]) }}" width="750" height="1400">
          <img class="kep-kitolt" src="{{ Kezdolap::kepUrl($dia, ['w' => 1920, 'q' => 76]) }}" alt="" fetchpriority="high" decoding="async">
        </picture></div>
      @else
        <div class="slide" data-img="home_hero_slide_{{ $i + 1 }}" data-kep="{{ Kezdolap::kepUrl($dia, ['w' => 1920, 'q' => 76]) }}"
             data-kep-mobil="{{ Kezdolap::kepUrl($dia, ['w' => 750, 'h' => 1400, 'fit' => 'crop', 'q' => 72]) }}"></div>
      @endif
    @endforeach
  </div>

  <div class="decor-layer" id="heroDecor" data-decor="hero" aria-hidden="true"></div>

  <div class="hero-inner wrap reveal">
    <div class="hero-blob">
      <h1 class="hero-title"><span class="accent" data-hs="home_hero_title_a">{{ $sz->szoveg('home_hero_title_a') }}</span><span class="l2" data-hs="home_hero_title_b">{{ $sz->szoveg('home_hero_title_b') }}</span></h1>
    </div>

    @if ($fesztival)
    <!-- a kezdő időpont rendszer-adat (Beállítások: nyitónap + óra): data-comp="countdown" + data-start -->
    <div class="hero-countdown" data-comp="countdown" data-start="{{ $cel->toIso8601String() }}"
         aria-label="{{ __('publikus.kp_visszaszamlalo') }}">
      <span class="hcd-label" data-hs="home_hero_countdown_label">{{ $sz->szoveg('home_hero_countdown_label') }}</span>
      <div class="hcd-units">
        <div class="hcd-unit"><b data-cd="days">{{ intdiv($hatra, 86400) }}</b><span data-hs="home_hero_cd_days">{{ $sz->szoveg('home_hero_cd_days') }}</span></div>
        <div class="hcd-unit"><b data-cd="hours">{{ intdiv($hatra % 86400, 3600) }}</b><span data-hs="home_hero_cd_hours">{{ $sz->szoveg('home_hero_cd_hours') }}</span></div>
        <div class="hcd-unit"><b data-cd="mins">{{ intdiv($hatra % 3600, 60) }}</b><span data-hs="home_hero_cd_mins">{{ $sz->szoveg('home_hero_cd_mins') }}</span></div>
      </div>
      <span class="hcd-foot" data-hs="home_hero_countdown_foot">{{ $sz->szoveg('home_hero_countdown_foot') }}</span>
    </div>
    @endif
  </div>

  <div class="dots" id="dots"></div>
</section>

<div class="fgl-decor" id="pageDecor" data-decor="ambient" aria-hidden="true"></div>

@if ($fesztival)
<!-- ====================== KIEMELT PROGRAMOK ====================== -->
<section class="blk" data-section="highlight">
  <div class="highlight-band reveal">
    <div class="sec-decor" id="bandDecor" data-decor="ambient" aria-hidden="true"></div>
    <div class="wrap">
      <div class="sec-head center">
        <div><h2><span data-hs="home_highlight_title_a">{{ $sz->szoveg('home_highlight_title_a') }}</span> <span class="brush" data-hs="home_highlight_title_b">{{ $sz->szoveg('home_highlight_title_b') }}</span></h2></div>
      </div>
      <div class="rail rail-highlight" data-rail="highlight" data-slot="programs" data-programs="highlight"
           role="group" aria-label="Kiemelt programok">
        @foreach (range(1, 6) as $i)
          @php $hk = $kep("home_highlight_{$i}_img", ['w' => 720, 'h' => 450, 'fit' => 'crop']); @endphp
          <article class="pcard"><div class="thumb" data-img="home_highlight_{{ $i }}_img">@if ($hk)<img class="kep-kitolt" src="{{ $hk }}" alt="" width="720" height="450" loading="lazy" decoding="async">@endif</div><div class="body"><h3><span data-hs="home_highlight_{{ $i }}_title">{{ $sz->szoveg("home_highlight_{$i}_title") }}</span></h3></div></article>
        @endforeach
      </div>
      <!-- csúszka: ha nincs mit görgetni (pl. desktopon a hírek rács), a JS elrejti -->
      <div class="railnav" data-railnav="highlight">
        <button class="railarrow railprev" type="button" aria-label="Kiemelt programok — vissza">{!! $nyilVissza !!}</button>
        <div class="railline"><div class="railknob" role="slider" tabindex="0" aria-label="Kiemelt programok görgetése" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">{!! $gomb !!}</div></div>
        <button class="railarrow railnext" type="button" aria-label="Kiemelt programok — tovább">{!! $nyilTovabb !!}</button>
      </div>

    </div>
  </div>
</section>
@endif

@unless (Kezdolap::rejtett('news'))
<!-- ============================ HÍREK ============================ -->
<section class="blk" data-section="news">
  <div class="wrap">
    <div class="sec-head reveal">
      <div><h2><span data-hs="home_news_title_a">{{ $sz->szoveg('home_news_title_a') }}</span> <span class="brush" data-hs="home_news_title_b">{{ $sz->szoveg('home_news_title_b') }}</span></h2></div>
    </div>
    <div class="grid3 reveal" data-rail="news" data-slot="news">
      @foreach ($hirek as $hir)
        <a class="ncard" href="{{ Nyelv::ut('/hirek/' . $hir->slug) }}"><div class="thumb"@if (! $hir->cover) data-ph @endif>@if ($hir->cover)<img src="{{ Kezdolap::kepUrl($hir->cover, ['w' => 800, 'h' => 800, 'fit' => 'crop', 'q' => 72]) }}" alt="" width="800" height="800" loading="lazy" decoding="async">@endif</div><div class="body">@if ($hir->published_on)<span class="date">{{ Nyelv::datum($hir->published_on) }}</span>@endif<h3>{{ Nyelv::valaszt($hir->title_hu, $hir->title_ro) }}</h3><p>{{ Nyelv::valaszt($hir->excerpt_hu, $hir->excerpt_ro) }}</p></div></a>
      @endforeach
    </div>
    <!-- csúszka: ha nincs mit görgetni (pl. desktopon a hírek rács), a JS elrejti -->
    <div class="railnav" data-railnav="news">
      <button class="railarrow railprev" type="button" aria-label="Hírek — vissza">{!! $nyilVissza !!}</button>
      <div class="railline"><div class="railknob" role="slider" tabindex="0" aria-label="Hírek görgetése" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">{!! $gomb !!}</div></div>
      <button class="railarrow railnext" type="button" aria-label="Hírek — tovább">{!! $nyilTovabb !!}</button>
    </div>
    <div class="sec-cta reveal"><a href="{{ Nyelv::ut('/hirek') }}" class="btn btn-primary"><span data-hs="home_news_cta">{{ $sz->szoveg('home_news_cta') }}</span> →</a></div>
  </div>
</section>
@endunless

<!-- ==================== FORGATAG SZÁMOKBAN ==================== -->
<section class="blk light" data-section="numbers">
  <div class="wrap">
    <div class="sec-head reveal">
      <div><h2><span data-hs="home_numbers_title_a">{{ $sz->szoveg('home_numbers_title_a') }}</span> <span class="brush" data-hs="home_numbers_title_b">{{ $sz->szoveg('home_numbers_title_b') }}</span></h2></div>
    </div>
    {{-- A számok (2026: data-target a sablonban) most szerkeszthetők: home_stats_value_*. A végérték JS nélkül is látszik;
         a számlálás 0-ról indul, amikor a sáv a képernyőre ér. --}}
    <div class="numbers reveal" id="numbers" data-slot="stats">
      @foreach (Kezdolap::SZAMOK as $k)
        @php $s = $szam($k); @endphp
        <div class="num"><b data-target="{{ $s['cel'] }}" data-suffix="{{ $s['utotag'] }}">{{ $ezres($s['cel']) . $s['utotag'] }}</b><span data-hs="home_stats_label_{{ $k }}">{{ $sz->szoveg("home_stats_label_{$k}") }}</span></div>
      @endforeach
    </div>
    <div class="sec-cta reveal"><a href="{{ Nyelv::ut('/rolunk') }}" class="btn btn-primary"><span data-hs="home_numbers_cta">{{ $sz->szoveg('home_numbers_cta') }}</span> →</a></div>
  </div>
</section>

<!-- ================= A FORGATAG KÉPEKBEN (média) ================= -->
<section class="blk purple" data-section="media">
  <div class="sec-decor" id="mediaDecor" data-decor="ambient" aria-hidden="true"></div>
  <div class="wrap">
    <div class="sec-head reveal">
      <div><h2><span data-hs="home_media_title_a">{{ $sz->szoveg('home_media_title_a') }}</span> <span class="brush" data-hs="home_media_title_b">{{ $sz->szoveg('home_media_title_b') }}</span></h2></div>
    </div>

    @if ($videok)
    <h3 class="rail-head reveal" data-hs="home_media_videos_title">{{ $sz->szoveg('home_media_videos_title') }}</h3>
    {{-- 2026: lib/video videoArchiveCard + renderRail (a YouTube-borító maxres → sd → hq tartalékkal). --}}
    @include('partials.rail', ['id' => 'vid-aftermovie', 'osztaly' => 'va-rail land', 'cimke' => 'Aftermovie-k', 'tartalom' => collect($videok)->map(fn ($v) =>
        '<article class="va-card"><button class="va-thumb" type="button" data-yt="' . $v['id'] . '" aria-label="' . e(__('publikus.kp_video_lejatszasa') . ($v['cim'] !== '' ? ': ' . $v['cim'] : '')) . '">'
        . '<img class="va-img" src="https://i.ytimg.com/vi/' . $v['id'] . '/maxresdefault.jpg" alt="" loading="lazy" '
        . 'onload="if(this.naturalWidth&&this.naturalWidth<=120){var s=(+this.dataset.s||0);if(s<2){s++;this.dataset.s=s;this.src=\'https://i.ytimg.com/vi/' . $v['id'] . '/\'+[\'maxresdefault\',\'sddefault\',\'hqdefault\'][s]+\'.jpg\';}}" '
        . 'onerror="var s=(+this.dataset.s||0);if(s<2){s++;this.dataset.s=s;this.src=\'https://i.ytimg.com/vi/' . $v['id'] . '/\'+[\'maxresdefault\',\'sddefault\',\'hqdefault\'][s]+\'.jpg\';}else{this.onerror=null;}">'
        . '<span class="va-play"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4.5 L20 12 L7 19.5 Z"/></svg></span></button>'
        . ($v['cim'] !== '' ? '<h3 class="va-title">' . e($v['cim']) . '</h3>' : '') . '</article>')->implode('')])
    @endif

    @if ($galeriak)
    <h3 class="rail-head spaced reveal" data-hs="home_media_gallery_title">{{ $sz->szoveg('home_media_gallery_title') }}</h3>
    {{-- 2026: lib/gallery renderGalleryRail — az év-borítóra kattintva a galéria-ablak nyílik (public/js/galeria.js). --}}
    @include('partials.rail', ['id' => 'galEvek', 'osztaly' => 'hp-gal-rail', 'cimke' => 'Galéria — évek', 'tartalom' => collect($galeriak)->values()->map(fn ($g, $i) =>
        '<button class="gal-card" type="button" data-year="' . $g['ev'] . '" style="--glow:' . $fenyek[$i % count($fenyek)] . '" aria-label="' . $g['ev'] . ' — galéria">'
        . '<span class="gal-card__img">' . ($g['borito'] ? '<img class="kep-kitolt" src="' . e($g['borito']) . '" alt="" width="600" height="800" loading="lazy" decoding="async">' : '')
        . '<span class="gal-meta"><b>' . $g['ev'] . '</b></span></span></button>')->implode('')])
    @endif

    <div class="media-cta reveal">
      <a href="{{ Nyelv::ut('/videoarchivum') }}#mestersegem-vasarhelyi" class="btn btn-fill orange" data-hs="home_media_cta_mestersegem">{{ $sz->szoveg('home_media_cta_mestersegem') }}</a>
      <a href="{{ Nyelv::ut('/videoarchivum') }}#vasarhely-szine-java" class="btn btn-fill lime" data-hs="home_media_cta_szinejava">{{ $sz->szoveg('home_media_cta_szinejava') }}</a>
    </div>
  </div>
</section>

<!-- ========================== SZERVEZŐK ========================== -->
<section class="blk white" data-section="organizers">
  <div class="edge-decor" id="orgDecor" data-decor="ambient" aria-hidden="true"></div>
  <div class="wrap">
    <div class="sec-head center reveal">
      <div><h2><span data-hs="home_organizers_title_a">{{ $sz->szoveg('home_organizers_title_a') }}</span> <span class="brush" data-hs="home_organizers_title_b">{{ $sz->szoveg('home_organizers_title_b') }}</span></h2></div>
    </div>
    <h3 class="rail-head reveal" data-hs="home_organizers_rail_title">{{ $sz->szoveg('home_organizers_rail_title') }}</h3>
    <div class="org-row big center reveal" data-slot="organizers">
      @foreach ([1, 2] as $i)
        @if ($ol = $sz->kep("home_org_logo_{$i}"))
          @php [$ow, $oh] = Kezdolap::meretben($ol, 580, 344); @endphp
          <div class="org nocard"><img data-img="home_org_logo_{{ $i }}" src="{{ $kep("home_org_logo_{$i}", ['w' => 580, 'h' => 344, 'fit' => 'max', 'q' => 82]) }}" alt="{{ $sz->szoveg("home_org_name_{$i}") }}"@if ($ow) width="{{ $ow }}" height="{{ $oh }}"@endif fetchpriority="low" decoding="async"></div>
          {{-- NEM lusta: a 2026-os CSS (width:auto) a még be nem töltött képnek 0×0-s dobozt ad, és a böngésző az ilyen lusta
               képet soha nem kéri le (mérve). A két kis logó alacsony elsőbbséggel jön, a nyitókép után. --}}
        @endif
      @endforeach
    </div>
    {{-- PARTNEREK ÉS TÁMOGATÓK — mozgó logósáv (2026-08-26). A logók a Támogatók közül, szint és sorrend szerint; a
         két szervező egyesület kimarad (App\Support\Kezdolap::partnerek). Ha nincs támogató, az EGÉSZ blokk kimarad. --}}
    @if ($partnerek)
    <div class="sp-rail-blk reveal" data-slot="sponsor-rail">
      <h3 class="rail-head" data-hs="home_sponsors_rail_title">{{ $sz->szoveg('home_sponsors_rail_title') }}</h3>
      @include('partials.rail', ['id' => 'sponsorRail', 'osztaly' => 'hp-sponsor-rail', 'auto' => true, 'cimke' => $sz->szoveg('home_sponsors_rail_title'),
        'tartalom' => collect($partnerek)->map(function ($p) {
            $belso = $p['logo']
                ? '<img src="' . e($p['logo']['src']) . '" alt="' . e($p['nev']) . '" width="' . $p['logo']['w'] . '" height="' . $p['logo']['h'] . '" loading="lazy" decoding="async" />'
                : '<span class="tmg-name">' . e($p['nev']) . '</span>';

            return $p['url'] ? '<a class="tmg-item" href="' . e($p['url']) . '" target="_blank" rel="noopener noreferrer">' . $belso . '</a>' : '<div class="tmg-item">' . $belso . '</div>';
        })->implode('')])
    </div>
    @endif
    <div class="center-cta reveal"><a href="{{ Nyelv::ut('/rolunk') }}" class="btn btn-primary"><span data-hs="home_organizers_cta">{{ $sz->szoveg('home_organizers_cta') }}</span> →</a></div>
  </div>
</section>

@if ($videok)
{{-- 2026: lib/video VIDEO_LIGHTBOX_HTML — a lejátszó csak kattintásra töltődik (youtube-nocookie). --}}
<div class="vid-lb" id="vidLb" hidden aria-hidden="true" role="dialog" aria-modal="true">
  <button class="vid-lb-close" id="vidLbClose" aria-label="{{ __('publikus.bezaras') }}">✕</button>
  <div class="vid-lb-frame" id="vidLbFrame"></div>
</div>
@endif
@if ($galeriak)
@include('partials.galeria-ablak')
@endif
@endsection
