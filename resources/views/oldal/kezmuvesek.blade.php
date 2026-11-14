@extends('layouts.app')
@use('App\Support\Nyelv')
@use('App\Support\Theme')
@use('App\Support\Kezmuvesek')
{{--
  KÉZMŰVES VÁSÁR — a 2026-os components/CraftspeopleView.astro jelölése BETŰRE (CLAUDE.md 2/e, PROMPT-17): ugyanazok az elemek,
  osztályok és sorrend, a 2026-os Base keretben (alap.css). A stílus a 2026-os kezmuvesek/style.css másolata, a szkript a
  lib/craft.ts átirata, a felugró ablak görgetője a közös vrail (2026: lib/rail).
  MINDEN látható szöveg kulcs: Tartalom → Aloldalak → Kézműves vásár (App\Support\LapSablon); a kézművesek: Tartalom → Kézművesek.
  Jelölési eltérések: a felúszás a közös .reveal (2026: .cv-reveal); a firka-réteg [data-decor="ambient"] jelölést kapott (a téma
  firka-kapcsolói erre hatnak), és a lapon a téma 12 motívuma egyszer szerepel (Theme::motifSprite — 2026-ban a szkriptbe égetve).
--}}
@section('title', Nyelv::valaszt($lap->title_hu, $lap->title_ro))
@section('leiras', Nyelv::valaszt($lap->seo_description_hu, $lap->seo_description_ro))
@include('partials.alap-fej')
@push('styles')
<link rel="stylesheet" href="@eszkoz('css/kezmuvesek.css')">
<link rel="stylesheet" href="@eszkoz('css/vrail.css')">
@endpush
@push('scripts')
<script src="@eszkoz('js/vrail.js')" defer></script>
<script src="@eszkoz('js/kezmuvesek.js')" defer></script>
@endpush
@php
    $emberek = Kezmuvesek::lista(Nyelv::aktualis());
    $per = Kezmuvesek::PER;
    $szamlalo = $sz->szoveg('craft_list_count');
    $elsoSzam = strtr($szamlalo, ['{from}' => '1', '{to}' => (string) min($per, count($emberek)), '{total}' => (string) count($emberek)]);
    $i18n = json_encode([
        'count' => $szamlalo,
        'prev' => __('publikus.cv_elozo'),
        'next' => __('publikus.cv_kovetkezo'),
        'page' => __('publikus.cv_lap_n'),
    ], JSON_UNESCAPED_UNICODE);
@endphp

@section('content')
{!! Theme::motifSprite() !!}
  <div class="cv-lap">
    <div class="cv-decor" id="pageDecor" data-decor="ambient" aria-hidden="true"></div>

    <section class="cv-sec cv-hero" data-section="craft-hero">
      <div class="cv-wrap reveal">
        <h1>
          <span data-hs="craft_hero_title">{{ $sz->szoveg('craft_hero_title') }}</span>
          <span class="cv-brush" data-hs="craft_hero_title_accent">{{ $sz->szoveg('craft_hero_title_accent') }}</span>
        </h1>
        <div class="cv-lead">
          <p data-hs="craft_hero_p1">{{ $sz->szoveg('craft_hero_p1') }}</p>
          <p data-hs="craft_hero_p2">{{ $sz->szoveg('craft_hero_p2') }}</p>
        </div>
      </div>
    </section>

    <section class="cv-sec cv-makers" id="kezmuvesek" data-section="craft-makers">
      <div class="cv-wrap">
        @if (count($emberek) === 0)
          <p class="cv-empty">{{ $sz->szoveg('craft_list_empty') }}</p>
        @else
          <div>
            <div class="cv-toolbar">
              <div class="cv-pager" data-cv-pager role="navigation" aria-label="{{ __('publikus.cv_lapozas') }} – {{ __('publikus.cv_felul') }}"></div>
              <p class="cv-count" id="cvCount" aria-live="polite">{{ $elsoSzam }}</p>
            </div>

            <div class="cv-grid" id="cvGrid" data-per-page="{{ $per }}" data-cv-i18n="{{ $i18n }}">
              @foreach ($emberek as $i => $p)
                <article class="cv-card"@if ($i >= $per) hidden @endif>
                  <button class="cv-open" type="button" aria-haspopup="dialog" data-cv-img="{{ $p['nagykep'] ?? '' }}" data-cv-alt="{{ $p['nev'] }}">
                    <span class="cv-thumb">
                      @if ($p['kiskep'])<img class="cv-img" src="{{ $p['kiskep'] }}" alt="" width="560" height="420" loading="lazy" decoding="async">@endif
                    </span>
                    <span class="cv-body">
                      <span class="cv-name">{{ $p['nev'] }}</span>
                      @if ($p['kivonat'] !== '')<span class="cv-teaser">{{ $p['kivonat'] }}</span>@endif
                      <span class="cv-more">{{ $sz->szoveg('craft_list_more') }}<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                    </span>
                  </button>
                  <div class="cv-full">@foreach ($p['bekezdesek'] as $t)<p>{{ $t }}</p>@endforeach</div>
                </article>
              @endforeach
            </div>

            <div class="cv-pager" data-cv-pager role="navigation" aria-label="{{ __('publikus.cv_lapozas') }} – {{ __('publikus.cv_alul') }}"></div>
            <p class="cv-note" data-hs="craft_list_note">{{ $sz->szoveg('craft_list_note') }}</p>
          </div>
        @endif
      </div>
    </section>
  </div>

  <div class="cv-modal" id="cvModal" role="dialog" aria-modal="true" aria-labelledby="cvModalTitle" hidden>
    <div class="cv-scrim" data-cv-close></div>
    <div class="cv-mcard">
      <button class="cv-x" type="button" data-cv-close aria-label="{{ __('publikus.bezaras') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
      <div class="cv-mimg" id="cvModalImg"></div>
      <div class="cv-mscroll" id="cvModalScroll" data-vscrollwrap>
        <div class="cv-mbody" id="cvModalBody" tabindex="0" data-vscroll="cvmodal">
          <h3 id="cvModalTitle"></h3>
          <div id="cvModalText"></div>
        </div>
        @include('partials.vrail', ['id' => 'cvmodal', 'cimke' => __('publikus.cv_gorgetes'), 'fel' => __('publikus.cv_fel'), 'le' => __('publikus.cv_le')])
      </div>
    </div>
  </div>
@endsection
