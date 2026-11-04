@extends('layouts.app')
{{--
  HELYSZÍNEK / KATEGÓRIÁK listája — a 2026-os components/TaxonListView.astro jelölése BETŰRE (CLAUDE.md 2/e).
  A darabszám mellett 2026-ban mindkét nyelven „program” állt (beégetve) — ugyanígy.
--}}
@section('title', $cim)
@include('partials.alap-fej')

@section('content')
  <h2>{{ $cim }}</h2>
  <div class="grid">
    @foreach ($lista as $t)
      <a class="{{ $kat ? 'taxon-card taxon-cat' : 'taxon-card' }}" href="{{ $t['href'] }}">{{ $t['nev'] }}<small>{{ $t['db'] }} program</small></a>
    @endforeach
  </div>
@endsection
