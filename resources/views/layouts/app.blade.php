@use('App\Support\Theme')
@use('App\Support\Nyelv')
@use('App\Support\Festival')
@php
    /*
     * A PUBLIKUS OLDAL KERETE — EGY fájl: <head>, meta, CSS, szkriptek. A fejléc, a menü és a lábléc külön
     * részekben (partials/), szintén egy-egy helyen. Minden szín, betű, forma és mozgás a publikált témából
     * jön (Theme::cssUrl); a Filament CSS-e (Tailwind preflight) IDE SOHA nem kerül.
     *
     * A lapok ezeket adhatják át: @section('title'), @section('leiras'), @section('canonical'), @section('og_kep'),
     * @section('keret', 'nelkul') — fejléc/lábléc nélküli lap (Hamarosan), @push('styles') — a lap saját CSS-e.
     */
    $ny = Nyelv::aktualis();
    $ut = '/' . ltrim(request()->path(), '/');
    $huUt = $ny === 'ro' ? (preg_replace('#^/ro(?=/|$)#', '', $ut) ?: '/') : $ut;
    // A lapozó paramétere a címhez tartozik (megosztható, nyelvváltáskor is megmarad); más paraméter nem.
    $lapozo = request()->filled('oldal') ? '?oldal=' . (int) request()->query('oldal') : '';
    $cimek = ['hu' => url($huUt) . $lapozo, 'ro' => url(Nyelv::ut($huUt, 'ro')) . $lapozo];
    $fesztNev = Festival::name($ny);
    $lapCim = trim($__env->yieldContent('title'));
    $teljesCim = $lapCim !== '' ? "{$lapCim} – {$fesztNev}" : $fesztNev . ' ' . Festival::year();
    $leiras = trim($__env->yieldContent('leiras'));
    $keret = trim($__env->yieldContent('keret')) !== 'nelkul';
@endphp
<!doctype html>
<html lang="{{ $ny }}" @if (Theme::get('motion.level') === 'minimal') data-motion="minimal" @endif>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $teljesCim }}</title>
@if ($leiras !== '')
<meta name="description" content="{{ $leiras }}">
@endif
<link rel="canonical" href="{{ trim($__env->yieldContent('canonical')) ?: $cimek[$ny] }}">
<link rel="alternate" hreflang="hu" href="{{ $cimek['hu'] }}">
<link rel="alternate" hreflang="ro" href="{{ $cimek['ro'] }}">
<link rel="alternate" hreflang="x-default" href="{{ $cimek['hu'] }}">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $teljesCim }}">
@if ($leiras !== '')
<meta property="og:description" content="{{ $leiras }}">
@endif
<meta property="og:image" content="{{ trim($__env->yieldContent('og_kep')) ?: Theme::assetUrl('logo') }}">
<meta property="og:locale" content="{{ $ny === 'ro' ? 'ro_RO' : 'hu_HU' }}">
<meta name="theme-color" content="{{ Theme::get('colors.bg.dark') }}">
<link rel="icon" type="image/png" href="{{ Theme::assetUrl('favicon') }}">
<link rel="apple-touch-icon" href="{{ Theme::assetUrl('favicon') }}">
@foreach (Theme::fontPreloads() as $betu)
<link rel="preload" href="{{ $betu }}" as="font" type="font/woff2" crossorigin>
@endforeach
<link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}?v={{ filemtime(public_path('fonts/fonts.css')) }}">
<link rel="stylesheet" href="{{ Theme::cssUrl() }}">
<link rel="stylesheet" href="{{ asset('css/publikus.css') }}?v={{ filemtime(public_path('css/publikus.css')) }}">
@stack('styles')
{{-- htmx (lapozás, szűrés) és Alpine (menü, ablak) — helyi, rögzített verziójú fájlok (package.json), CDN nélkül. --}}
<script src="{{ asset('js/vendor/htmx-2.0.4.min.js') }}" defer></script>
<script src="{{ asset('js/vendor/alpine-3.14.9.min.js') }}" defer></script>
</head>
{{-- x-data a body-n: a fejléc burgerje és a mobil menü ugyanazt az állapotot látja (a fejléc nem kerülhet
     burkoló elembe, mert a ragadós pozíció akkor a burkolóhoz tapadna). --}}
<body x-data="{ menu: false }" x-effect="document.documentElement.classList.toggle('mnav-lock', menu)" @keydown.escape.window="menu = false">
<a href="#main" class="skip-link">{{ __('publikus.ugras_tartalomra') }}</a>
@if ($hamarosanSav ?? false)
<div class="admin-sav" role="status">{{ __('publikus.hamarosan_sav') }}</div>
@endif
@if ($keret)
@include('partials.header', ['cimek' => $cimek, 'fesztNev' => $fesztNev])
@endif
<main id="main">
@yield('content')
</main>
@if ($keret)
@include('partials.footer', ['fesztNev' => $fesztNev])
@endif
@stack('scripts')
</body>
</html>
