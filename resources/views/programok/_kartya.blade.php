@use('App\Support\Festival')
@use('App\Support\Nyelv')
@use('App\Support\ProgramSzoveg')
@php
    /*
     * A 2026-os `card()` (programkereso/script.js) jelölése BETŰRE, szerveroldalon. $sor: ProgramSor; $listaSor: true = a
     * találati lista SORA (`pk-row`), false = a kiemelt sáv kártyája. A kép CSS-változóból jön (2026): a sor a NÉGYZETES
     * változatot (--pk-ths, 208×208), a kártya a 4:3-asat (--pk-th, 720×540) — mindkettő egyszer vágva, a megjelenés arányán.
     * A link `href`-je a program LAPJA (JS nélkül, keresőknek); a keresőben htmx-szel a FELUGRÓ ABLAKOT tölti be.
     */
    $p = $sor->program();
    $v = $p->venue;
    $glow = ['--doodle-pink', '--doodle-orange', '--doodle-yellow', '--doodle-lime', '--doodle-blue', '--doodle-purple', '--doodle-red'][$p->id % 7];
    $stilus = "--pk-glow:var({$glow})";
    if ($p->cover) {
        $stilus .= ";--pk-th:url('" . url($p->cover->getGlideUrl(['w' => 720, 'h' => 540, 'fit' => 'crop', 'fm' => 'webp', 'q' => 72])) . "')"
            . ";--pk-ths:url('" . url($p->cover->getGlideUrl(['w' => 208, 'h' => 208, 'fit' => 'crop', 'fm' => 'webp', 'q' => 72])) . "')";
    }
    $t1 = Festival::clock($sor->kezd) . ($sor->ejjel() ? ' ' . __('publikus.pk_ejjel') : '');
    // MEGJEGYZÉS: a program egész menetrendjére szóló és az időpont saját megjegyzése, ismétlés nélkül (2026: lib/notes).
    $mj = collect([$p->schedule_note, $sor->idopont->note])->map(fn ($x) => trim((string) $x))->filter()->unique()->implode(' · ');
    $szures ??= null;
@endphp
<article @class(['pcard', 'pk-card', 'pk-row' => $listaSor ?? true]) style="{{ $stilus }}">
  @if ($p->cover)<div class="thumb"></div>@endif
  {{-- MENTÉS (kedvencek): a gomb helye a 2026-os; a működése a kedvencek körében kapcsol be (ALLAPOT.md). --}}
  <button class="pk-save" type="button" aria-pressed="false" aria-label="{{ __('publikus.pk_mentes') }}" data-save="{{ $p->id }}" disabled>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.6-7-9.6A4.4 4.4 0 0 1 12 7a4.4 4.4 0 0 1 7 3.4c0 5-7 9.6-7 9.6z"/></svg>
  </button>
  <div class="body">
    <span @class(['pk-time', 'is-allday' => $sor->egeszNap()])><b class="pk-t1">{{ $t1 }}</b>@if ($sor->veg !== null)<span class="pk-t2">–{{ Festival::clock($sor->veg) }}</span>@else<span class="pk-t2 pk-open" aria-hidden="true">◔</span>@endif</span>
    <h3><a class="pk-cardlink" href="{{ Nyelv::ut('/program/' . $p->slug) }}"
       @if ($szures) hx-get="{{ $szures->mas(['program' => $p->slug])->url() }}&amp;idopont={{ $sor->idopont->id }}" hx-target="#pkModal" hx-select="#pkModal" hx-swap="outerHTML" hx-push-url="true" @endif
       >{{ ProgramSzoveg::cim($p) }}</a></h3>
    <span class="pk-rowmeta">
      <span class="pk-venue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>{{ $v ? Nyelv::valaszt($v->name, $v->name_ro) : '' }}</span>
      @php $jel = trim(view('programok._jelzesek', ['p' => $p])->render()); @endphp
      @if ($jel !== '')<span class="pk-cmeta">{!! $jel !!}</span>@endif
    </span>
    @if ($mj !== '')
      <span class="pk-note" title="{{ $mj }}">{{ ProgramSzoveg::rovid($mj, 90) }}</span>
    @endif
  </div>
  <span class="pk-go" aria-hidden="true">→</span>
</article>
