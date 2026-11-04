@use('App\Support\ProgramKartya')
@use('App\Support\ProgramSzoveg')
{{--
  PROGRAMKÁRTYA — „a rendszer szíve”: a 2026-os components/ProgramCard.astro jelölése BETŰRE (CLAUDE.md 2/e).
  16/10 képmező (fotós: kép; FOTÓ NÉLKÜLI: kategóriaszín-fény + a kategória neve + pöttyöskör-firka = .pc-doodle, amit az
  Arculat „Fotó nélküli kártyákon” kapcsolója rejt/mutat) · kategória-jelölő · cím · időpont(ok) + helyszín · belépés-chip ·
  kedvenc-szív. Paraméterek: $program, opcionálisan $occ (a napi nézet kiemelt időpontja).
  A szív a kedvencek köréig tiltott (mint a programkeresőben); a „MA” jelvényt a public/js/alap.js kapcsolja (2026: Base).
--}}
@php $k = ProgramKartya::adat($program, $occ ?? null); @endphp
<article @class(['pc', 'pc-has-status' => $k['statusz']]) @if (! $k['statusz']) data-occ-dates="{{ $k['datumok'] }}" @endif>
  <div class="pc-media">
    @if ($program->cover)
      <a href="{{ $k['href'] }}" tabindex="-1" aria-hidden="true">
        <img src="{{ url($program->cover->getGlideUrl(['w' => 800, 'h' => 500, 'fit' => 'crop', 'fm' => 'webp', 'q' => 74])) }}" width="800" height="500" alt="" loading="lazy" decoding="async">
      </a>
    @else
      <a href="{{ $k['href'] }}" tabindex="-1" aria-hidden="true" class="pc-noimg">
        <svg class="pc-doodle" width="52" height="52" viewBox="0 0 60 60" fill="none" aria-hidden="true">
          <circle cx="30" cy="30" r="15" style="stroke:var(--gc, var(--accent-b))" stroke-width="6"></circle>
          <circle cx="30" cy="30" r="5" style="fill:var(--gc, var(--accent-b))"></circle>
          <circle cx="52" cy="18" r="3.5" style="fill:var(--gc, var(--accent-b))"></circle>
          <circle cx="55" cy="32" r="3" style="fill:var(--gc, var(--accent-b))"></circle>
          <circle cx="50" cy="45" r="3.5" style="fill:var(--gc, var(--accent-b))"></circle>
        </svg>
        <span class="pc-groupname">{{ $k['csoportNev'] }}</span>
      </a>
    @endif
    @if ($k['statusz'])
      <span class="pc-status {{ $k['statusz'] }}">{{ $k['statuszFelirat'] }}</span>
    @else
      <span class="pc-status ma" hidden data-ma-badge>{{ __('publikus.b_ma') }}</span>
    @endif
  </div>
  <div class="pc-body">
    @if ($k['csoportNev'] !== '')<div class="pc-group"><i aria-hidden="true"></i>{{ $k['csoportNev'] }}</div>@endif
    <h3 class="pc-title"><a href="{{ $k['href'] }}">{{ $k['cim'] }}</a></h3>
    <p class="pc-meta">
      @if ($k['ido'] !== '')<span class="pc-time">{{ $k['ido'] }}</span>@endif
      @if ($k['helyszin'] !== '')<span>{{ $k['helyszin'] }}</span>@endif
    </p>
    <div class="pc-foot">
      <span class="entry-chip {{ $k['belepes'] }}">{{ ProgramKartya::belepesFelirat($k['belepes']) }}@if ($k['belepes'] === 'ticketed' && trim($k['jegy']) !== '') · {{ ProgramSzoveg::linkek($k['jegy'], __('publikus.b_jegyvasarlas')) }}@endif</span>
      <button class="fav-btn" data-id="{{ $program->id }}" type="button" aria-pressed="false" aria-label="{{ __('publikus.b_kedvencekhez') }}" disabled></button>
    </div>
  </div>
</article>
