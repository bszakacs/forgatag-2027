@use('App\Support\Festival')
@use('App\Support\Nyelv')
{{--
  KÁRTYA a napi kiemelt sávban (2026: ugyanaz a card(), sor-elrendezés nélkül): 4:3-as kép fent — a fájl ezen az
  arányon készül, a CSS nem vág másodszor —, alatta az idő, a cím, a helyszín és a jelzések.
--}}
@php
    $p = $sor->program();
    $v = $p->venue;
    $glow = ['orange', 'lime', 'blue', 'pink', 'yellow', 'red', 'purple', 'green'][($p->category_id ?? 0) % 8];
@endphp
<article class="pcard pk-card" style="--pk-glow:var(--doodle-{{ $glow }})">
  @if ($p->cover)
    <div class="thumb"><img src="{{ url($p->cover->getGlideUrl(['w' => 720, 'h' => 540, 'fit' => 'crop', 'fm' => 'webp', 'q' => 72])) }}"
         width="720" height="540" alt="" loading="lazy" decoding="async"></div>
  @endif
  <div class="body">
    <span @class(['pk-time', 'is-allday' => $sor->egeszNap()])>
      <b class="pk-t1">{{ Festival::clock($sor->kezd) }}@if ($sor->ejjel()) {{ __('publikus.pk_ejjel') }}@endif</b>@if ($sor->veg !== null)<span class="pk-t2">–{{ Festival::clock($sor->veg) }}</span>@endif
    </span>
    <h3>@include('programok._link', ['p' => $p, 'sor' => $sor])</h3>
    @if ($v)
      <span class="pk-venue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>{{ Nyelv::valaszt($v->name, $v->name_ro) }}</span>
    @endif
    <span class="pk-cmeta">@include('programok._jelzesek', ['p' => $p])</span>
  </div>
</article>
