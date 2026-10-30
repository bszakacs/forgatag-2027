@extends('layouts.app')
@use('App\Support\Festival')
@use('App\Support\Nyelv')
@use('App\Support\ProgramSzures')
@php
    /**
     * A PROGRAMKERESŐ — kinézet és viselkedés a 2026-os (CLAUDE.md 2/e, `../forgatag-os/frontend/src/programkereso/`).
     * Szerkezet (a htmx miatt számít, mi hol van):
     *   #pk-tartalom (hx-history-elt: a „vissza” gomb CSAK ezt tölti újra)
     *     #pk-szam      a TALÁLATSZÁM sora — a szűrők FÖLÖTT: a fejléc alá belógó logó erre a nem kattintható
     *                   szövegre lóg rá, nem egy gombra (2026-os tanulság; a sáv beljebb tolása csúnya volt)
     *     #pk-urlap     a szűrő-űrlap. JS nélkül rendes GET; htmx-szel kattintásra azonnal, gépelésre ~280 ms múlva
     *                   kér, és CSAK a #pk-talalatok cserélődik. Az űrlap maga nem cserélődik (a gépelés, a nyitott
     *                   lenyíló és a lista görgetése megmarad); a darabszámok id szerint, célzottan frissülnek (oob).
     *     #pk-talalatok aktív szűrők + kiemelt sáv + találatok — EZ a csere célterülete.
     *     #pk-modal     a felugró ablak (a címben: ?program=<slug>, megosztható) — saját, célzott cserével.
     *   A TÉRKÉP (2. rész) a #pk-tartalom-on KÍVÜL kap helyet: az innerHTML-csere megsemmisítené az eseménykezelőit.
     */
    $ny = Nyelv::aktualis();
    $cim = Nyelv::valaszt($lap->title_hu, $lap->title_ro);
    $alapUrl = Nyelv::ut('/programok');
    $tenyleges = $szures->tenylegesNap();
    $napFelirat = $szures->datum() ? Festival::dayLabel(\Carbon\CarbonImmutable::parse($szures->datum()), $ny) : null;
    $katNev = $kategoriak->mapWithKeys(fn ($k) => [$k->slug => Nyelv::valaszt($k->name_hu, $k->name_ro)]);
    $helyNev = collect($helyszinek)->mapWithKeys(fn ($h) => [$h['venue']->slug => Nyelv::valaszt($h['venue']->name, $h['venue']->name_ro)]);
    $napszakNev = ['delelott' => $sz->szoveg('programs_daypart_morning'), 'delutan' => $sz->szoveg('programs_daypart_afternoon'), 'este' => $sz->szoveg('programs_daypart_evening')];
    $szuroDb = $szures->aktivDb();
@endphp
@section('title', $cim)
@section('leiras', Nyelv::valaszt($lap->seo_description_hu, $lap->seo_description_ro))
@section('canonical', url($szures->mas(['program' => null])->url()))
@section('nyelv_query', $szures->querySzoveg())
@push('styles')
<link rel="stylesheet" href="{{ asset('css/rail.css') }}?v={{ filemtime(public_path('css/rail.css')) }}">
<link rel="stylesheet" href="{{ asset('css/programok.css') }}?v={{ filemtime(public_path('css/programok.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/rail.js') }}?v={{ filemtime(public_path('js/rail.js')) }}"></script>
<script src="{{ asset('js/programkereso.js') }}?v={{ filemtime(public_path('js/programkereso.js')) }}"></script>
@endpush

