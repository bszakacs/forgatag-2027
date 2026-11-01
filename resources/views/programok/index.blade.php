@extends('layouts.app')
@use('App\Support\Festival')
@use('App\Support\Nyelv')
@use('App\Support\ProgramSzures')
@php
    /**
     * A PROGRAMKERESŐ — a 2026-os `programkereso/body.html` jelölése BETŰRE (CLAUDE.md 2/e); a stílus a 2026-os style.css
     * (public/css/programkereso.css). Csak a KISZOLGÁLÁS más: a szűrés a szerveren fut, a szűrő-szekció egy GET-űrlap
     * (JS nélkül is működik), a JS által épített részeket (napsáv, listák, kártyák, aktív szűrők) a szerver rajzolja.
     *
     * htmx: az űrlap kattintásra azonnal, gépelésre ~280 ms múlva kér; a FŐ csere a #pkResults, a többi változó rész
     * (#pkActive, #pkHighlight, a darabszámok, a jelvények, a listák sorai) id szerint, célzottan (hx-swap-oob). Az űrlap
     * maga NEM cserélődik. A TÉRKÉP helye a találatok melletti jobb hasáb (#pkMapCol) és a mobil térképlap
     * (#pkMapSheetIn) — egyik sem csere-célpont, így a térkép eseménykezelőit semmi nem bántja (2. rész).
     */
    $ny = Nyelv::aktualis();
    $alapUrl = Nyelv::ut('/programok');
    $tenyleges = $szures->tenylegesNap();
    $n = $szures->aktivDb();
    $katNev = $kategoriak->mapWithKeys(fn ($k) => [$k->slug => Nyelv::valaszt($k->name_hu, $k->name_ro)]);
    $helyNev = $helyszinek->mapWithKeys(fn ($h) => [$h->slug => Nyelv::valaszt($h->name, $h->name_ro)]);
    $napNev = $szures->datum() ? Festival::fullDayLabel(\Carbon\CarbonImmutable::parse($szures->datum()), $ny) : null;
    $szuroIkon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18M6 12h12M10 18h4"/></svg>';
    $terkepIkon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 20l-5 2V6l5-2m0 16l6 2m-6-18l6 2m0 14l5-2V4l-5 2m0 14V6"/></svg>';
@endphp
@section('title', Nyelv::valaszt($lap->title_hu, $lap->title_ro))
@section('leiras', Nyelv::valaszt($lap->seo_description_hu, $lap->seo_description_ro))
@section('canonical', url($szures->mas([])->url()))
@section('nyelv_query', $szures->querySzoveg())
@section('ter', 'sajat')
@push('styles')
<link rel="stylesheet" href="@eszkoz('css/rail.css')">
<link rel="stylesheet" href="@eszkoz('css/programkereso.css')">
<link rel="stylesheet" href="@eszkoz('css/programkereso-szerver.css')">
@endpush
@push('scripts')
<script src="@eszkoz('js/rail.js')"></script>
<script src="@eszkoz('js/programkereso.js')"></script>
@endpush

@section('content')
<div class="fgl-atmo" aria-hidden="true"></div>

@if (! $vanProgram)
<section class="blk" data-section="programs-intro">
  <div class="wrap">
    <div class="pk-noprog">
      <h2>{{ $sz->szoveg('programs_noprog_title') }}</h2>
      <p>{{ $sz->szoveg('programs_noprog_text') }}</p>
    </div>
  </div>
</section>
@else
<section class="blk" data-section="programs-intro">
  <div class="wrap pk-intro">
    <h1 class="pk-srtitle"><span>{{ $sz->szoveg('programs_title_a') }}</span><span>{{ $sz->szoveg('programs_title_b') }}</span></h1>
    <div class="pk-mobar pk-mobar-inline" data-section="programs-tools" data-comp="program-mobile-tools">
      <button @class(['btn', 'btn-fill', 'orange', 'has-n' => $n > 0]) type="button" data-pk-sheet="filter" id="pkMobarSzures1" hx-swap-oob="true">
        {!! $szuroIkon !!}<span>{{ $sz->szoveg('programs_filter_title') }}</span><span class="pk-badge-n">{{ $n }}</span>
      </button>
      {{-- TÉRKÉP: a térkép-kör kapcsolja be (2. rész) — a gomb helye a 2026-os. --}}
      <button class="btn btn-fill lime" type="button" data-pk-sheet="map" disabled>{!! $terkepIkon !!}<span>{{ $sz->szoveg('programs_map_btn') }}</span></button>
    </div>
  </div>
