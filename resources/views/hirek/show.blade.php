@extends('layouts.app')
@use('App\Support\Nyelv')
@use('App\Support\HtmlSzuro')
@php
    $cim = Nyelv::valaszt($hir->title_hu, $hir->title_ro);
    $kivonat = Nyelv::valaszt($hir->excerpt_hu, $hir->excerpt_ro);
    $vissza = Nyelv::ut('/hirek');
    $borito = $hir->cover ? url($hir->cover->getGlideUrl(['w' => 1200, 'h' => 1200, 'fit' => 'crop', 'fm' => 'webp', 'q' => 78])) : null;
@endphp
@section('title', $cim)
@section('leiras', \Illuminate\Support\Str::limit($kivonat, 300))
@if ($borito)
  @section('og_kep', $borito)
@endif

@section('content')
<div class="lap-wrap">
  <article class="article">
    <a class="article-back" href="{{ $vissza }}"><span aria-hidden="true">←</span> {{ __('publikus.vissza_hirekhez') }}</a>
    @if ($hir->published_on)
      <time class="article-date" datetime="{{ $hir->published_on->format('Y-m-d') }}">{{ Nyelv::datum($hir->published_on) }}</time>
    @endif
    <h1 class="article-title">{{ $cim }}</h1>
    @if ($borito)
      <img class="article-hero" src="{{ $borito }}" width="1200" height="1200" alt="">
    @endif
    <div class="article-body">
      @if ($kivonat !== '')
        <p class="lead">{{ $kivonat }}</p>
      @endif
      {{-- A szerkesztőből jövő HTML CSAK szűrve (App\Support\HtmlSzuro) — sosem nyersen. --}}
      {!! HtmlSzuro::tisztit(Nyelv::valasztHtml($hir->body_hu, $hir->body_ro)) !!}
    </div>
    <div class="article-foot"><a class="art-btn" href="{{ $vissza }}"><span aria-hidden="true">←</span> {{ __('publikus.vissza_hirekhez') }}</a></div>
  </article>
</div>
@endsection
