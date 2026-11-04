@extends('layouts.app')
@use('App\Support\Nyelv')
@use('App\Support\ProgramKartya')
@php
    /*
     * EGY HELYSZÍN LAPJA — a 2026-os components/VenueDetailView.astro jelölése BETŰRE (CLAUDE.md 2/e): vissza-link,
     * „része ennek”, név, címdoboz (+ OpenStreetMap-keresés), alhelyszínek, a helyszín ÉS a termei programjai.
     * TULAJDONOSI DÖNTÉS (2026-11-04) — tudatos eltérés: a címdobozban a TÉRKÉPSZÁM és „Mutasd a térképen” (a /terkep a
     * jelölőt kiemeli). Terem saját pozíció nélkül a szülője jelölőjét kapja (a térképen is az képviseli).
     * TULAJDONOSI DÖNTÉS (2026-11-04) — tudatos eltérés: a kártyák RÁCSBAN (2026-ban rács nélkül, teljes szélességben álltak —
     * 1280 px-en egy kártya 1265 × 990 px volt). A rács a 2026-os „Állandó” fül rácsa (.allando-grid), új kinézet nincs.
     */
    $ny = Nyelv::aktualis();
    $nev = ProgramKartya::helyszinNev($v);
    $cim = ($ny === 'ro' && filled($v->address_ro)) ? $v->address_ro : $v->address;
    $keres = $v->gps_lat && $v->gps_lng ? $v->gps_lat . ',' . $v->gps_lng : ($cim ?: $v->name);
    $osm = 'https://www.openstreetmap.org/search?query=' . rawurlencode($keres);
@endphp
@section('title', $nev)
@include('partials.alap-fej')

@section('content')
  <p><a href="{{ Nyelv::ut('/helyszinek') }}">← {{ __('publikus.b_helyszinek') }}</a></p>
  @if ($v->parent)
    <p class="hint">{{ __('publikus.b_resze') }} <a href="{{ Nyelv::ut('/helyszin/' . $v->parent->slug) }}">{{ ProgramKartya::helyszinNev($v->parent) }}</a></p>
  @endif
  <h2>{{ $nev }}</h2>

  <div class="infobox">
    @if (filled($cim))<p>{{ $cim }}</p>@endif
    <p><a href="{{ $osm }}" target="_blank" rel="noopener noreferrer">🗺️ {{ __('publikus.b_terkep_megnyitasa') }}</a></p>
    @if ($jelolo)
      <p class="hk-terkepszam"><span class="hk-szam" aria-label="{{ __('publikus.b_terkepszam') }}">{{ $jelolo['no'] }}</span>
        <a href="{{ Nyelv::ut('/terkep') }}?helyszin={{ $jelolo['slug'] }}">{{ __('publikus.b_mutasd_terkepen') }} →</a></p>
    @endif
  </div>

  @if ($gyerekek->isNotEmpty())
    <h3 class="section-title" style="margin-top:22px">{{ __('publikus.b_alhelyszinek') }} ({{ $gyerekek->count() }})</h3>
    <div class="chips venue-children">
      @foreach ($gyerekek as $c)
        <a class="chip cat" href="{{ Nyelv::ut('/helyszin/' . $c->slug) }}">{{ ProgramKartya::helyszinNev($c) }}</a>
      @endforeach
    </div>
  @endif

  <h3 class="section-title" style="margin-top:22px">{{ __('publikus.b_napi_program') }}</h3>
  @if ($programok->isEmpty())
    <div class="empty">{{ __('publikus.b_nincs_helyszin') }}</div>
  @else
    <div class="allando-grid">
      @foreach ($programok as $program)
        @include('partials.programkartya', ['program' => $program])
      @endforeach
    </div>
  @endif
@endsection
