@extends('layouts.app')
@use('App\Support\Kedvencek')
@use('App\Support\Nyelv')
@use('App\Support\Theme')
{{--
  KEDVENC PROGRAMJAIM (/kedvencek) — a 2026-os kedvenceim/body.html jelölése BETŰRE (CLAUDE.md 2/e, PROMPT-21): ugyanazok az
  elemek, osztályok és sorrend; a stílus a 2026-os style.css másolata (kedvencek.css), utána a programkeresőé (2026: kvCss + pkCss,
  ebben a sorrendben — a kártya és a felugró ablak kinézete a keresőé).

  🔴 A LAP FORRÁSÁBAN NINCS LÁTOGATÓI ADAT: a lap gyorsítótárazható. A lista a böngészőé (public/js/kedvencek.js); a
  kedvencek-lap.js a saját listájával kéri le a kártyákat (/kedvencek/kartyak), és a két állapotot (üres / feltöltött) ő kapcsolja.

  Eltérések a 2026-ostól (PROMPT-21, tulajdonosi döntés: belépés és személyes adat NINCS, a lista egy megosztható linkhez tartozik):
  · az e-mail-cím bekérése (fg-locked „Más böngészőben is látni szeretnéd?” + a fg-auth ablak) helyén a LISTA LINKJE, ugyanabban
    a dobozban (fg-locked is-offer) — és a lista FÖLÖTT, nem alatta: a link az egyetlen út vissza a listához, ezért nem lehet
    egy hosszú lista alján eldugva;
  · a kezelősor (2026: csak e-mail-címmel látszott) a listával együtt látszik; új eleme „Az egész lista a naptárba”;
  · a kártya a programkereső kártyája (programok/_kartyak — 2026-ban is „UGYANAZ a kártya”), a felugró ablak a keresőé (htmx nélkül
    kérve, a címsor nem változik).
--}}
@section('title', Nyelv::valaszt($lap->title_hu, $lap->title_ro))
@section('leiras', Nyelv::valaszt($lap->seo_description_hu, $lap->seo_description_ro))
@section('robots', 'noindex')
@section('ter', 'sajat')
@push('styles')
<link rel="stylesheet" href="@eszkoz('css/kedvencek.css')">
<link rel="stylesheet" href="@eszkoz('css/programkereso.css')">
<link rel="stylesheet" href="@eszkoz('css/kedvencek-szerver.css')">
<link rel="stylesheet" href="@eszkoz('css/naptar.css')">
@endpush
@push('scripts')
<script src="@eszkoz('js/naptar.js')" defer></script>
<script src="@eszkoz('js/kedvencek-lap.js')" defer></script>
@endpush

@section('content')
<div class="fgl-atmo" aria-hidden="true"></div>

<section class="blk" data-section="fav-intro" data-kedvencek-lap data-kartyak="{{ Nyelv::ut('/kedvencek/kartyak') }}" data-naptar-url="{{ Nyelv::ut('/kedvencek/naptar.ics') }}">
  <div class="wrap kv-intro">
    <h1><span data-hs="fav_title_a">{{ $sz->szoveg('fav_title_a') }}</span><span class="brush" data-hs="fav_title_b">{{ $sz->szoveg('fav_title_b') }}</span></h1>
    <p class="kv-sum" id="kvSum" aria-live="polite"></p>
  </div>
</section>

{!! Theme::motifSprite() !!}
<div class="fgl-decor" id="pageDecor" data-decor="ambient" data-szoras="oldal" aria-hidden="true"></div>

