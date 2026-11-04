@extends('programok.lista')
@use('App\Support\Festival')
@use('App\Support\Nyelv')
@php
    /*
     * NAPI NÉZET (/nap/<dátum>, és az „Állandó” fül: /nap/allando) — a közös programlista-lap (programok/lista: a
     * programkereső sorai, „Vissza a programkeresőhöz” ugyanarra a napra). Fölötte a napválasztó a kereső napgombjaival.
     * Az „Állandó” fül a legalább két különböző napon futó programok — programonként egyszer, az első napjánál.
     */
    $ny = Nyelv::aktualis();
    $allando = $datum === 'allando';
@endphp
@section('title', $allando ? __('publikus.b_allando') : __('publikus.b_napi_program') . ' — ' . Festival::fullDayLabel(\Carbon\CarbonImmutable::parse($datum), $ny))
@section('lista_cim'){{ $allando ? __('publikus.b_allando_cim') : __('publikus.b_napi_program') }}@unless ($allando) <span class="brush">{{ Festival::fullDayLabel(\Carbon\CarbonImmutable::parse($datum), $ny) }}</span>@endunless @endsection
@section('lista_fej')
  <nav class="pk-daytabs" aria-label="{{ __('publikus.pk_napok') }}">
    @foreach ($napok as $nap)
      <a @class(['pk-day', 'is-soft' => $nap['id'] < Festival::openingDay(), 'is-on' => $nap['id'] === $datum]) href="{{ Nyelv::ut('/nap/' . $nap['id']) }}"
         aria-label="{{ Festival::fullDayLabel($nap['date'], $ny) }}" @if ($nap['id'] === $datum) aria-current="page" @endif>
        <span class="pk-dow">{{ Festival::shortWeekday($nap['date'], $ny) }}</span><span class="pk-num">{{ $nap['date']->day }}</span>
      </a>
    @endforeach
    <a @class(['pk-day', 'is-on' => $allando]) href="{{ Nyelv::ut('/nap/allando') }}" @if ($allando) aria-current="page" @endif>
      <span class="pk-dow">{{ __('publikus.b_allando') }}</span><span class="pk-num" aria-hidden="true">◔</span>
    </a>
  </nav>
@endsection
