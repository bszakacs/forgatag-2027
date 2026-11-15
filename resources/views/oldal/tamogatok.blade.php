@extends('layouts.app')
@use('App\Support\Nyelv')
@use('App\Support\MediaLapok')
{{--
  TÁMOGATÓK — a 2026-os components/SponsorsView.astro jelölése BETŰRE (CLAUDE.md 2/e, PROMPT-17), a 2026-os Base keretben
  (alap.css): sötét fejszekció → teljes szélességű világos sáv a szintekkel és a logókkal → sötét „Köszönjük” panel.
  A csempe a kezdőlapi partner-sávval közös (MediaLapok::tamogatoCsempe + tamogatok.css); a csempe mérete a szint helyéből
  (1. → is-xl, 2. → is-lg, 3. → is-md, a többi → is-sm — 2026: sizeClass, a végleges magasságok a tamogatok-lap.css-ben).
  Üres szint nem jelenik meg; ha egy támogató sincs, a világos sáv sem. A logót soha nem színezzük át; az alt a támogató neve.
  MINDEN látható szöveg kulcs (LapSablon) — 2026-ban a „Köszönjük!” beégetett volt; a támogatók: Tartalom → Támogatók (a
  legutóbbi év, MediaLapok::tamogatoSzintek).
--}}
@section('title', Nyelv::valaszt($lap->title_hu, $lap->title_ro))
@section('leiras', Nyelv::valaszt($lap->seo_description_hu, $lap->seo_description_ro))
@include('partials.alap-fej')
@push('styles')
<link rel="stylesheet" href="@eszkoz('css/tamogatok.css')">
<link rel="stylesheet" href="@eszkoz('css/tamogatok-lap.css')">
@endpush
@php
    $szintek = MediaLapok::tamogatoSzintek(Nyelv::aktualis());
@endphp

@section('content')
  <section class="tmg-intro" data-section="sponsors-intro">
    <div class="tmg-wrap">
      <h1>
        <span data-hs="sponsors_title_a">{{ $sz->szoveg('sponsors_title_a') }}</span><span
          class="tmg-em" data-hs="sponsors_title_b">{{ $sz->szoveg('sponsors_title_b') }}</span><span
          data-hs="sponsors_title_c">{{ $sz->szoveg('sponsors_title_c') }}</span>
      </h1>
      <p class="tmg-lead" data-hs="sponsors_lead">{{ $sz->szoveg('sponsors_lead') }}</p>
    </div>
  </section>

  @if ($szintek)
  <section class="tmg-list" data-section="sponsors-list">
    <div class="tmg-wrap" data-slot="sponsors">
      @foreach ($szintek as $szint)
        <div class="tmg-tier {{ $szint['osztaly'] }}">
          <div class="tmg-head">
            <h2>{{ $szint['nev'] }}</h2>
            <span class="tmg-count">{{ count($szint['tamogatok']) }}</span>
          </div>
          <div class="tmg-grid">{!! collect($szint['tamogatok'])->map(fn ($p) => MediaLapok::tamogatoCsempe($p))->implode('') !!}</div>
        </div>
      @endforeach
    </div>
  </section>
  @endif

  <section class="tmg-outro" data-section="sponsors-outro">
    <div class="tmg-wrap">
      <div class="tmg-thanks">
        <p data-hs="sponsors_thanks"><strong>{{ $sz->szoveg('sponsors_thanks_strong') }}</strong>{{ $sz->szoveg('sponsors_thanks') }}</p>
      </div>
    </div>
  </section>
@endsection