<section class="blk" data-section="fav-list" style="padding-top:var(--sp-4)">
  <div class="wrap">

    <div class="fg-locked is-offer kv-link" id="kvLink" hidden>
      <h2 id="kvLinkCim" data-hs="fav_link_title">{{ $sz->szoveg('fav_link_title') }}</h2>
      <p data-hs="fav_link_text">{{ $sz->szoveg('fav_link_text') }}</p>
      <div class="kv-linkrow">
        <input type="text" id="kvLinkInput" readonly aria-labelledby="kvLinkCim" placeholder="{{ __('publikus.kv_link_keszul') }}">
        <button class="btn btn-fill orange" type="button" id="kvLinkCopy" data-hs="fav_link_copy">{{ $sz->szoveg('fav_link_copy') }}</button>
        <button class="btn btn-ghost" type="button" id="kvLinkShare" data-hs="fav_link_share" hidden>{{ $sz->szoveg('fav_link_share') }}</button>
      </div>
    </div>

    <div id="kvList" data-slot="favourites" data-comp="fav-list" hidden></div>

    <div id="kvEmpty" class="kv-empty" hidden>
      <div class="kv-empty-card">
        <span class="kv-empty-heart" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M12 20s-7-4.6-7-9.6A4.4 4.4 0 0 1 12 7a4.4 4.4 0 0 1 7 3.4c0 5-7 9.6-7 9.6z"/></svg>
        </span>
        <h2 data-hs="fav_empty_title">{{ $sz->szoveg('fav_empty_title') }}</h2>
        <p data-hs="fav_empty_text">{{ $sz->szoveg('fav_empty_text') }}</p>
        <a class="btn btn-fill orange" href="{{ Nyelv::ut('/programok') }}" id="kvEmptyCta" data-hs="fav_empty_cta">{{ $sz->szoveg('fav_empty_cta') }}</a>
      </div>

      @php $ajanlott = Kedvencek::ajanlott(); @endphp
      @if ($ajanlott->isNotEmpty())
      <div class="kv-suggest">
        <div class="pk-gtitle">
          <h2><span data-hs="fav_suggest_a">{{ $sz->szoveg('fav_suggest_a') }}</span><span class="brush" data-hs="fav_suggest_b">{{ $sz->szoveg('fav_suggest_b') }}</span></h2>
          <span class="pk-gn" data-hs="fav_suggest_hint">{{ $sz->szoveg('fav_suggest_hint') }}</span>
        </div>
        <div class="pk-grid" id="kvSuggest" data-slot="programs" data-programs="highlight">
          @include('programok._kartyak', ['sorok' => $ajanlott, 'listaSor' => false, 'kedvenc' => true])
        </div>
      </div>
      @endif
    </div>

    <div class="kv-tools" id="kvTools" hidden>
      <div class="kv-toolrow">
        <a class="kv-tool" id="kvIcs" href="{{ Nyelv::ut('/kedvencek/naptar.ics') }}" data-hs="fav_ics_cta">{{ $sz->szoveg('fav_ics_cta') }}</a>
        <button class="kv-tool" type="button" id="kvHide" data-hs="fav_hide_cta" data-megerosites="{{ __('publikus.kv_elrejtes_megerosites') }}">{{ $sz->szoveg('fav_hide_cta') }}</button>
        <button class="kv-tool is-danger" type="button" id="kvWipe" data-hs="fav_wipe_cta" data-megerosites="{{ __('publikus.kv_torles_megerosites') }}">{{ $sz->szoveg('fav_wipe_cta') }}</button>
      </div>
    </div>
    {{-- A jegyzet a kezelősoron KÍVÜL: a lista betöltési hibája üres listánál is látszódjon (néma hiba tilos). --}}
    <p class="kv-toolnote" id="kvToolNote" role="status" aria-live="polite"
       data-t-elrejtve="{{ __('publikus.kv_elrejtve') }}" data-t-torolve="{{ __('publikus.kv_torolve') }}" data-t-torles-hiba="{{ __('publikus.kv_torles_hiba') }}"
       data-t-betoltes-hiba="{{ __('publikus.kv_betoltes_hiba') }}" data-t-link-betoltve="{{ __('publikus.kv_link_betoltve') }}"
       data-t-link-ervenytelen="{{ __('publikus.kv_link_ervenytelen') }}" data-t-link-hiba="{{ __('publikus.kv_link_hiba') }}"
       data-t-nincs-tarolo="{{ __('publikus.kv_nincs_tarolo') }}"></p>

  </div>
</section>

<div class="kv-undo" id="kvUndo" hidden role="status" aria-live="polite">
  <span class="kv-undo-txt"><b id="kvUndoName"></b> <span data-hs="fav_undo_text">{{ $sz->szoveg('fav_undo_text') }}</span></span>
  <button class="kv-undo-btn" type="button" id="kvUndoBtn" data-hs="fav_undo_cta">{{ $sz->szoveg('fav_undo_cta') }}</button>
</div>

{{-- A FELUGRÓ ABLAK helye (2026: <!--PK_MODAL-->) — a programkereső ablaka, kérésre töltődik (kedvencek-lap.js). --}}
<div class="pk-modal" id="pkModal" hidden role="dialog" aria-modal="true" aria-labelledby="pkMTitle" data-comp="program-modal"></div>
@endsection