</section>

<div class="fgl-decor" id="pageDecor" aria-hidden="true"></div>

{{-- GÖRGETÉSKOR BEÚSZÓ SÁV (2026): a szűrőblokk kigörgetése után a lap neve, „Szűrés” és a találatszám. --}}
<div class="pk-utilbar" id="pkUtil">
  <div class="wrap pk-utilbar-in">
    <span class="pk-pagename">{{ $sz->szoveg('programs_title_short') }}</span>
    <button @class(['pk-sheetbtn', 'has-n' => $n > 0]) type="button" data-pk-sheet="filter" id="pkUtilSzures" hx-swap-oob="true">
      {!! $szuroIkon !!}<span>{{ $sz->szoveg('programs_filter_title') }}</span><span class="pk-badge-n">{{ $n }}</span>
    </button>
    <p class="pk-total" id="pkUtilTotal" hx-swap-oob="true"><b class="pk-countnum">{{ $t['osszes'] }}</b> <span class="pk-countlab">{{ __('publikus.pk_talalat') }}</span></p>
  </div>
</div>

<div class="pk-sheetscrim" id="pkSheetScrim" aria-hidden="true"></div>

{{-- A SZŰRŐ-SZEKCIÓ = a GET-űrlap. Asztalon (1041 px fölött) VÍZSZINTES sáv a lap tetején, mobilon felcsúszó panel. --}}
<form class="pk-explore" data-section="programs-explore" id="pkUrlap" method="get" action="{{ $alapUrl }}" role="search"
      data-kezdonap="{{ ProgramSzures::kezdoNap() }}"
      hx-get="{{ $alapUrl }}" hx-trigger="change[target.matches('[data-pk-szuro]')], submit"
      hx-target="#pkResults" hx-select="#pkResults" hx-swap="outerHTML" hx-push-url="true" hx-sync="this:replace">
  <div class="pk-sheethead">
    <span class="pk-sheettitle">{{ $sz->szoveg('programs_filter_title') }}</span>
    <span class="pk-sheetn"><b id="pkSheetN" hx-swap-oob="true">{{ $t['osszes'] }}</b> <span>{{ $sz->szoveg('programs_word') }}</span></span>
    <button class="pk-sheetclose" type="button" id="pkSheetClose" aria-label="{{ __('publikus.pk_panel_bezaras') }}">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
  </div>

  <div class="wrap pk-explore-in">
    <div class="pk-panel" data-comp="program-filter" data-slot="filters">
      <div id="pkHeadSentinel" aria-hidden="true"></div>
      <div class="pk-panel-head">
        <h2>{{ $sz->szoveg('programs_filter_title') }}</h2>
        {{-- ÉLŐ TALÁLATSZÁM — asztalon a sáv LEGFELSŐ sora: a fejléc logója erre a nem kattintható sorra lóg be
             (a behúzását és a magasságát a JS a logó mért méretéből állítja, mint 2026-ban). --}}
        <p class="pk-count" id="pkCount" aria-live="polite" hx-swap-oob="true" @if ($n === 0) hidden @endif><b class="pk-countnum">{{ $t['osszes'] }}</b> <span class="pk-countlab">{{ $n ? __('publikus.pk_felel_meg') : __('publikus.pk_program_szo') }}</span><span class="pk-countday">{{ $napNev ? ' · ' . $napNev : '' }}</span></p>
      </div>

      <div class="pk-panel-top">
        <label class="pk-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          <input type="search" id="pkQ" name="q" value="{{ $szures->q }}" maxlength="80" autocomplete="off"
                 placeholder="{{ $sz->szoveg('programs_filter_search_ph') }}" aria-label="{{ __('publikus.pk_kereses_cimke') }}"
                 hx-get="{{ $alapUrl }}" hx-include="closest form" hx-trigger="input changed delay:280ms, search" hx-sync="closest form:replace">
        </label>

        <div class="pk-dayblock" data-comp="program-daybar">
          <input class="pk-srtitle" type="radio" id="pkNapMind" name="nap" value="{{ ProgramSzures::MIND }}" data-pk-szuro tabindex="-1" aria-hidden="true" @checked($tenyleges === ProgramSzures::MIND)>
          <div class="pk-days" id="pkDays" role="group" aria-label="{{ __('publikus.pk_napok') }}">
            <label @class(['pk-now', 'is-on' => $tenyleges === ProgramSzures::MOST]) id="pkNow">
              <input type="radio" name="nap" value="{{ ProgramSzures::MOST }}" data-pk-szuro @checked($tenyleges === ProgramSzures::MOST)>
              <span class="pk-pulse" aria-hidden="true"></span>
              <span class="pk-nowlab"><span>{{ $sz->szoveg('programs_now_1') }}</span><span>{{ $sz->szoveg('programs_now_2') }}</span></span>
            </label>
            @foreach ($napok as $nap)
              <label @class(['pk-day', 'is-soft' => $nap['date']->format('Y-m-d') < Festival::openingDay(), 'is-on' => $tenyleges === $nap['id']])>
                <input type="radio" name="nap" value="{{ $nap['id'] }}" data-pk-szuro aria-label="{{ Festival::fullDayLabel($nap['date'], $ny) }}" @checked($tenyleges === $nap['id'])>
                <span class="pk-dow">{{ Festival::shortWeekday($nap['date'], $ny) }}</span><span class="pk-num">{{ $nap['date']->day }}</span>
              </label>
            @endforeach
          </div>
        </div>
      </div>

      <div class="pk-panel-groups">
        <details class="pk-group">
          <summary><span>{{ $sz->szoveg('programs_filter_daypart') }}</span><span class="pk-gcount" id="pkGcNapszak" hx-swap-oob="true" @if (! $szures->napszakok) hidden @endif>{{ count($szures->napszakok) }}</span><i class="pk-caret" aria-hidden="true"></i></summary>
          <div class="pk-groupbody">
            <div class="pk-chips" role="group" aria-label="{{ $sz->szoveg('programs_filter_daypart') }}">
              @foreach (['delelott' => 'programs_daypart_morning', 'delutan' => 'programs_daypart_afternoon', 'este' => 'programs_daypart_evening'] as $kulcs => $kk)
                @php $be = in_array($kulcs, $szures->napszakok, true); @endphp
                <label class="pk-chip" aria-pressed="{{ $be ? 'true' : 'false' }}"><input type="checkbox" name="napszak" value="{{ $kulcs }}" data-pk-szuro @checked($be)>{{ $sz->szoveg($kk) }}</label>
              @endforeach
            </div>
          </div>
        </details>

        <details class="pk-group">
          <summary><span>{{ $sz->szoveg('programs_filter_venue') }}</span><span class="pk-gcount" id="pkGcHely" hx-swap-oob="true" @if (! $szures->helyszinek) hidden @endif>{{ count($szures->helyszinek) }}</span><i class="pk-caret" aria-hidden="true"></i></summary>
          <div class="pk-groupbody">
            <div class="pk-venuesearch">
              <input type="search" id="pkVenueQ" placeholder="{{ $sz->szoveg('programs_filter_venue_ph') }}" aria-label="{{ $sz->szoveg('programs_filter_venue_ph') }}" autocomplete="off">
            </div>
            <div class="pk-venuelist" id="pkVenues"><div class="pk-opts">
              @include('programok._opciok', ['csoport' => 'venue', 'nev' => 'helyszin', 'nevek' => $helyNev, 'db' => $darabok['helyszin'], 'kivalasztva' => $szures->helyszinek])
            </div></div>
          </div>
        </details>

        <details class="pk-group">
          <summary><span>{{ $sz->szoveg('programs_filter_category') }}</span><span class="pk-gcount" id="pkGcKat" hx-swap-oob="true" @if (! $szures->kategoriak) hidden @endif>{{ count($szures->kategoriak) }}</span><i class="pk-caret" aria-hidden="true"></i></summary>
          <div class="pk-groupbody">
            <div class="pk-venuesearch">
              <input type="search" id="pkCatQ" placeholder="{{ $sz->szoveg('programs_filter_category_ph') }}" aria-label="{{ $sz->szoveg('programs_filter_category_ph') }}" autocomplete="off">
            </div>
            <div class="pk-catlist" id="pkCats"><div class="pk-opts">
              @include('programok._opciok', ['csoport' => 'cat', 'nev' => 'kategoria', 'nevek' => $katNev, 'db' => $darabok['kategoria'], 'kivalasztva' => $szures->kategoriak])
            </div></div>
          </div>
        </details>
      </div>
      {{-- JavaScript nélkül a jelölőnégyzet nem küld: ez a gomb igen (JS-sel a szűrés azonnali, mint 2026-ban). --}}
      <noscript><button class="btn btn-primary pk-nojs" type="submit">{{ $sz->szoveg('programs_apply') }}</button></noscript>
    </div>
  </div>

  <div class="pk-sheetfoot">
    <button class="btn btn-fill lime pk-apply" type="submit" data-pk-close-sheet>
      <span>{{ $sz->szoveg('programs_apply') }}</span><span class="pk-applysep" aria-hidden="true">·</span><b class="pk-applyn" id="pkApplyN" hx-swap-oob="true">{{ $t['osszes'] }}</b><span>{{ $sz->szoveg('programs_word') }}</span>
    </button>
  </div>
