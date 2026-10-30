{{--
  KÖZÖS vízszintes csúszka (a 2026-os lib/rail): natív görgetés + egy-kártyás léptetés, alatta a nyilak és a húzható
  gomb. A viselkedés: public/js/rail.js (htmx-csere után is újraindul), a kinézet: public/css/rail.css.
  Paraméterek: $id, $osztaly (a használó rács-osztálya), $cimke, $tartalom (a kártyák HTML-je).
--}}
<div class="crail {{ $osztaly ?? '' }}" data-rail="{{ $id }}" tabindex="0" role="group" aria-label="{{ $cimke }}">{!! $tartalom !!}</div>
<div class="railnav" data-railnav="{{ $id }}" hidden>
  <button class="railarrow railprev" type="button" aria-label="{{ __('publikus.elozo') }}" disabled><svg viewBox="0 0 46 34" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M24 8 L14 17 L24 26"/><path d="M36 8 L26 17 L36 26"/><circle class="mk-dot" cx="9" cy="17" r="2.4" stroke="none"/></svg></button>
  <div class="railline"><div class="railknob" role="slider" tabindex="0" aria-label="{{ $cimke }}" aria-orientation="horizontal" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="14" class="mk-lime" fill="none" stroke-width="6"/><circle cx="20" cy="20" r="5" class="mk-yellow"/></svg></div></div>
  <button class="railarrow railnext" type="button" aria-label="{{ __('publikus.kovetkezo') }}"><svg viewBox="0 0 46 34" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 8 L32 17 L22 26"/><path d="M10 8 L20 17 L10 26"/><circle class="mk-dot" cx="37" cy="17" r="2.4" stroke="none"/></svg></button>
</div>
