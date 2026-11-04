@extends('layouts.app')
@use('App\Support\Festival')
@use('App\Support\Nyelv')
@use('App\Support\ProgramKartya')
@use('App\Support\ProgramSzoveg')
@use('App\Enums\ProgramStatus')
@use('App\Enums\TranslatableField')
@use('App\Enums\TranslationStatus')
@php
    /*
     * A PROGRAM RÉSZLETES LAPJA (/program/<slug>) — a 2026-os components/ProgramDetailView.astro jelölése BETŰRE (CLAUDE.md 2/e,
     * PROMPT-15). A felületről nem vezetünk ide (a programkereső felugró ablaka a fő út): a lap a keresőmotoroknak és a
     * megosztott linkeknek szól, ezért minden útja a programkeresőbe, UGYANERRE a programra visz (…/programok?program=<slug>).
     * Ritmus: a hír-cikk lapé (publikus.css cikk-része + az article.css két szabálya az alap-szerver.css-ben), a saját
     * szabályai: public/css/programlap.css (a 2026-os <style> blokk betűre).
     * A „Naptárba” gomb a naptár-körig tiltott, a szív a kedvencek-körig (mint a programkeresőben); a „Megosztás” működik.
     */
    $ny = Nyelv::aktualis();
    $cim = ProgramSzoveg::mezo($program, TranslatableField::Title);
    $leiras = ProgramSzoveg::mezo($program, TranslatableField::Description);
    $jegy = ProgramSzoveg::mezo($program, TranslatableField::TicketInfo);
    $gepi = $ny === 'ro' && ($cim['gepi'] || $leiras['gepi'] || $jegy['gepi']);
    $ellenorzott = false;
    if ($ny === 'ro' && ! $gepi) {
        $tr = $program->translations->first(fn ($t) => $t->locale === 'ro' && $t->field === TranslatableField::Title)
            ?? $program->translations->first(fn ($t) => $t->locale === 'ro' && $t->field === TranslatableField::Description);
        $ellenorzott = in_array($tr?->status, [TranslationStatus::Reviewed, TranslationStatus::Approved], true);
    }
    $occ = ProgramKartya::idopontok($program);
    $nap = fn ($o) => Festival::weekday(\Carbon\CarbonImmutable::parse($o->date->format('Y-m-d')), $ny);
    $sav = fn ($o) => ProgramKartya::ora($o->start_time) . ($o->end_time ? '–' . ProgramKartya::ora($o->end_time) : '');
    $v = $program->venue;
    $osm = $v ? 'https://www.openstreetmap.org/search?query=' . rawurlencode($v->gps_lat && $v->gps_lng ? $v->gps_lat . ',' . $v->gps_lng : ($v->address ?: $v->name)) : '';
    $kategoriak = collect([$program->category])->filter()->map(fn ($k) => [$k, true])
        ->merge($program->extraCategories->reject(fn ($k) => $k->id === $program->category_id)->map(fn ($k) => [$k, false]));
    $schedNote = trim((string) $program->schedule_note);
    $mutasdSched = $schedNote !== '' && ! ($occ->isNotEmpty() && $occ->every(fn ($o) => trim((string) $o->note) === $schedNote));
    $bekezdesek = collect(preg_split('/\n\s*\n/u', str_replace(["\r\n", "\r"], "\n", $leiras['szoveg'])))->map(fn ($b) => trim($b))->filter()->values();
    $extrak = ProgramKartya::extrak($program->extra_fields);
    $kereso = Nyelv::ut('/programok') . '?program=' . rawurlencode($program->slug);
    $kivonat = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', $leiras['szoveg'])), 200, '');
@endphp
@section('title', $cim['szoveg'])
@section('leiras', $kivonat)
@if ($program->cover)
  @section('og_kep', url($program->cover->getGlideUrl(['w' => 1200, 'h' => 630, 'fit' => 'crop', 'fm' => 'jpg', 'q' => 78])))
@endif
@include('partials.alap-fej')
@push('styles')
<link rel="stylesheet" href="@eszkoz('css/programlap.css')">
@endpush
@push('scripts')
<script src="@eszkoz('js/programlap.js')" defer></script>
@endpush

