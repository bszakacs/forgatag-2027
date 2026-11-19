@use('App\Support\Festival')
@use('App\Support\Kedvencek')
@use('App\Support\Nyelv')
@php
    /*
     * A „KEDVENC PROGRAMJAIM” LISTÁJA — a 2026-os kedvenceim/script.js render() + osszegzo() jelölése, szerveroldalon (PROMPT-21).
     * A böngésző kéri le a saját listájával (/kedvencek/kartyak?p=…), és a két részt (#kvSumUj, #kvListUj) a lapba teszi.
     * Napok szerint; egy nap a programkereső rácsa (.pk-grid) a kereső kártyáival (tulajdonosi szabály: bárhol listázunk
     * programot, a kereső sablonja listáz), a 2026-os átfedés-jelzéssel. Az átfedés-számláló a 2026-os: programpáronként egyszer.
     * $napok: Kedvencek::napok() (nap → ProgramSor-ok).
     */
    $ny = Nyelv::aktualis();
    $db = $napok->sum(fn ($s) => $s->count());
    $atfedesek = 0;
    $elso = null;
    $atfedes = [];
    foreach ($napok as $sorok) {
        $latott = [];
        foreach ($sorok as $sor) {
            $utk = Kedvencek::atfedesek($sor, $sorok);
            $atfedes[$sor->idopont->id] = $utk;
            foreach ($utk as $q) {
                $par = collect([$sor->program()->id, $q->program()->id])->sort()->implode('|');
                if (! isset($latott[$par])) {
                    $latott[$par] = true;
                    $atfedesek++;
                    $elso ??= $sor->idopont->id;
                }
            }
        }
    }
@endphp
<div id="kvSumUj">@if ($db)<span><b>{{ $db }}</b> {{ __('publikus.kv_program') }}</span><span class="kv-dot">·</span><span><b>{{ $napok->count() }}</b> {{ __('publikus.kv_napon') }}</span>@if ($atfedesek)<span class="kv-dot">·</span><button type="button" class="kv-jump"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5M12 16.5v.5"/><path d="M10.3 3.9 2.6 17.4A2 2 0 0 0 4.3 20.4h15.4a2 2 0 0 0 1.7-3l-7.7-13.5a2 2 0 0 0-3.4 0Z"/></svg><span>{{ $atfedesek }} {{ __('publikus.kv_atfedes_szo') }}</span></button>@endif @endif</div>
<div id="kvListUj">
@foreach ($napok as $nap => $sorok)
  @php $datum = \Carbon\CarbonImmutable::parse($nap); @endphp
  <div class="kv-day" data-day="{{ $nap }}">
    <div class="pk-gtitle"><h2>{{ Festival::fullDayLabel($datum, $ny) }}</h2><span class="pk-gn">{{ Festival::dayLabel($datum, $ny) }} · {{ $sorok->count() }} {{ __('publikus.kv_program') }}</span></div>
    <div class="pk-grid">
      @include('programok._kartyak', ['sorok' => $sorok, 'listaSor' => false, 'kedvenc' => true, 'atfedes' => $atfedes, 'elsoAtfedes' => $elso])
    </div>
  </div>
@endforeach
@if ($db)
  <p class="kv-note">{{ __('publikus.kv_jegyzet') }}</p>
@endif
</div>
