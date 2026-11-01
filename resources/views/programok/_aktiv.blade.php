@use('App\Support\Festival')
@use('App\Support\ProgramSzures')
@php
    /*
     * AKTÍV SZŰRŐK — a 2026-os `renderActive()` jelölése és SORRENDJE: „Most zajlik”, nap, »keresés«, napszakok, helyszínek,
     * kategóriák, végül „Összes törlése”. Üresen is ott van (is-empty, súgó-szöveggel). JS nélkül a címkék linkek (a szűrő
     * nélküli címre), JS-sel az ŰRLAPOT állítják (public/js/programkereso.js). Frissítés: id szerint (hx-swap-oob).
     */
    $aktiv = [];
    if ($szures->most()) {
        $aktiv[] = ['nap', ProgramSzures::MOST, __('publikus.pk_zajlik'), $szures->mas(['nap' => ProgramSzures::MIND])];
    }
    if ($szures->datum()) {
        $aktiv[] = ['nap', $szures->datum(), Festival::dayLabel(\Carbon\CarbonImmutable::parse($szures->datum()), $ny), $szures->mas(['nap' => ProgramSzures::MIND])];
    }
    if ($szures->q !== '') {
        $aktiv[] = ['q', '', '»' . $szures->q . '«', $szures->mas(['q' => ''])];
    }
    foreach ($szures->napszakok as $s) {
        $aktiv[] = ['napszak', $s, __('publikus.pk_' . $s), $szures->mas(['napszakok' => array_values(array_diff($szures->napszakok, [$s]))])];
    }
    foreach ($szures->helyszinek as $s) {
        $aktiv[] = ['helyszin', $s, $helyNev[$s] ?? $s, $szures->mas(['helyszinek' => array_values(array_diff($szures->helyszinek, [$s]))])];
    }
    foreach ($szures->kategoriak as $s) {
        $aktiv[] = ['kategoria', $s, $katNev[$s] ?? $s, $szures->mas(['kategoriak' => array_values(array_diff($szures->kategoriak, [$s]))])];
    }
@endphp
<div @class(['pk-activebar', 'is-empty' => ! $aktiv]) id="pkActive" aria-live="polite" hx-swap-oob="true">@if (! $aktiv)<span class="pk-ahint">{{ __('publikus.pk_aktiv_ures') }}</span>@else<span class="pk-alabel">{{ __('publikus.pk_aktiv') }}</span>@foreach ($aktiv as [$mezo, $ertek, $felirat, $nelkule])<a class="pk-achip" href="{{ $nelkule->url() }}" data-pk-torol="{{ $mezo }}" data-ertek="{{ $ertek }}" aria-label="{{ __('publikus.pk_szuro_torles', ['mit' => $felirat]) }}"><span>{{ $felirat }}</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></a>@endforeach<a class="pk-achip pk-aclear" href="{{ (new ProgramSzures(ProgramSzures::MIND))->url() }}" data-pk-torol="*">{{ __('publikus.pk_osszes_torles_rovid') }}</a>@endif</div>
