@use('App\Support\Festival')
@use('App\Support\Nyelv')
@use('App\Support\ProgramSzures')
@php
    /*
     * A TALÁLATOK — a 2026-os #pkResults és `renderResults()`: „Most zajlik” + „Következik”; egy nap napszakonként; nap
     * nélkül nap → napszak csoportokkal; üresen javaslat. Ez a htmx-csere FŐ célja (hx-select="#pkResults").
     */
    $mind = new ProgramSzures(ProgramSzures::MIND);
@endphp
<div id="pkResults" data-slot="programs">
@if ($t['most'])
  @php $m = $t['most']; @endphp
  @if (! $m['fesztivalon'] || ($m['zajlik']->isEmpty() && $m['kovetkezik']->isEmpty()))
    <div class="pk-empty">
      @if (! $m['fesztivalon'])
        <h3>{{ $sz->szoveg('programs_now_empty_title') }}</h3>
        <p>@if ($m['elotte']){{ __('publikus.pk_fesztival_kezdete', ['mikor' => Festival::spanLabel($ny)]) }} {{ $sz->szoveg('programs_now_before') }}@else{{ $sz->szoveg('programs_now_after') }}@endif</p>
        <a class="pk-mghost" href="{{ $mind->url() }}" data-pk-torol="*">{{ __('publikus.pk_osszes_torles') }}</a>
      @else
        <h3>{{ $sz->szoveg('programs_empty_title') }}</h3>
        <p>{{ $sz->szoveg('programs_empty_hint') }}</p>
        <a class="pk-mghost" href="{{ $mind->url() }}" data-pk-torol="*">{{ __('publikus.pk_osszes_torles') }}</a>
      @endif
    </div>
  @else
    @foreach (['zajlik' => ['now', 'pk_zajlik'], 'kovetkezik' => ['next', 'pk_kovetkezik']] as $kulcs => [$kod, $felirat])
      @if ($m[$kulcs]->isNotEmpty())
        <div @class(['pk-gtitle', 'is-live' => $kulcs === 'zajlik']) data-pk-group="{{ $kod }}"><h2>{{ __('publikus.' . $felirat) }}</h2><span class="pk-gn">{{ $m[$kulcs]->count() }} {{ __('publikus.pk_program_szo') }}</span></div>
        <div class="pk-grid pk-rows">
          @include('programok._kartyak', ['sorok' => $m[$kulcs]->sortBy(fn ($s) => $s->kezd), 'listaSor' => true])
        </div>
      @endif
    @endforeach
  @endif
@elseif ($t['osszes'] === 0)
  {{-- 2026: nem üres felület, hanem javaslat — melyik szűrő elengedésével marad a legtöbb program. --}}
  <div class="pk-empty">
    <h3>{{ $sz->szoveg('programs_empty_title') }}</h3>
    @if ($lazitas)
      <p>{!! __('publikus.pk_lazit', ['mit' => e(__('publikus.pk_dim_' . $lazitas['mi'])), 'db' => $lazitas['db']]) !!}</p>
    @else
      <p>{{ $sz->szoveg('programs_empty_hint') }}</p>
    @endif
    <a class="pk-mghost" href="{{ $mind->url() }}" data-pk-torol="*">{{ __('publikus.pk_osszes_torles') }}</a>
  </div>
@else
  @include('programok._napok', ['sorok' => $t['sorok'], 'egyNap' => (bool) $szures->datum()])
@endif
</div>
