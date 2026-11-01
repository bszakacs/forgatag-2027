@extends('layouts.app')
@use('App\Support\Festival')
@use('App\Support\Nyelv')
@php
    // NAPI NÉZET (/nap/<dátum>): egy nap teljes programja, szűrés nélkül — megosztható, és JS nélkül is a teljes nap.
    // A kártyák és a napszak-csoportok a 2026-os programkereső jelölésével és stílusával (programkereso.css).
    // ⚠ A lap EGÉSZÉT a 2026-os DailyProgramView mellé téve még össze kell vetni (ALLAPOT.md, 2. rész).
    $ny = Nyelv::aktualis();
    $d = \Carbon\CarbonImmutable::parse($datum);
    $napNev = Festival::fullDayLabel($d, $ny);
@endphp
@section('title', $sz->szoveg('programs_day_title') . ' — ' . $napNev)
@section('leiras', Nyelv::valaszt($lap->seo_description_hu, $lap->seo_description_ro))
@push('styles')
<link rel="stylesheet" href="{{ asset('css/programkereso.css') }}?v={{ filemtime(public_path('css/programkereso.css')) }}">
<link rel="stylesheet" href="{{ asset('css/programkereso-szerver.css') }}?v={{ filemtime(public_path('css/programkereso-szerver.css')) }}">
<link rel="stylesheet" href="{{ asset('css/programok.css') }}?v={{ filemtime(public_path('css/programok.css')) }}">
@endpush

@section('content')
<div class="fgl-atmo" aria-hidden="true"></div>
<section class="blk pk-napi" data-section="programs-results">
  <div class="wrap">
    <header class="pk-naphead">
      <h1>{{ $sz->szoveg('programs_day_title') }} <span class="brush">{{ $napNev }}</span></h1>
    </header>

    <nav class="pk-daytabs" aria-label="{{ __('publikus.pk_napok') }}">
      @foreach ($napok as $nap)
        <a @class(['pk-day', 'is-soft' => $nap['id'] < Festival::openingDay(), 'is-on' => $nap['id'] === $datum]) href="{{ Nyelv::ut('/nap/' . $nap['id']) }}"
           aria-label="{{ Festival::fullDayLabel($nap['date'], $ny) }}" @if ($nap['id'] === $datum) aria-current="page" @endif>
          <span class="pk-dow">{{ Festival::shortWeekday($nap['date'], $ny) }}</span><span class="pk-num">{{ $nap['date']->day }}</span>
        </a>
      @endforeach
    </nav>

    <div class="pk-napi-eszkoz">
      <p class="pk-count"><b class="pk-countnum">{{ $sorok->count() }}</b> <span class="pk-countlab">{{ __('publikus.pk_program_szo') }}</span></p>
      <a class="pk-sheetbtn" href="{{ Nyelv::ut('/programok') }}?nap={{ $datum }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18M6 12h12M10 18h4"/></svg>
        <span>{{ __('publikus.pk_nap_szurese') }}</span>
      </a>
    </div>

    @if ($sorok->isEmpty())
      <div class="pk-empty"><p>{{ __('publikus.pk_nincs_ezen_a_napon') }}</p></div>
    @else
      @include('programok._csoportok', ['sorok' => $sorok])
    @endif
  </div>
</section>
@endsection
