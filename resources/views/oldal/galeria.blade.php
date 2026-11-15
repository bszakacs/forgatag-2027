@extends('layouts.app')
@use('App\Support\Nyelv')
@use('App\Support\Kezdolap')
@use('App\Support\MediaLapok')
{{--
  GALÉRIA — a 2026-os components/GalleryView.astro jelölése BETŰRE (CLAUDE.md 2/e, PROMPT-17), a 2026-os Base keretben
  (alap.css): fejléc, év-rács (2026: lib/gallery renderYearGrid), a galéria-ablak (2. szint: az év képei hasábokban, 3. szint:
  egy kép teljes méretben). Az ablak és a szkriptje a kezdőlappal KÖZÖS (partials/galeria-ablak + galeria.js + galeria.css).
  MINDEN látható szöveg kulcs: Tartalom → Aloldalak → Galéria; a galériák maguk: Tartalom → Galériák.
  Egyetlen eltérés: az évek képlistája nem a lapba ágyazva jön (2026: #gallery-data, ~470 kép címe minden betöltéskor), hanem
  külön kérésben, az első megnyitáskor (/galeria-adat.json — ugyanaz, mint a kezdőlapon).
--}}
@section('title', Nyelv::valaszt($lap->title_hu, $lap->title_ro))
@section('leiras', Nyelv::valaszt($lap->seo_description_hu, $lap->seo_description_ro))
@include('partials.alap-fej')
@push('styles')
<link rel="stylesheet" href="@eszkoz('css/galeria-lap.css')">
<link rel="stylesheet" href="@eszkoz('css/galeria.css')">
@endpush
@push('scripts')
<script src="@eszkoz('js/galeria.js')"></script>
@endpush
@php
    $evek = MediaLapok::galeriaEvek();
    $fenyek = Kezdolap::FENYEK;
@endphp

@section('content')
  <div class="gal-page">
    <section class="page-head">
      <h1><span class="brush" data-hs="gallery_title">{{ $sz->szoveg('gallery_title') }}</span></h1>
      <p data-hs="gallery_intro">{{ $sz->szoveg('gallery_intro') }}</p>
    </section>
    @if ($evek === [])
      <p class="gal-empty">{{ $sz->szoveg('gallery_empty') }}</p>
    @else
      <div class="year-grid">@foreach ($evek as $i => $g)<button class="ycard" type="button" data-year="{{ $g['ev'] }}" style="--glow:{{ $fenyek[$i % count($fenyek)] }}" aria-label="{{ $g['ev'] }} — galéria"><span class="gy-cover"@if ($g['borito']) style="background-image:url('{{ $g['borito'] }}')"@endif></span><span class="yl"><b>{{ $g['ev'] }}</b></span></button>@endforeach</div>
    @endif
  </div>
  @if ($evek !== [])
    @include('partials.galeria-ablak')
  @endif
@endsection
