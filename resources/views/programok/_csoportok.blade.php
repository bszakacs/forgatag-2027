{{--
  Egy nap találatai NAPSZAKONKÉNT — a 2026-os `dayparts()` + `groupBlock()` jelölése: délelőtt / délután / este, a végén
  „egész nap nyitva”. A napszakot a fesztiválnap skáláján számoljuk, tehát a 00:30-as kezdés az „este” végére kerül.
  Paraméter: $sorok (Collection<ProgramSor>), $elotag (a csoport-kulcs előtagja, mint 2026-ban: 'd<nap>-').
--}}
@php $elotag ??= ''; @endphp
@foreach (['delelott' => ['morning', 'pk_delelott'], 'delutan' => ['afternoon', 'pk_delutan'], 'este' => ['evening', 'pk_este'], 'egesznap' => ['allday', 'pk_egesznap']] as $kulcs => [$kod, $felirat])
  @php $csoport = $sorok->filter(fn ($s) => $s->napszak() === $kulcs)->sortBy(fn ($s) => $s->kezd); @endphp
  @if ($csoport->isNotEmpty())
    <div class="pk-gtitle" data-pk-group="{{ $elotag . $kod }}"><h2>{{ __('publikus.' . $felirat) }}</h2><span class="pk-gn">{{ $csoport->count() }} {{ __('publikus.pk_program_szo') }}</span></div>
    <div class="pk-grid pk-rows">
      @include('programok._kartyak', ['sorok' => $csoport, 'listaSor' => true])
    </div>
  @endif
@endforeach
