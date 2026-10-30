@use('App\Support\Festival')
@use('App\Support\Nyelv')
@use('App\Support\ProgramSzoveg')
@use('App\Enums\TranslatableField')
@php
    /*
     * A FELUGRÓ ABLAK (2026: programkereso/modal.html + lib/program-modal.js) — a listából EZ nyílik, nem a program lapja.
     * A címsorban `?program=<slug>` (megosztható: külön megnyitva a kereső nyílik, rajta az ablak). A tartalmat a szerver
     * rajzolja; a kártya htmx-szel CSAK ezt az elemet kéri le és cseréli (#pk-modal), a bezárás ugyanígy — így a „vissza”
     * gomb is bezárja. JS nélkül a kártya a program lapjára visz.
     * Ami még NINCS benne (később épül, lásd ALLAPOT.md): „Mutasd a térképen” (térkép-kör), „Naptárba” (naptár-kör),
     * „Mentés” (kedvencek-kör).
     */
    $p = $felugro['program'] ?? null;
    $zarUrl = $szures->mas([])->url();
@endphp
<div class="pk-modal" id="pk-modal" role="dialog" aria-modal="true" aria-labelledby="pk-mcim" @if (! $p) hidden @endif
     x-data x-init="$nextTick(() => { if (! $el.hidden) { document.documentElement.classList.add('pk-modal-nyitva'); $el.querySelector('.pk-close')?.focus(); } else { document.documentElement.classList.remove('pk-modal-nyitva'); } })"
     @keydown.escape.window="if (! $el.hidden) $refs.zar.click()">
  @if ($p)
    @php
        /** @var \App\Support\ProgramSor $sor */
        $sor = $felugro['sor'];
        $cim = ProgramSzoveg::mezo($p, TranslatableField::Title);
        $leiras = ProgramSzoveg::mezo($p, TranslatableField::Description);
        $jegy = ProgramSzoveg::mezo($p, TranslatableField::TicketInfo);
        $v = $p->venue;
        $ido = $sor ? Festival::fullDayLabel(\Carbon\CarbonImmutable::parse($sor->nap()), Nyelv::aktualis()) . ' · '
            . Festival::clock($sor->kezd) . ($sor->veg !== null ? '–' . Festival::clock($sor->veg) : '')
            . ($sor->ejjel() ? ' ' . __('publikus.pk_ejjel') : '') . ($sor->egeszNap() ? ' · ' . __('publikus.pk_folyamatosan') : '') : '';
        $megjegyzes = trim((string) ($sor?->idopont->note ?: $p->schedule_note));
        $fellepo = trim((string) ($p->extra_fields['performer_name'] ?? $p->extra_fields['book_author'] ?? ''));
    @endphp
    <a class="pk-scrim" href="{{ $zarUrl }}" tabindex="-1" aria-hidden="true" @click.prevent="$refs.zar.click()"></a>
    <div class="pk-dialog">
      <a class="pk-close" x-ref="zar" href="{{ $zarUrl }}" aria-label="{{ __('publikus.bezaras') }}"
         hx-get="{{ $zarUrl }}" hx-target="#pk-modal" hx-select="#pk-modal" hx-swap="outerHTML" hx-push-url="true">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </a>
      <div class="pk-dialog-scroll">
        {{-- A KÉP 4:3 (a fájl ezen az arányon készül), a program adatai a kép ALJÁN, saját halványuló sávban. Kép nélkül
             ugyanez az elem normál szövegsorként jelenik meg — nincs üres képkeret. --}}
        <div @class(['pk-mimg', 'pk-noimg' => ! $p->cover])
             @if ($p->cover) style="background-image:url('{{ url($p->cover->getGlideUrl(['w' => 720, 'h' => 540, 'fit' => 'crop', 'fm' => 'webp', 'q' => 74])) }}')" @endif>
          <div class="pk-mover">
            <span>{{ $ido }}</span>
            @if ($v)
              <span class="pk-mvenue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/></svg><span class="pk-mvnev">{{ str_replace(', ', ' › ', Nyelv::valaszt($v->name, $v->name_ro)) }}</span></span>
            @endif
          </div>
        </div>
        <div class="pk-mbody">
          @if ($megjegyzes !== '')
            <p class="pk-mnote">{{ $megjegyzes }}</p>
          @endif
          <h2 id="pk-mcim">{{ $cim['szoveg'] }}</h2>
          @if ($p->category)
            <p class="pk-mseries">{{ Nyelv::valaszt($p->category->name_hu, $p->category->name_ro) }}</p>
          @endif
          <div class="pk-mdesc">{{ ProgramSzoveg::html($leiras['szoveg']) }}</div>
          @if ($cim['gepi'] || $leiras['gepi'] || $jegy['gepi'])
            <span class="pk-mmt">{{ $sz->szoveg('programs_modal_mt') }}</span>
          @endif
          <dl class="pk-mmeta">
            <dt>{{ $sz->szoveg('programs_modal_organizer') }}</dt><dd>{{ filled($p->organizer_names) ? $p->organizer_names : '—' }}</dd>
            @if ($fellepo !== '')
              <dt>{{ $sz->szoveg('programs_modal_guests') }}</dt><dd>{{ $fellepo }}</dd>
            @endif
          </dl>
          <div class="pk-mflags">@include('programok._jelzesek', ['p' => $p, 'teljes' => true])</div>
          <div class="pk-mactions">
            <button class="pk-mghost" type="button" x-data="{ kesz: false }"
                    @click="pkMegoszt(@js($cim['szoveg']), location.href).then(m => { kesz = m === 'masolva'; if (kesz) setTimeout(() => kesz = false, 1600) })">
              <svg viewBox="0 0 24 24" stroke-linecap="round" aria-hidden="true"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7M12 15V3M8 7l4-4 4 4"/></svg>
              <span x-text="kesz ? @js('✓ ' . __('publikus.pk_masolva')) : @js($sz->szoveg('programs_modal_share'))">{{ $sz->szoveg('programs_modal_share') }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  @endif
</div>
