@extends('layouts.app')
@use('App\Support\Nyelv')
{{-- Saját aloldal (nem rendszerlap): cím, bevezető, nyitókép és a blokkok — mind az adminból (Tartalom → Aloldalak). --}}
@section('title', Nyelv::valaszt($lap->title_hu, $lap->title_ro))
@section('leiras', Nyelv::valaszt($lap->seo_description_hu, $lap->seo_description_ro))

@section('content')
<div class="lap-wrap">
  <header class="sec-head">
    <h1>{{ Nyelv::valaszt($lap->title_hu, $lap->title_ro) }}</h1>
  </header>
  @if (($bevezeto = Nyelv::valaszt($lap->lead_hu, $lap->lead_ro)) !== '')
    <p class="lap-lead">{{ $bevezeto }}</p>
  @endif
  @if ($lap->hero)
    <img class="lap-hero" src="{{ url($lap->hero->getGlideUrl(['w' => 1600, 'h' => 900, 'fit' => 'crop', 'fm' => 'webp', 'q' => 78])) }}" width="1600" height="900" alt="">
  @endif
  @include('partials.blokkok', ['blokkok' => $lap->blocks])
</div>
@endsection
