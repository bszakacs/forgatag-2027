@use('App\Support\Festival')
@use('App\Support\Nyelv')
@use('App\Support\ProgramSzoveg')
@use('App\Enums\ProgramStatus')
@php
    /** @var \App\Support\ProgramSor $sor */
    $p = $sor->program();
    $v = $p->venue;
    $megjegyzes = trim((string) ($sor->idopont->note ?: $p->schedule_note));
    // A kiemelés színe a KATEGÓRIÁHOZ kötött (nem véletlen), a firka-palettából — így nem cáfolja a kategóriát.
    $glow = ['orange', 'lime', 'blue', 'pink', 'yellow', 'red', 'purple', 'green'][($p->category_id ?? 0) % 8];
@endphp
{{-- Egy találati SOR (2026: „pk-row”): idő · négyzetes kép · cím + helyszín + jelzések · nyíl. Az egész sor kattintható:
     a keresőben a FELUGRÓ ABLAKOT nyitja (2026), JS nélkül és máshol a program lapjára visz. --}}
<article @class(['pk-card', 'pk-row', 'is-cancelled' => $p->status === ProgramStatus::Cancelled]) style="--pk-glow:var(--doodle-{{ $glow }})">
  <span @class(['pk-time', 'is-allday' => $sor->egeszNap()])>
    <b class="pk-t1">{{ Festival::clock($sor->kezd) }}@if ($sor->ejjel()) {{ __('publikus.pk_ejjel') }}@endif</b>
    @if ($sor->veg !== null)
      <span class="pk-t2">–{{ Festival::clock($sor->veg) }}</span>
    @else
      <span class="pk-t2 pk-open" aria-hidden="true">◔</span>
    @endif
  </span>
  @if ($p->cover)
    {{-- A kép a megjelenés arányán (1:1) készül, egy vágással; a 2× méret a nagy pontsűrűségű kijelzőnek. KÉP NÉLKÜL nincs üres keret (2026). --}}
    <img class="thumb" src="{{ url($p->cover->getGlideUrl(['w' => 128, 'h' => 128, 'fit' => 'crop', 'fm' => 'webp', 'q' => 70])) }}"
         width="64" height="64" alt="" loading="lazy" decoding="async">
  @endif
  <h3>@include('programok._link', ['p' => $p, 'sor' => $sor])</h3>
  <span class="pk-rowmeta">
    @if ($v)
      <span class="pk-venue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>{{ Nyelv::valaszt($v->name, $v->name_ro) }}</span>
    @endif
    <span class="pk-cmeta">@include('programok._jelzesek', ['p' => $p])</span>
  </span>
  @if ($megjegyzes !== '')
    <span class="pk-note" title="{{ $megjegyzes }}">{{ \Illuminate\Support\Str::limit($megjegyzes, 90, '…') }}</span>
  @endif
  <span class="pk-go" aria-hidden="true">→</span>
</article>
