@use('App\Support\Festival')
{{--
  A TALÁLATI SOROK napok szerint — a 2026-os `renderResults()`: egy nap napszakonként; több nap esetén nap → napszak
  csoportokkal. EGY helyen (tulajdonosi szabály, 2026-11-04): ezt használja a programkereső (#pkResults) ÉS minden lap, ami
  programot listáz (napi nézet, kategória-, helyszínlap) — ugyanaz a kód, ugyanaz a kinézet.
  Paraméterek: $sorok (Collection<ProgramSor>), $egyNap (true = egy nap), $ny; a $szures / $kereso az _kartyak-ig öröklődik.
--}}
@if ($egyNap)
  @include('programok._csoportok', ['sorok' => $sorok])
@else
  @foreach ($sorok->groupBy(fn ($s) => $s->nap()) as $nap => $napiSorok)
    <div class="pk-daytitle"><h2>{{ Festival::fullDayLabel(\Carbon\CarbonImmutable::parse($nap), $ny) }}</h2><span class="pk-gn">{{ $napiSorok->count() }} {{ __('publikus.pk_program_szo') }}</span></div>
    @include('programok._csoportok', ['sorok' => $napiSorok, 'elotag' => 'd' . $nap . '-'])
  @endforeach
@endif
