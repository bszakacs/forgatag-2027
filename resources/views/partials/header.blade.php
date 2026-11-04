@use('App\Support\Theme')
@use('App\Support\Nyelv')
@use('App\Support\PublikusMenu')
@php
    // FEJLÉC — EGY helyen. Menü: az adatbázisból (Tartalom → Menü, „main”), nyelvenként szűrve.
    // A nyelvváltót a téma kapcsolója (Arculat → Fejléc) mutatja vagy rejti. A `menu` állapot a layout body-ján.
    $fomenu = PublikusMenu::fa('main');
    $ny = Nyelv::aktualis();
@endphp
<header class="site-hd">
  <div class="wrap bar">
    <a class="logo" href="{{ Nyelv::ut('/') }}"><img src="{{ Theme::assetUrl('logoHeader') }}" alt="{{ $fesztNev }}"></a>
    <nav class="main" aria-label="{{ __('publikus.fomenu') }}">
      @include('partials.nav', ['fa' => $fomenu, 'valtozat' => 'asztali'])
    </nav>
    <div class="head-tools">
      {{-- KERESŐ (téma: Arculat → Fejléc → „Kereső a fejlécben”) — a 2026-os SiteHeader jelölése; 2026-ban Enterre a /kereses
           lapra vitt (az a 2027-ben nincs), itt a programkereső szabadszavas keresőjére (PROMPT-15). Űrlap: JS nélkül is megy. --}}
      @if (Theme::get('header.showSearch'))
        <form class="search" role="search" action="{{ Nyelv::ut('/programok') }}" method="get">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          <input type="search" name="q" data-comp="search" placeholder="{{ __('publikus.b_kereso_hely') }}" aria-label="{{ __('publikus.b_kereso_hely') }}">
        </form>
      @endif
      @if (Theme::get('header.showLangSwitch'))
        <nav class="lang" aria-label="{{ __('publikus.nyelvvalto') }}">
          @foreach (Nyelv::NYELVEK as $n)
            <a href="{{ $cimek[$n] }}" lang="{{ $n }}" hreflang="{{ $n }}" @class(['on' => $n === $ny]) @if ($n === $ny) aria-current="true" @endif>{{ strtoupper($n) }}</a>
          @endforeach
        </nav>
      @endif
      {{-- JavaScript nélkül a link a #mobile panelre ugrik (CSS :target nyitja); Alpine-nal helyben nyit/zár. --}}
      <a class="burger" href="#mobile" role="button" aria-controls="mobile" aria-label="{{ __('publikus.menu_nyitas') }}"
         aria-expanded="false" :aria-expanded="menu.toString()" @click.prevent="menu = ! menu"><i></i><i></i><i></i></a>
    </div>
  </div>
</header>
<div class="mnav" id="mobile" :class="{ 'open': menu }">
  <a class="mnav-x" href="#">{{ __('publikus.menu_zaras') }} ✕</a>
  <nav class="mnav-scroll" aria-label="{{ __('publikus.fomenu') }}" @click="if ($event.target.closest('a')) menu = false">
    @include('partials.nav', ['fa' => $fomenu, 'valtozat' => 'mobil'])
  </nav>
</div>
