@extends('layouts.app')
@use('App\Support\Nyelv')
@php
    $cim = Nyelv::valaszt($lap->title_hu, $lap->title_ro);
    $n = $hirek->currentPage();
@endphp
{{-- A lapszám a címben is: a böngésző fülén, a könyvjelzőben és a keresőben is látszik, hányadik oldal. --}}
@section('title', $n > 1 ? $cim . ' – ' . __('publikus.oldal_n', ['n' => $n]) : $cim)
@section('leiras', Nyelv::valaszt($lap->seo_description_hu, $lap->seo_description_ro))
@section('canonical', url($lapUrl($n)))

@section('content')
<div class="lap-wrap">
  <header class="sec-head">
    <h1>{{ $sz->szoveg('news_title') }} <span class="brush">{{ $sz->szoveg('news_title_accent') }}</span></h1>
  </header>

  {{-- A #hirlista az, amit a lapozó htmx-szel kicserél (a teljes lapból hx-select-tel kiemelve). --}}
  <div id="hirlista" class="hirlista">
    @if ($hirek->isEmpty())
      <p class="nw-empty">{{ $sz->szoveg('news_empty') }}</p>
    @else
      <div class="nw-grid">
        @foreach ($hirek as $hir)
          @include('hirek._kartya', ['hir' => $hir])
        @endforeach
      </div>
    @endif

    @if ($hirek->lastPage() > 1)
      {{--
        LAPOZÓ: rendes linkek (?oldal=N) — JavaScript nélkül rendes oldalbetöltés.
        htmx-szel (hx-boost) a link ugyanazt a címet kéri le, a válaszból csak a #hirlista cserélődik,
        a címsor és a lapcím is frissül (a vissza gomb is működik). Saját JavaScript nincs.
      --}}
      <nav class="nw-pager" aria-label="{{ __('publikus.lapozas') }}"
           hx-boost="true" hx-target="#hirlista" hx-select="#hirlista" hx-swap="outerHTML show:window:top">
        @if ($n > 1)
          <a class="nw-pg nw-pg-arrow" href="{{ $lapUrl($n - 1) }}" rel="prev" aria-label="{{ __('publikus.elozo') }}">←</a>
        @endif
        @for ($i = 1; $i <= $hirek->lastPage(); $i++)
          <a @class(['nw-pg', 'on' => $i === $n]) href="{{ $lapUrl($i) }}" @if ($i === $n) aria-current="page" @endif aria-label="{{ __('publikus.oldal_n', ['n' => $i]) }}">{{ $i }}</a>
        @endfor
        @if ($n < $hirek->lastPage())
          <a class="nw-pg nw-pg-arrow" href="{{ $lapUrl($n + 1) }}" rel="next" aria-label="{{ __('publikus.kovetkezo') }}">→</a>
        @endif
      </nav>
    @endif
  </div>
</div>
@endsection
