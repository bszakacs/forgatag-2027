@use('App\Support\Festival')
@use('App\Support\Nyelv')
@use('App\Support\ProgramJelzesek')
@use('App\Support\ProgramSzoveg')
@use('App\Enums\ProgramStatus')
@use('App\Enums\TranslatableField')
@php
    /*
     * A FELUGRÓ ABLAK — a 2026-os programkereso/modal.html jelölése BETŰRE, a kitöltést (lib/program-modal.js `nyit`) a
     * szerver végzi. A listából EZ nyílik; a címsorban `?program=<slug>` (megosztható). A kártya htmx-szel CSAK ezt az
     * elemet kéri le (#pkModal), a bezárás ugyanígy. Egyetlen jelölési eltérés: a leírás tárolója `div` (2026: `p`,
     * amibe a JS tett `p`-ket — szerveroldali HTML-ben `p` nem lehet `p`-ben).
     * A négy gomb a 2026-os helyén van; a „Mutasd a térképen” és a „Megosztás” működik, a „Naptárba” a naptár-, a
     * „Mentés” a kedvencek-körben kapcsol be (addig tiltva — ALLAPOT.md).
     */
    $p = $felugro['program'] ?? null;
    $zarUrl = $szures->mas([])->url();
@endphp
<div class="pk-modal" id="pkModal" @if (! $p) hidden @endif role="dialog" aria-modal="true" aria-labelledby="pkMTitle" data-comp="program-modal">
@if ($p)
  @php
      /** @var ?\App\Support\ProgramSor $sor */
      $sor = $felugro['sor'];
      $cim = ProgramSzoveg::mezo($p, TranslatableField::Title);
      $leiras = ProgramSzoveg::mezo($p, TranslatableField::Description);
      $jegy = ProgramSzoveg::mezo($p, TranslatableField::TicketInfo);
      $v = $p->venue;
      // 2026: DAYS.full · timeLabel(o) (a „(éjjel)” a teljes sáv után) · „folyamatosan” az egész napos tételnél.
      $ido = $sor ? Festival::fullDayLabel(\Carbon\CarbonImmutable::parse($sor->nap()), $ny) . ' · '
          . Festival::clock($sor->kezd) . ($sor->veg !== null ? '–' . Festival::clock($sor->veg) : '')
          . ($sor->ejjel() ? ' ' . __('publikus.pk_ejjel') : '') . ($sor->egeszNap() ? ' · ' . __('publikus.pk_folyamatosan') : '') : '';
      $mj = collect([$p->schedule_note, $sor?->idopont->note])->map(fn ($x) => trim((string) $x))->filter()->unique()->implode(' · ');
      $fellepo = trim((string) ($p->extra_fields['performer_name'] ?? $p->extra_fields['book_author'] ?? ''));
      $gepi = $cim['gepi'] || $leiras['gepi'] || $jegy['gepi'];
  @endphp
  <a class="pk-scrim" href="{{ $zarUrl }}" data-pk-close tabindex="-1" aria-hidden="true"></a>
  <div class="pk-dialog">
    <a class="pk-close" href="{{ $zarUrl }}" data-pk-close aria-label="{{ __('publikus.bezaras') }}"
       hx-get="{{ $zarUrl }}" hx-target="#pkModal" hx-select="#pkModal" hx-swap="outerHTML" hx-push-url="true">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </a>
    <div class="pk-dialog-scroll">
      <div @class(['pk-mimg', 'pk-noimg' => ! $p->cover]) id="pkMImg" data-img="program_detail_image"
           @if ($p->cover) style="background-image:url('{{ url($p->cover->getGlideUrl(['w' => 720, 'h' => 540, 'fit' => 'crop', 'fm' => 'webp', 'q' => 72])) }}')" @endif>
        <div class="pk-mover">
          <span id="pkMTime">{{ $ido }}</span>
          <span class="pk-mvenue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/></svg><span id="pkMVenue">{{ $v ? str_replace(', ', ' › ', Nyelv::valaszt($v->name, $v->name_ro)) : '' }}</span></span>
        </div>
      </div>
      <div class="pk-mbody">
        <p class="pk-mnote" id="pkMNote" @if ($mj === '') hidden @endif>{{ $mj }}</p>
        <h2 id="pkMTitle">{{ $cim['szoveg'] }}</h2>
        <p class="pk-mseries" id="pkMSeries">{{ $p->category ? Nyelv::valaszt($p->category->name_hu, $p->category->name_ro) : '' }}</p>
        <div class="pk-mdesc" id="pkMDesc">{{ ProgramSzoveg::html($leiras['szoveg']) }}</div>
        <span class="pk-mmt" id="pkMMt" @if (! $gepi) hidden @endif>{{ $sz->szoveg('programs_modal_mt') }}</span>
        <dl class="pk-mmeta">
          <dt>{{ $sz->szoveg('programs_modal_organizer') }}</dt><dd id="pkMOrg">{{ filled($p->organizer_names) ? $p->organizer_names : '—' }}</dd>
          <dt id="pkMGuestsDt" @if ($fellepo === '') hidden @endif>{{ $sz->szoveg('programs_modal_guests') }}</dt><dd id="pkMGuests" @if ($fellepo === '') hidden @endif>{{ $fellepo }}</dd>
        </dl>
        <div class="pk-mflags" id="pkMFlags">{{ ProgramJelzesek::html($p, true) }}</div>
        <div class="pk-mactions">
          {{-- 2026: csak ha a helyszín SZEREPEL a térképen — a térkép JS-e dönt (public/js/terkep.js), addig rejtett. --}}
          <button class="pk-mghost" type="button" id="pkMMap" data-venue="{{ $v?->slug }}" hidden disabled>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 3 3 5.5v15L9 18l6 3 6-2.5v-15L15 6 9 3v15"/></svg>
            <span class="pk-only-wide">{{ $sz->szoveg('programs_modal_map_cta') }}</span>
            <span class="pk-only-narrow">{{ $sz->szoveg('programs_modal_map_cta_short') }}</span>
          </button>
          {{-- 2026: lemondott program nem kerül naptárba — ott a gomb rejtett. --}}
          <button class="pk-mghost" type="button" id="pkMIcs" @if ($p->status === ProgramStatus::Cancelled) hidden @endif disabled>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M7 3v3M17 3v3M4 8h16M5 6h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1z"/></svg>
            <span class="pk-only-wide">{{ $sz->szoveg('programs_modal_ics') }}</span>
            <span class="pk-only-narrow">{{ $sz->szoveg('programs_modal_ics_short') }}</span>
          </button>
          <button class="pk-mghost" type="button" id="pkMSave" aria-pressed="false" disabled>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.6-7-9.6A4.4 4.4 0 0 1 12 7a4.4 4.4 0 0 1 7 3.4c0 5-7 9.6-7 9.6z"/></svg>
            <span>{{ $sz->szoveg('programs_modal_save') }}</span>
          </button>
          <button class="pk-mghost" type="button" id="pkMShare" data-cim="{{ $cim['szoveg'] }}" data-masolva="✓ {{ __('publikus.pk_masolva') }}">
            <svg viewBox="0 0 24 24" stroke-linecap="round" aria-hidden="true"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7M12 15V3M8 7l4-4 4 4"/></svg>
            <span>{{ $sz->szoveg('programs_modal_share') }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
@endif
</div>
