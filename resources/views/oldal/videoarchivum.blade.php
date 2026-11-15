@extends('layouts.app')
@use('App\Support\Nyelv')
@use('App\Support\Theme')
@use('App\Support\MediaLapok')
{{--
  VIDEÓ ARCHÍVUM — a 2026-os components/VideoArchiveView.astro jelölése BETŰRE (CLAUDE.md 2/e, PROMPT-17): nyitókép, majd a
  három sáv (Aftermovie-k = világos, fekvő · Vásárhely színe-java = sötét, álló · Mesterségem = narancs, fekvő), mindegyik a
  közös csúszkán (partials/rail + rail.js), a közös csempével (MediaLapok::videoKartya) és lejátszóval (partials/video-ablak +
  video.js). A videók: Tartalom → Aloldalak → Videó archívum → videós blokkok — a SORRENDJÜK adja a sávot (MediaLapok::SAVOK).
  MINDEN látható szöveg kulcs (LapSablon), a videók címe a blokkból.
  Jelölési eltérések: a nyitókép firka-rétege [data-decor="hero"] (a téma kapcsolói hatnak), a lapon a téma 12 motívuma egyszer
  (Theme::motifSprite — 2026-ban a szkriptbe égetve).
--}}
@section('title', Nyelv::valaszt($lap->title_hu, $lap->title_ro))
@section('leiras', Nyelv::valaszt($lap->seo_description_hu, $lap->seo_description_ro))
@section('ter', 'sajat')
@push('styles')
<link rel="stylesheet" href="@eszkoz('css/videoarchivum.css')">
<link rel="stylesheet" href="@eszkoz('css/rail.css')">
<link rel="stylesheet" href="@eszkoz('css/video.css')">
<link rel="stylesheet" href="@eszkoz('css/videoarchivum-szerver.css')">
@endpush
@push('scripts')
<script src="@eszkoz('js/rail.js')"></script>
<script src="@eszkoz('js/videoarchivum.js')"></script>
<script src="@eszkoz('js/video.js')"></script>
@endpush
@php
    $savok = MediaLapok::videoSavok(Nyelv::aktualis());
@endphp

@section('content')
{!! Theme::motifSprite() !!}
<div class="va-page"><section class="subhero"><div class="hero-decor" id="heroDecor" data-decor="hero" aria-hidden="true"></div><div class="wrap reveal"><h1><span class="glow"><span data-hs="videos_hero_title">{{ $sz->szoveg('videos_hero_title') }}</span><span class="brush" data-hs="videos_hero_title_accent">{{ $sz->szoveg('videos_hero_title_accent') }}</span></span></h1></div></section>@foreach ($savok as $s)<section id="{{ $s['horgony'] }}" class="{{ $s['osztaly'] }} vidblk"><div class="wrap"><h2 class="rail-head reveal"><span data-hs="{{ $s['kulcs'] }}">{{ $sz->szoveg($s['kulcs']) }}</span><span class="brush" data-hs="{{ $s['kiemelt'] }}">{{ $sz->szoveg($s['kiemelt']) }}</span></h2>@include('partials.rail', ['id' => $s['horgony'], 'osztaly' => 'va-rail ' . $s['irany'], 'cimke' => $s['cim'],
    'tartalom' => collect($s['videok'])->map(fn ($v) => MediaLapok::videoKartya($v))->implode('')])</div></section>@endforeach</div>
@if ($savok)
@include('partials.video-ablak')
@endif
@endsection