</form>

{{-- TÉRKÉPLAP (csak mobilon, 2026) — a térkép a 2. részben ide és a jobb hasábba (#pkMapCol) kerül. --}}
<section class="pk-mapsheet" id="pkMapSheet" data-section="programs-map" aria-hidden="true">
  <div class="pk-sheethead">
    <span class="pk-sheettitle">{{ $sz->szoveg('programs_map_btn') }}</span>
    <span class="pk-sheetn"><b class="pk-countnum">{{ $t['osszes'] }}</b> <span class="pk-countlab">{{ __('publikus.pk_program_szo') }}</span></span>
    <button class="pk-sheetclose" type="button" data-pk-close-sheet aria-label="{{ __('publikus.pk_panel_bezaras_talalatok') }}">
      <svg class="pk-ic-x" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      <svg class="pk-ic-find" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
    </button>
  </div>
  <div class="pk-mapsheet-in" id="pkMapSheetIn"></div>
</section>

<section class="blk" data-section="programs-results" style="padding-top:var(--sp-5)">
  <div class="wrap">
    @include('programok._aktiv')
    <div id="pkUtilSentinel" aria-hidden="true"></div>
    @include('programok._kiemelt')
    @include('programok._eredmeny')
    {{-- TÉRKÉP-HASÁB asztalon (2026, 3/4. lépés): a lista MELLETT, jobbra; alatta a jelmagyarázat. A helye most
         megvan (440 px), a tartalma a térkép-körben (2. rész) jön. Nem csere-célpont. --}}
    <div class="pk-mapcol" id="pkMapCol" aria-hidden="false"></div>
  </div>
</section>

<div @class(['pk-mobar', 'pk-mobar-fixed']) id="pkMobar" data-section="programs-tools" data-comp="program-mobile-tools">
  <button @class(['btn', 'btn-fill', 'orange', 'has-n' => $n > 0]) type="button" data-pk-sheet="filter" id="pkMobarSzures2" hx-swap-oob="true">
    {!! $szuroIkon !!}<span>{{ $sz->szoveg('programs_filter_title') }}</span><span class="pk-badge-n">{{ $n }}</span>
  </button>
  <button class="btn btn-fill lime" type="button" data-pk-sheet="map" disabled>{!! $terkepIkon !!}<span>{{ $sz->szoveg('programs_map_btn') }}</span></button>
</div>

@include('programok._felugro')
@endif
@endsection