@section('content')
<div class="pk" x-data="pkKereso" x-effect="document.documentElement.classList.toggle('pk-lock', lap)" @keydown.escape.window="lap = false">
  <h1 class="pk-srtitle">{{ $sz->szoveg('programs_title_a') }}{{ $sz->szoveg('programs_title_b') }}</h1>

  <div id="pk-tartalom" hx-history-elt>
    {{-- 1. TALÁLATSZÁM — a szűrők fölött (lásd fent). aria-live: a képernyőolvasó is hallja a változást. --}}
    <div class="wrap pk-fej">
      <p class="pk-count" id="pk-szam" hx-swap-oob="true" aria-live="polite">
        <b>{{ $t['osszes'] }}</b> {{ __('publikus.pk_program_szo') }}@if ($szures->most())<span class="pk-countday"> · {{ __('publikus.pk_zajlik') }}</span>@elseif ($napFelirat)<span class="pk-countday"> · {{ $napFelirat }}</span>@endif
      </p>
    </div>

    <div class="wrap pk-racs">
      <form id="pk-urlap" class="pk-explore" method="get" action="{{ $alapUrl }}" role="search"
            :class="{ 'is-open': lap }" data-alapnap="{{ ProgramSzures::alapNap() }}"
            hx-get="{{ $alapUrl }}" hx-trigger="change[target.matches('[data-pk-szuro]')], submit"
            hx-target="#pk-talalatok" hx-select="#pk-talalatok" hx-swap="outerHTML" hx-push-url="true"
            hx-sync="this:replace" hx-indicator="#pk-talalatok">
        <div class="pk-sheethead">
          <span class="pk-sheettitle">{{ $sz->szoveg('programs_filter_title') }}</span>
          <a class="pk-sheetclose" href="#" @click.prevent="lap = false" aria-label="{{ __('publikus.bezaras') }}">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
          </a>
        </div>

        <div class="pk-panel">
          {{-- Szabadszavas keresés: MINDEN napon keres (2026: a gépelés „minden nap” állapotra vált, a törlése vissza a
               kezdőnapra). Saját kérést indít (késleltetve); a válasz nem cseréli le, amit közben beírtál. --}}
          <label class="pk-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
            <input type="search" id="pk-q" name="q" value="{{ $szures->q }}" maxlength="80" autocomplete="off"
                   placeholder="{{ $sz->szoveg('programs_filter_search_ph') }}" aria-label="{{ __('publikus.pk_kereses_cimke') }}"
                   @input="keresesValt($el)"
                   hx-get="{{ $alapUrl }}" hx-include="closest form" hx-trigger="input changed delay:280ms, search"
                   hx-sync="closest form:replace">
          </label>

          {{-- NAPSÁV (2026): a „Most zajlik” + a napok EGY sorban. A kiválasztott gomb kiemelése AZONNAL, a böngészőben
               (CSS :has); a kiválasztott nap újbóli megnyomása kikapcsolja (minden nap). A „minden nap” rejtett rádió:
               JS nélkül a nap-címke „x”-e vezet oda. --}}
          <fieldset class="pk-dayblock">
            <legend class="pk-srtitle">{{ __('publikus.pk_napok') }}</legend>
            <input class="pk-srtitle" type="radio" id="pk-nap-mind" name="nap" value="{{ ProgramSzures::MIND }}" data-pk-szuro tabindex="-1" aria-hidden="true" @checked($tenyleges === ProgramSzures::MIND)>
            <div class="pk-days">
              <label class="pk-now" @click="napKattint($event, $el)">
                <input type="radio" id="pk-nap-most" name="nap" value="{{ ProgramSzures::MOST }}" data-pk-szuro @change="mostValt()" @checked($tenyleges === ProgramSzures::MOST)>
                <span class="pk-pulse" aria-hidden="true"></span>
                <span class="pk-nowlab"><span>{{ $sz->szoveg('programs_now_1') }}</span><span>{{ $sz->szoveg('programs_now_2') }}</span></span>
              </label>
              @foreach ($napok as $nap)
                <label @class(['pk-day', 'is-soft' => $nap['prep']]) @click="napKattint($event, $el)">
                  <input type="radio" id="pk-nap-{{ $nap['id'] }}" name="nap" value="{{ $nap['id'] }}" data-pk-szuro @checked($tenyleges === $nap['id'])>
                  <span class="pk-dow">{{ Festival::shortWeekday($nap['date'], $ny) }}</span>
                  <span class="pk-num">{{ $nap['date']->day }}</span>
                  <span class="pk-srtitle">{{ Festival::fullDayLabel($nap['date'], $ny) }}</span>
                </label>
              @endforeach
            </div>
          </fieldset>

          <div class="pk-panel-groups">
            {{-- NAPSZAK (2026): chipek, többes választás; a „Most zajlik”-kal kizárják egymást. --}}
            <details class="pk-group" @if ($szures->napszakok) open @endif>
              <summary>
                {{ $sz->szoveg('programs_filter_daypart') }}
                <span class="pk-gcount" id="pk-gc-napszak" hx-swap-oob="true" @if (! $szures->napszakok) hidden @endif>{{ count($szures->napszakok) }}</span>
                <i class="pk-caret" aria-hidden="true"></i>
              </summary>
              <div class="pk-groupbody">
                <div class="pk-chips" role="group" aria-label="{{ $sz->szoveg('programs_filter_daypart') }}">
                  @foreach ($napszakNev as $kulcs => $felirat)
                    <label class="pk-chip">
                      <input type="checkbox" id="pk-napszak-{{ $kulcs }}" name="napszak" value="{{ $kulcs }}" data-pk-szuro
                             @change="napszakValt($el)" @checked(in_array($kulcs, $szures->napszakok, true))>
                      {{ $felirat }}
                    </label>
                  @endforeach
                </div>
              </div>
            </details>

            @foreach (['helyszin' => ['programs_filter_venue', 'programs_filter_venue_ph', 'hely'], 'kategoria' => ['programs_filter_category', 'programs_filter_category_ph', 'kat']] as $dim => [$felirat, $ph, $rov])
              @php $kivalasztva = $dim === 'helyszin' ? $szures->helyszinek : $szures->kategoriak; @endphp
              {{-- Az űrlap szűréskor nem cserélődik, ezért a lenyíló nyitva marad; a „vissza” gombnál a kiválasztást tartalmazó nyílik. --}}
              <details class="pk-group" @if ($kivalasztva) open @endif>
                <summary>
                  {{ $sz->szoveg($felirat) }}
                  <span class="pk-gcount" id="pk-gc-{{ $rov }}" hx-swap-oob="true" @if (! $kivalasztva) hidden @endif>{{ count($kivalasztva) }}</span>
                  <i class="pk-caret" aria-hidden="true"></i>
                </summary>
                <div class="pk-groupbody">
                  <div class="pk-venuesearch">
                    {{-- Csak a lista böngészése (nincs neve: nem kerül a címsorba). --}}
                    <input type="search" id="pk-{{ $rov }}-q" x-model="szo.{{ $rov }}" placeholder="{{ $sz->szoveg($ph) }}" aria-label="{{ $sz->szoveg($ph) }}" autocomplete="off">
                  </div>
                  <div class="pk-opts pk-venuelist">
                    @if ($dim === 'helyszin')
                      @foreach ($helyszinek as $h)
                        @include('programok._opcio', ['nev' => 'helyszin', 'slug' => $h['venue']->slug, 'felirat' => $helyNev[$h['venue']->slug], 'gyerek' => $h['gyerek'],
                            'db' => $darabok['helyszin'][$h['venue']->slug] ?? 0, 'be' => in_array($h['venue']->slug, $szures->helyszinek, true), 'rov' => $rov])
                      @endforeach
                    @else
                      @foreach ($kategoriak as $k)
                        @include('programok._opcio', ['nev' => 'kategoria', 'slug' => $k->slug, 'felirat' => $katNev[$k->slug], 'gyerek' => false,
                            'db' => $darabok['kategoria'][$k->slug] ?? 0, 'be' => in_array($k->slug, $szures->kategoriak, true), 'rov' => $rov])
                      @endforeach
                    @endif
                  </div>
                </div>
              </details>
            @endforeach
          </div>

          {{-- JavaScript nélkül a jelölőnégyzet nem küld: ez a gomb igen. JS-sel a szűrés azonnali, ezért rejtett. --}}
          <noscript><button class="art-btn pk-nojs" type="submit">{{ $sz->szoveg('programs_apply') }}</button></noscript>
        </div>

        {{-- MOBIL: a felcsúszó panel lába (2026): a szűrés már megtörtént, a gomb „mutasd ezeket” — a számmal. --}}
        <div class="pk-sheetfoot">
          <button class="art-btn pk-apply" type="submit" id="pk-alkalmaz" hx-swap-oob="true" @click.prevent="lap = false">
            {{ $sz->szoveg('programs_apply') }} <span aria-hidden="true">·</span> <b>{{ $t['osszes'] }}</b> {{ $sz->szoveg('programs_word') }}
          </button>
        </div>
      </form>

      @include('programok._talalatok')
    </div>

    @include('programok._felugro')
  </div>

  {{-- MOBIL: az alsó, hüvelykujjal elérhető „Szűrés” gomb (mint 2026-ban) nyitja a felcsúszó szűrőpanelt; a jelvény
       az aktív szűrők száma. JS nélkül a link a panelre ugrik, és a :target nyitja. --}}
  <div class="pk-mobar">
    <a class="art-btn" href="#pk-urlap" @click.prevent="lap = true" aria-controls="pk-urlap">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18M6 12h12M10 18h4"/></svg>
      {{ $sz->szoveg('programs_filter_title') }}
      <span class="pk-badge-n" id="pk-szurodb" hx-swap-oob="true" @if (! $szuroDb) hidden @endif>{{ $szuroDb }}</span>
    </a>
  </div>
  <div class="pk-sheetscrim" @click="lap = false" aria-hidden="true"></div>
</div>
@endsection
