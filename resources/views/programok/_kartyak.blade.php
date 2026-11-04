@use('App\Support\Festival')
@use('App\Support\Nyelv')
@use('App\Support\ProgramJelzesek')
@use('App\Support\ProgramSzoveg')
@php
    /*
     * KÁRTYÁK — a 2026-os `card()` (programkereso/script.js) jelölése BETŰRE, szerveroldalon. EGY sablon rajzolja a teljes
     * listát (nem kártyánként egy-egy beillesztett sablon: az a lap kirajzolásának nagyobbik része volt — mérve 2026-10-30).
     * $sorok: ProgramSor-ok; $listaSor: true = a találati lista SORA (`pk-row`), false = a kiemelt sáv kártyája.
     * A kép CSS-változóból jön (2026): a sor a NÉGYZETES változatot (--pk-ths, 208×208), a kártya a 4:3-asat (--pk-th,
     * 720×540) — mindkettő egyszer vágva, a megjelenés arányán. A link `href`-je a program LAPJA (JS nélkül, keresőknek);
     * a keresőben ($szures) htmx-szel a FELUGRÓ ABLAKOT tölti be.
     *
     * 🔴 TULAJDONOSI SZABÁLY (2026-11-04): BÁRHOL listázunk programokat, EZ a sablon listázza (kereső, napi nézet, kategória-
     * és helyszínlap) — nem hasonló, hanem ugyanaz a kód. A kereső lapján kívül ($kereso: a lap programjainak szűrése a
     * keresőben) a link a KERESŐ felugró ablakára visz, ugyanarra a szűrésre — minden a keresőbe vezet vissza.
     * A KÉPHELY MINDIG OTT VAN (tulajdonosi döntés, 2026-11-04): fotó nélkül a 2026-os helyőrző csempe (.pk-noimg) a fotó
     * nélküli program sarok-firkájával (.pc-doodle — az Arculat „Fotó nélküli kártyákon” kapcsolója rejti/mutatja).
     */
    $szures ??= null;
    $kereso ??= null;
    $listaSor ??= true;
    $ejjel = __('publikus.pk_ejjel');
    $mentesCimke = __('publikus.pk_mentes');
    $glowok = ['--doodle-pink', '--doodle-orange', '--doodle-yellow', '--doodle-lime', '--doodle-blue', '--doodle-purple', '--doodle-red'];
@endphp
@foreach ($sorok as $sor)
@php
    $p = $sor->program();
    $v = $p->venue;
    $stilus = '--pk-glow:var(' . $glowok[$p->id % 7] . ')';
    if ($p->cover) {
        $stilus .= ";--pk-th:url('" . url($p->cover->getGlideUrl(['w' => 720, 'h' => 540, 'fit' => 'crop', 'fm' => 'webp', 'q' => 72])) . "')"
            . ";--pk-ths:url('" . url($p->cover->getGlideUrl(['w' => 208, 'h' => 208, 'fit' => 'crop', 'fm' => 'webp', 'q' => 72])) . "')";
    }
    // MEGJEGYZÉS: a program egész menetrendjére szóló és az időpont saját megjegyzése, ismétlés nélkül (2026: lib/notes).
    $mj = collect([$p->schedule_note, $sor->idopont->note])->map(fn ($x) => trim((string) $x))->filter()->unique()->implode(' · ');
    $jel = (string) ProgramJelzesek::html($p);
@endphp
<article @class(['pcard', 'pk-card', 'pk-row' => $listaSor]) style="{{ $stilus }}">
  @if ($p->cover)<div class="thumb"></div>@else<div class="thumb pk-noimg"><svg class="pc-doodle" width="52" height="52" viewBox="0 0 60 60" fill="none" aria-hidden="true"><circle cx="30" cy="30" r="15" style="stroke:var(--accent-b)" stroke-width="6"></circle><circle cx="30" cy="30" r="5" style="fill:var(--accent-b)"></circle><circle cx="52" cy="18" r="3.5" style="fill:var(--accent-b)"></circle><circle cx="55" cy="32" r="3" style="fill:var(--accent-b)"></circle><circle cx="50" cy="45" r="3.5" style="fill:var(--accent-b)"></circle></svg></div>@endif
  {{-- MENTÉS (kedvencek): a gomb helye a 2026-os; a működése a kedvencek körében kapcsol be (ALLAPOT.md). --}}
  <button class="pk-save" type="button" aria-pressed="false" aria-label="{{ $mentesCimke }}" data-save="{{ $p->id }}" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.6-7-9.6A4.4 4.4 0 0 1 12 7a4.4 4.4 0 0 1 7 3.4c0 5-7 9.6-7 9.6z"/></svg></button>
  <div class="body">
    <span @class(['pk-time', 'is-allday' => $sor->egeszNap()])><b class="pk-t1">{{ Festival::clock($sor->kezd) }}{{ $sor->ejjel() ? ' ' . $ejjel : '' }}</b>@if ($sor->veg !== null)<span class="pk-t2">–{{ Festival::clock($sor->veg) }}</span>@else<span class="pk-t2 pk-open" aria-hidden="true">◔</span>@endif</span>
    <h3><a class="pk-cardlink" href="{{ $kereso ? $kereso->mas(['program' => $p->slug])->url() . '&idopont=' . $sor->idopont->id : Nyelv::ut('/program/' . $p->slug) }}"@if ($szures) hx-get="{{ $szures->mas(['program' => $p->slug])->url() }}&amp;idopont={{ $sor->idopont->id }}" hx-target="#pkModal" hx-select="#pkModal" hx-swap="outerHTML" hx-push-url="true"@endif>{{ ProgramSzoveg::cim($p) }}</a></h3>
    <span class="pk-rowmeta"><span class="pk-venue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>{{ $v ? Nyelv::valaszt($v->name, $v->name_ro) : '' }}</span>@if ($jel !== '')<span class="pk-cmeta">{!! $jel !!}</span>@endif</span>
    @if ($mj !== '')
      <span class="pk-note" title="{{ $mj }}">{{ ProgramSzoveg::rovid($mj, 90) }}</span>
    @endif
  </div>
  <span class="pk-go" aria-hidden="true">→</span>
</article>
@endforeach