@section('content')
<div class="article-wrap">
  <article class="article pd-page">
    <a class="article-back" href="{{ $kereso }}"><span aria-hidden="true">←</span> {{ __('publikus.b_vissza_kereso') }}</a>

    <nav class="pd-crumb" aria-label="breadcrumb">
      <a href="{{ Nyelv::ut('/kategoriak') }}">{{ __('publikus.b_morzsa') }}</a>@foreach ($kategoriak as [$k]) › <a href="{{ Nyelv::ut('/kategoria/' . $k->slug) }}">{{ Nyelv::valaszt($k->name_hu, $k->name_ro) }}</a>@endforeach
    </nav>

    <div class="pd-pills">
      @foreach ($kategoriak as [$k, $elso])<span class="{{ $elso ? 'pd-pill' : 'pd-pill pd-pill-extra' }}">{{ Nyelv::valaszt($k->name_hu, $k->name_ro) }}</span>@endforeach
      @if ($ellenorzott)<span class="pd-pill pd-pill-extra">{{ __('publikus.b_ro_ellenorzott') }}</span>@endif
    </div>

    <h1 class="article-title">{{ $cim['szoveg'] }}</h1>
    @if ($gepi)<p class="pd-mt">🌐 {{ __('publikus.b_gepi') }}</p>@endif

    <p class="pd-lead-meta">
      @if ($occ->isNotEmpty())<span><strong>{{ ProgramKartya::rovidNap($occ[0]->date) }}</strong> ({{ $nap($occ[0]) }}) · {{ $sav($occ[0]) }}</span>@endif
      @if ($v)<span>{{ ProgramKartya::helyszinMegj(ProgramKartya::helyszinNev($v)) }}</span>@endif
      @if (filled($program->organizer_names))<span>{{ $program->organizer_names }}</span>@endif
    </p>

    @if ($program->cover)
      {{-- 4:3, a megjelenés arányán vágva (egy vágás) — 2026: ratioCover(…, 4, 3, 1200) --}}
      <div class="article-hero is-43" style="background-image:url('{{ url($program->cover->getGlideUrl(['w' => 1200, 'h' => 900, 'fit' => 'crop', 'fm' => 'webp', 'q' => 76])) }}')" role="img" aria-label="{{ $cim['szoveg'] }}"></div>
      @if (filled($program->cover->photographer_credit))<p class="pd-credit">{{ $program->cover->photographer_credit }}</p>@endif
    @endif

    <div class="pd-facts2">
      <div class="pd-fact2">
        <h2>{{ __('publikus.b_mikor') }}</h2>
        <ul>
          @foreach ($occ as $o)
            <li><strong>{{ ProgramKartya::rovidNap($o->date) }}</strong> ({{ $nap($o) }}) · {{ $sav($o) }}{{ filled($o->note) ? ' · ' . $o->note : '' }}</li>
          @endforeach
        </ul>
        @if ($mutasdSched)<p class="pd-note">{{ $schedNote }}</p>@endif
      </div>
      <div class="pd-fact2">
        <h2>{{ __('publikus.b_hol') }}</h2>
        @if ($v)<p class="pd-venue2">{{ ProgramKartya::helyszinMegj(ProgramKartya::helyszinNev($v)) }}</p>@endif
        @if (filled($v?->address))<p class="pd-addr2">{{ $v->address }}</p>@endif
        @if ($osm)<p><a href="{{ $osm }}" target="_blank" rel="noopener noreferrer">{{ __('publikus.b_utvonal') }} →</a></p>@endif
        <p class="pd-entry"><span class="chip {{ $program->entry_type?->value }}">{{ ProgramKartya::belepesFelirat($program->entry_type?->value ?? 'free') }}@if ($program->entry_type?->value === 'ticketed' && trim($jegy['szoveg']) !== '') · {{ ProgramSzoveg::linkek($jegy['szoveg'], __('publikus.b_jegyvasarlas')) }}@endif</span></p>
      </div>
    </div>

    <div class="article-body">
      @foreach ($bekezdesek as $i => $b)
        <p @class(['lead' => $i === 0])>{{ ProgramSzoveg::linkek($b) }}</p>
      @endforeach
    </div>

    @if (collect($extrak)->where('kind', '!=', 'long')->isNotEmpty())
      <dl class="pd-xgrid2">
        @foreach (collect($extrak)->where('kind', '!=', 'long') as $e)
          <div class="pd-x2">
            <dt>{{ $e['label'] }}</dt>
            <dd>@if ($e['kind'] === 'link')<a href="{{ $e['href'] }}" target="_blank" rel="noopener noreferrer">{{ $e['label'] }} ↗</a>@elseif ($e['kind'] === 'flag')✓@else{{ $e['value'] }}@endif</dd>
          </div>
        @endforeach
      </dl>
    @endif

    @foreach (collect($extrak)->where('kind', 'long') as $e)
      <div class="pd-xlong2">
        <h2>{{ $e['label'] }}</h2>
        <p>{{ $e['value'] }}</p>
      </div>
    @endforeach

    <div class="pd-actions2">
      <a class="art-btn" href="{{ $kereso }}">{{ __('publikus.b_megnyitas_kereso') }} →</a>
      @if ($program->status !== ProgramStatus::Cancelled)
        <button class="pd-ghost" id="pd-ics" type="button" disabled>
          <span class="pd-only-wide">{{ $occ->count() > 1 ? __('publikus.b_naptarba_mind') : __('publikus.b_naptarba') }}</span>
          <span class="pd-only-narrow">{{ __('publikus.b_naptar') }}</span>
        </button>
      @endif
      <button class="pd-ghost" id="pd-share" type="button" data-copied="{{ __('publikus.b_masolva') }}">↗ {{ __('publikus.b_megosztas') }}</button>
      <button class="fav-btn fav-btn-lg" data-id="{{ $program->id }}" type="button" aria-pressed="false" aria-label="{{ __('publikus.b_kedvencekhez') }}" disabled></button>
    </div>
    @if ($program->status !== ProgramStatus::Cancelled && $occ->count() > 1)
      <p class="pd-icsnote">{{ $occ->count() }} {{ __('publikus.b_naptar_tobb') }}</p>
    @endif

    <div class="article-foot"><a class="art-btn" href="{{ $kereso }}"><span aria-hidden="true">←</span> {{ __('publikus.b_vissza_kereso') }}</a></div>
  </article>
</div>
@endsection
