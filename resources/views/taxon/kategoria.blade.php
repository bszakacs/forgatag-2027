@extends('layouts.app')
@use('App\Support\Nyelv')
{{--
  EGY KATEGÓRIA LAPJA — a 2026-os components/TaxonDetailView.astro (kind=category) jelölése BETŰRE (CLAUDE.md 2/e).
  A programok az ELSŐDLEGES és a TOVÁBBI kategória szerint is (2026: inCategory), az első időpont szerint.
  (A csoport-színes fejléc 2026-ban a csoportokkal megszűnt — a kategóriának nincs színe —, ezért a sima cím-ág él.)
--}}
@php $nev = Nyelv::valaszt($k->name_hu, $k->name_ro); @endphp
@section('title', $nev)
@include('partials.alap-fej')

@section('content')
  <p><a href="{{ Nyelv::ut('/kategoriak') }}">← {{ __('publikus.b_kategoriak') }}</a></p>
  <h2>{{ $nev }}</h2>
  @forelse ($programok as $program)
    @include('partials.programkartya', ['program' => $program])
  @empty
    <div class="empty">{{ __('publikus.b_nincs_kategoria') }}</div>
  @endforelse
@endsection
