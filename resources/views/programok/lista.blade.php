@extends('layouts.app')
@use('App\Support\Nyelv')
@php
    /*
     * PROGRAMLISTA-LAP — a napi nézet, a kategória- és a helyszínlap közös kerete (tulajdonosi szabály, 2026-11-04):
     *  · BÁRHOL listázunk programokat, a PROGRAMKERESŐ sablonja listázza (programok/_napok → _csoportok → _kartyak):
     *    ugyanaz a kód, ugyanaz a kinézet — csak a jobb oldali térképhasáb nincs;
     *  · MINDEN a keresőbe vezet vissza: fent és lent hangsúlyos „Vissza a programkeresőhöz” UGYANARRA a szűrésre ($kereso),
     *    és a sor is a kereső felugró ablakára visz. Más lapra (kategórialista, helyszínlista) innen nem vezet link.
     * A gyerek-lap adja: @section('lista_cim') (a cím HTML-je), opcionálisan @section('lista_info') és @section('lista_fej');
     * adat: $sorok (ProgramSor), $egyNap, $kereso (ProgramSzures — a lap programjai a keresőben), $ures (üres lista szövege).
     */
    $ny = Nyelv::aktualis();
    $vissza = $kereso->url();
@endphp
@section('ter', 'sajat')
@push('styles')
<link rel="stylesheet" href="@eszkoz('css/programkereso.css')">
<link rel="stylesheet" href="@eszkoz('css/programkereso-szerver.css')">
<link rel="stylesheet" href="@eszkoz('css/programlista.css')">
@endpush

@section('content')
<div class="fgl-atmo" aria-hidden="true"></div>
<section class="blk pk-napi" data-section="programs-results">
  <div class="wrap">
    <header class="pk-naphead">
      <h1>@yield('lista_cim')</h1>
      @yield('lista_info')
    </header>
    @yield('lista_fej')

    <div class="pk-napi-eszkoz">
      <p class="pk-count"><b class="pk-countnum">{{ $sorok->count() }}</b> <span class="pk-countlab">{{ __('publikus.pk_program_szo') }}</span></p>
      <a class="btn btn-primary pk-vissza" href="{{ $vissza }}"><span aria-hidden="true">←</span> {{ __('publikus.b_vissza_kereso') }}</a>
    </div>

    @if ($sorok->isEmpty())
      <div class="pk-empty"><p>{{ $ures }}</p></div>
    @else
      @include('programok._napok', ['sorok' => $sorok, 'egyNap' => $egyNap])
    @endif

    <div class="pk-lista-vege"><a class="btn btn-primary pk-vissza" href="{{ $vissza }}"><span aria-hidden="true">←</span> {{ __('publikus.b_vissza_kereso') }}</a></div>
  </div>
</section>
@endsection
