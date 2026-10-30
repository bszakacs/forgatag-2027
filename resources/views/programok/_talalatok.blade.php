@use('App\Support\Festival')
@use('App\Support\Nyelv')
@use('App\Support\ProgramSzures')
@php
    /*
     * A CSERE CÉLTERÜLETE (#pk-talalatok): aktív szűrők, napi kiemelt sáv, találatok — a 2026-os sorrendben. A htmx a
     * teljes lap-válaszból csak ezt emeli ki (hx-select). JavaScript nélkül a szűrő-címkék rendes linkek.
     */
    $aktiv = [];
    if ($szures->most()) {
        $aktiv[] = ['nap', ProgramSzures::MOST, __('publikus.pk_zajlik'), $szures->mas(['nap' => ProgramSzures::MIND])];
    } elseif ($szures->datum()) {
        $aktiv[] = ['nap', $szures->datum(), $napFelirat, $szures->mas(['nap' => ProgramSzures::MIND])];
    }
    foreach ($szures->napszakok as $s) {
        $aktiv[] = ['napszak', $s, $napszakNev[$s], $szures->mas(['napszakok' => array_values(array_diff($szures->napszakok, [$s]))])];
    }
    foreach ($szures->helyszinek as $s) {
        $aktiv[] = ['helyszin', $s, $helyNev[$s] ?? $s, $szures->mas(['helyszinek' => array_values(array_diff($szures->helyszinek, [$s]))])];
    }
    foreach ($szures->kategoriak as $s) {
        $aktiv[] = ['kategoria', $s, $katNev[$s] ?? $s, $szures->mas(['kategoriak' => array_values(array_diff($szures->kategoriak, [$s]))])];
    }
    if ($szures->q !== '') {
        $aktiv[] = ['q', '', '„' . $szures->q . '”', $szures->mas(['q' => ''])];
    }
    $mindTorol = new ProgramSzures(ProgramSzures::MIND);
