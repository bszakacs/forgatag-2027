@use('App\Support\Festival')
{{--
  NAPI KIEMELT SÁV — a 2026-os #pkHighlight jelölése: a kiválasztott nap kiemeltjei a koordinátor sorrendjében (nap
  nélkül a kezdőnapé); ha arra a napra nincs kiemelés, a sáv REJTETT. A csúszka a közös rail (partials/rail, a 2026-os
  lib/rail: id „prog-highlight”, osztály „pk-hirail”). Frissítés: id szerint (hx-swap-oob); a csúszka utána újraindul.
--}}
<div id="pkHighlight" hx-swap-oob="true" @if ($kiemelt['sorok']->isEmpty()) hidden @endif>
  <div class="pk-gtitle">
    <h2><span>{{ $sz->szoveg('programs_highlight_title_a') }}</span><span class="brush">{{ $sz->szoveg('programs_highlight_title_b') }}</span></h2>
    <span class="pk-gn" id="pkHighDay">{{ $kiemelt['sorok']->isEmpty() ? '' : Festival::fullDayLabel(\Carbon\CarbonImmutable::parse($kiemelt['nap']), $ny) }}</span>
  </div>
  @include('partials.rail', ['id' => 'prog-highlight', 'osztaly' => 'pk-hirail', 'cimke' => $ny === 'ro' ? 'Programe recomandate' : 'Kiemelt programok',
      'tartalom' => $kiemelt['sorok']->map(fn ($s) => view('programok._kartya', ['sor' => $s, 'listaSor' => false, 'szures' => $szures])->render())->implode('')])
</div>
