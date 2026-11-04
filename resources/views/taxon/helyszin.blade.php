@extends('programok.lista')
@use('App\Support\Nyelv')
@use('App\Support\ProgramKartya')
@use('App\Support\ProgramSzures')
@php
    /*
     * EGY HELYSZÍN LAPJA — a közös programlista-lap (programok/lista: a programkereső sorai, „Vissza a programkeresőhöz”).
     * A programok a keresőével azonos szűréssel: ezen a helyszínen és a termeiben, minden nap.
     * A cím alatt a helyszín adatai: a szülője és a termei (a KERESŐBE visznek, arra a helyszínre szűrve — minden a keresőbe
     * vezet vissza), a cím, az OpenStreetMap-keresés, és — tulajdonosi döntés (2026-11-04) — a térképszám a „Mutasd a
     * térképen” linkkel (a /terkep a jelölőt kiemeli; saját pozíció nélküli terem a szülőjéét kapja).
     */
    $ny = Nyelv::aktualis();
    $nev = ProgramKartya::helyszinNev($v);
    $cim = ($ny === 'ro' && filled($v->address_ro)) ? $v->address_ro : $v->address;
    $osm = 'https://www.openstreetmap.org/search?query=' . rawurlencode($v->gps_lat && $v->gps_lng ? $v->gps_lat . ',' . $v->gps_lng : ($cim ?: $v->name));
    $keresoben = fn ($h) => (new ProgramSzures(ProgramSzures::MIND, [], [], [$h->slug]))->url();
@endphp
@section('title', $nev)
@section('lista_cim'){{ $nev }}@endsection
@section('lista_info')
  <div class="pk-lista-info">
    @if ($v->parent)<p>{{ __('publikus.b_resze') }} <a href="{{ $keresoben($v->parent) }}">{{ ProgramKartya::helyszinNev($v->parent) }}</a></p>@endif
    @if (filled($cim))<p>{{ $cim }} · <a href="{{ $osm }}" target="_blank" rel="noopener noreferrer">{{ __('publikus.b_terkep_megnyitasa') }}</a></p>@endif
    @if ($jelolo)<p class="hk-terkepszam"><span class="hk-szam" aria-label="{{ __('publikus.b_terkepszam') }}">{{ $jelolo['no'] }}</span><a href="{{ Nyelv::ut('/terkep') }}?helyszin={{ $jelolo['slug'] }}">{{ __('publikus.b_mutasd_terkepen') }} →</a></p>@endif
    @if ($gyerekek->isNotEmpty())
      <p>{{ __('publikus.b_alhelyszinek') }}: @foreach ($gyerekek as $c)<a href="{{ $keresoben($c) }}">{{ ProgramKartya::helyszinNev($c) }}</a>@if (! $loop->last) · @endif @endforeach</p>
    @endif
  </div>
@endsection