@endphp
<section id="pk-talalatok" class="pk-talalatok" aria-label="{{ __('publikus.pk_program_szo') }}">
  {{-- AKTÍV SZŰRŐK — üresen is ott van (2026): a felhasználó előre látja, hova kerülnek, és az első címke nem tolja le a listát. --}}
  <div @class(['pk-activebar', 'is-empty' => ! $aktiv]) aria-live="polite">
    @if (! $aktiv)
      <span class="pk-ahint">{{ __('publikus.pk_aktiv_ures') }}</span>
    @else
      <span class="pk-alabel">{{ __('publikus.pk_aktiv') }}</span>
      @foreach ($aktiv as [$mezo, $ertek, $felirat, $nelkule])
        {{-- JS nélkül link a szűrő nélküli címre; JS-sel az űrlapot állítja (az az igazság), és onnan indul a kérés. --}}
        <a class="pk-achip" href="{{ $nelkule->url() }}" data-pk-torol="{{ $mezo }}" data-ertek="{{ $ertek }}"
           aria-label="{{ __('publikus.pk_szuro_torles', ['mit' => $felirat]) }}" @click.prevent="torol($el)">
          {{ $felirat }}
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </a>
      @endforeach
      <a class="pk-achip pk-aclear" href="{{ $mindTorol->url() }}" data-pk-torol="*" @click.prevent="torol($el)">{{ __('publikus.pk_osszes_torles') }}</a>
    @endif
  </div>

  {{-- NAPI KIEMELT SÁV (2026): a kiválasztott nap kiemeltjei a koordinátor sorrendjében; nap nélkül a kezdőnapé.
       Ha arra a napra nincs kiemelés, a sáv nem jelenik meg. A csúszka a közös rail (public/js/rail.js). --}}
  @if ($vanProgram && $kiemelt['sorok']->isNotEmpty())
    <div class="pk-highlight">
      <div class="pk-gtitle">
        <h2>{{ $sz->szoveg('programs_highlight_title_a') }}<span class="brush">{{ $sz->szoveg('programs_highlight_title_b') }}</span></h2>
        <span class="pk-gn">{{ Festival::fullDayLabel(\Carbon\CarbonImmutable::parse($kiemelt['nap']), Nyelv::aktualis()) }}</span>
      </div>
      @include('partials.rail', ['id' => 'pk-kiemelt', 'osztaly' => 'pk-hirail', 'cimke' => $sz->szoveg('programs_highlight_title_a') . $sz->szoveg('programs_highlight_title_b'),
          'tartalom' => $kiemelt['sorok']->map(fn ($s) => view('programok._kartya', ['sor' => $s, 'szures' => $szures])->render())->implode('')])
    </div>
  @endif

  @if (! $vanProgram)
    <div class="pk-empty">
      <h2>{{ $sz->szoveg('programs_noprog_title') }}</h2>
      <p>{{ $sz->szoveg('programs_noprog_text') }}</p>
    </div>
  @elseif ($t['most'])
    @php $m = $t['most']; @endphp
    @if (! $m['fesztivalon'] || ($m['zajlik']->isEmpty() && $m['kovetkezik']->isEmpty()))
      <div class="pk-empty">
        <h2>{{ $sz->szoveg('programs_now_empty_title') }}</h2>
        @if (! $m['fesztivalon'] && $m['elotte'])
          <p>{{ __('publikus.pk_fesztival_kezdete', ['mikor' => Festival::spanLabel(Nyelv::aktualis())]) }} {{ $sz->szoveg('programs_now_before') }}</p>
        @elseif (! $m['fesztivalon'])
          <p>{{ $sz->szoveg('programs_now_after') }}</p>
        @else
          <p>{{ $sz->szoveg('programs_empty_hint') }}</p>
        @endif
        <a class="pk-mghost" href="{{ $mindTorol->url() }}" data-pk-torol="*" @click.prevent="torol($el)">{{ __('publikus.pk_osszes_torles') }}</a>
      </div>
    @else
      @foreach (['zajlik' => 'pk_zajlik', 'kovetkezik' => 'pk_kovetkezik'] as $kulcs => $felirat)
        @if ($m[$kulcs]->isNotEmpty())
          <div @class(['pk-gtitle', 'is-live' => $kulcs === 'zajlik'])>
            <h2>{{ __('publikus.' . $felirat) }}</h2>
            <span class="pk-gn">{{ $m[$kulcs]->count() }} {{ __('publikus.pk_program_szo') }}</span>
          </div>
          <div class="pk-rows">
            @foreach ($m[$kulcs] as $sor)
              @include('programok._sor', ['sor' => $sor])
            @endforeach
          </div>
        @endif
      @endforeach
    @endif
  @elseif ($t['osszes'] === 0)
    {{-- ÜRES ÁLLAPOT (2026): nem üres felület, hanem javaslat — melyik szűrő elengedésével marad a legtöbb program. --}}
    <div class="pk-empty">
      <h2>{{ $sz->szoveg('programs_empty_title') }}</h2>
      @if ($lazitas)
        <p>{!! __('publikus.pk_lazit', ['mit' => e(__('publikus.pk_dim_' . $lazitas['mi'])), 'db' => $lazitas['db']]) !!}</p>
      @else
        <p>{{ $sz->szoveg('programs_empty_hint') }}</p>
      @endif
      <a class="pk-mghost" href="{{ $mindTorol->url() }}" data-pk-torol="*" @click.prevent="torol($el)">{{ __('publikus.pk_osszes_torles') }}</a>
    </div>
  @elseif ($szures->datum())
    @include('programok._csoportok', ['sorok' => $t['sorok']])
  @else
    {{-- Nap nélkül (2026): NAP szerinti csoportok, a napon belül napszakonként — ugyanaz a bontás, mint egy napra szűrve. --}}
    @foreach ($t['sorok']->groupBy(fn ($s) => $s->nap()) as $nap => $napiSorok)
      <div class="pk-daytitle">
        <h2>{{ Festival::fullDayLabel(\Carbon\CarbonImmutable::parse($nap), Nyelv::aktualis()) }}</h2>
        <span class="pk-gn">{{ $napiSorok->count() }} {{ __('publikus.pk_program_szo') }}</span>
      </div>
      @include('programok._csoportok', ['sorok' => $napiSorok, 'szint' => 'h3'])
    @endforeach
  @endif
</section>
