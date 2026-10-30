{{--
  Egy nap találatai NAPSZAKONKÉNT (2026-os bontás): délelőtt / délután / este, a végén „egész nap nyitva”.
  A napszakot a fesztiválnap skáláján számoljuk, tehát a 00:30-as kezdés az „este” végére kerül.
  Paraméter: $sorok (Collection<ProgramSor>), $szint (h2 vagy h3 — napfejléc alatt h3).
--}}
@php $szint ??= 'h2'; @endphp
@foreach (['delelott' => 'pk_delelott', 'delutan' => 'pk_delutan', 'este' => 'pk_este', 'egesznap' => 'pk_egesznap'] as $kulcs => $felirat)
  @php $csoport = $sorok->filter(fn ($s) => $s->napszak() === $kulcs); @endphp
  @if ($csoport->isNotEmpty())
    <div class="pk-gtitle">
      <{{ $szint }}>{{ __('publikus.' . $felirat) }}</{{ $szint }}>
      <span class="pk-gn">{{ $csoport->count() }} {{ __('publikus.pk_program_szo') }}</span>
    </div>
    <div class="pk-rows">
      @foreach ($csoport as $sor)
        @include('programok._sor', ['sor' => $sor])
      @endforeach
    </div>
  @endif
@endforeach
