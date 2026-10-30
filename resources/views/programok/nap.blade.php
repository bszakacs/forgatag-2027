@extends('layouts.app')
@use('App\Support\Festival')
@use('App\Support\Nyelv')
@php
    // NAPI NÉZET (/nap/<dátum>): egy nap teljes programja, szűrés nélkül — megosztható, és JS nélkül is a teljes nap.
    // A sorrend és a napszak a fesztiválnap skáláján (hajnali vágás): a 23:00–00:30 program EZEN a napon van.
    $ny = Nyelv::aktualis();
    $d = \Carbon\CarbonImmutable::parse($datum);
    $napNev = Festival::fullDayLabel($d, $ny);
    $cim = $sz->szoveg('programs_day_title') . ' — ' . $napNev;
@endphp
@section('title', $cim)
@section('leiras', Nyelv::valaszt($lap->seo_description_hu, $lap->seo_description_ro))
@push('styles')
<link rel="stylesheet" href="{{ asset('css/programok.css') }}?v={{ filemtime(public_path('css/programok.css')) }}">
@endpush

@section('content')
<div class="pk pk-napi">
  <div class="wrap">
    {{-- A cím a lap tetején, a logó alatti sáv nem kattintható szöveg. --}}
    <header class="pk-naphead">
      <h1>{{ $sz->szoveg('programs_day_title') }} <span class="brush">{{ $napNev }}</span></h1>
    </header>

    <nav class="pk-daytabs" aria-label="{{ __('publikus.pk_napok') }}">
      @foreach ($napok as $nap)
        <a @class(['pk-day', 'is-soft' => $nap['prep'], 'is-on' => $nap['id'] === $datum]) href="{{ Nyelv::ut('/nap/' . $nap['id']) }}"
           @if ($nap['id'] === $datum) aria-current="page" @endif>
          <span class="pk-dow">{{ Festival::shortWeekday($nap['date'], $ny) }}</span>
          <span class="pk-num">{{ $nap['date']->day }}</span>
          <span class="pk-srtitle">{{ Festival::fullDayLabel($nap['date'], $ny) }}</span>
        </a>
      @endforeach
    </nav>

    <p class="pk-napi-eszkoz">
      <span class="pk-count"><b>{{ $sorok->count() }}</b> {{ __('publikus.pk_program_szo') }}</span>
      <a class="pk-sheetbtn" href="{{ Nyelv::ut('/programok') }}?nap={{ $datum }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18M6 12h12M10 18h4"/></svg>
        {{ __('publikus.pk_nap_szurese') }}
      </a>
    </p>

    @if ($sorok->isEmpty())
      <div class="pk-empty"><p>{{ __('publikus.pk_nincs_ezen_a_napon') }}</p></div>
    @else
      @include('programok._csoportok', ['sorok' => $sorok])
    @endif
  </div>
</div>
@endsection
