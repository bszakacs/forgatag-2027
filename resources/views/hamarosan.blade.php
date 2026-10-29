@extends('layouts.app')
@use('App\Support\Theme')
@use('App\Support\Nyelv')
@use('App\Support\Festival')
@use('App\Support\Settings')
{{--
  „HAMAROSAN” — a korai élesítés lapja. Ezt kapja a látogató minden publikus címen, ha a Beállítások → Publikus
  oldal kapcsolója be van kapcsolva (App\Http\Middleware\HamarosanMod); a kezdőlap megépüléséig a gyökér is ez.
  Szöveg: Beállítások; név és dátum: a fesztivál adatai; logó, firkák, színek: Arculat. Itt semmi nincs beégetve.
--}}
@php
    $ny = Nyelv::aktualis();
    $cim = Nyelv::valaszt(Settings::get('public.coming_soon_title_hu'), Settings::get('public.coming_soon_title_ro'));
@endphp
@section('keret', 'nelkul')
@section('title', $cim)
@section('leiras', Nyelv::valaszt(Settings::get('public.coming_soon_text_hu'), Settings::get('public.coming_soon_text_ro')))
@section('canonical', url(Nyelv::ut('/')))

@section('content')
<div class="hm" data-decor="hero">
  <span class="glow g1" aria-hidden="true"></span>
  <span class="glow g2" aria-hidden="true"></span>
  <div class="decor hm-d1" aria-hidden="true">{!! Theme::motifSvg(0) !!}</div>
  <div class="decor hm-d2" aria-hidden="true">{!! Theme::motifSvg(3) !!}</div>

  <div class="hm-in">
    @if (Theme::get('header.showLangSwitch'))
      <nav class="hm-lang" aria-label="{{ __('publikus.nyelvvalto') }}">
        @foreach (Nyelv::NYELVEK as $n)
          <a href="{{ url(Nyelv::ut('/', $n)) }}" lang="{{ $n }}" hreflang="{{ $n }}" @class(['on' => $n === $ny]) @if ($n === $ny) aria-current="true" @endif>{{ strtoupper($n) }}</a>
        @endforeach
      </nav>
    @endif
    <img class="hm-logo" src="{{ Theme::assetUrl('logo') }}" alt="{{ Festival::name($ny) }}">
    <p class="hm-datum">{{ Festival::spanLabel($ny) }} · {{ __('publikus.varos') }}</p>
    <h1 class="hm-cim">{{ $cim }}</h1>
    <p class="hm-szoveg">{{ Nyelv::valaszt(Settings::get('public.coming_soon_text_hu'), Settings::get('public.coming_soon_text_ro')) }}</p>
    @include('partials.social')
  </div>
</div>
@endsection
