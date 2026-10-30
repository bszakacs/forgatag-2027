@extends('layouts.app')
@use('App\Support\Festival')
@use('App\Support\Nyelv')
@use('App\Support\ProgramSzoveg')
@use('App\Enums\EntryType')
@use('App\Enums\ProgramStatus')
@use('App\Enums\TranslatableField')
@php
    /*
     * A PROGRAM LAPJA (/program/<slug>). Románul a legjobb megjeleníthető fordítás (ProgramSzoveg); ha át nem
     * nézett gépi fordítás látszik, egy apró, halvány jelzés mondja ki (2026-os szabály: tájékoztat, nem riaszt).
     */
    $ny = Nyelv::aktualis();
    $cim = ProgramSzoveg::mezo($program, TranslatableField::Title);
    $leiras = ProgramSzoveg::mezo($program, TranslatableField::Description);
    $jegy = ProgramSzoveg::mezo($program, TranslatableField::TicketInfo);
    $gepi = $cim['gepi'] || $leiras['gepi'] || $jegy['gepi'];
    $v = $program->venue;
    $kategoriak = collect([$program->category])->filter()->merge($program->extraCategories)->unique('id');
    $kivonat = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', $leiras['szoveg'])), 160);
@endphp
@section('title', $cim['szoveg'])
@section('leiras', $kivonat)
@if ($program->cover)
  @section('og_kep', url($program->cover->getGlideUrl(['w' => 1200, 'h' => 630, 'fit' => 'crop', 'fm' => 'jpg', 'q' => 78])))
@endif
@push('styles')
<link rel="stylesheet" href="{{ asset('css/programok.css') }}?v={{ filemtime(public_path('css/programok.css')) }}">
@endpush

@section('content')
<div class="lap-wrap pd">
  <article class="article">
    <a class="article-back" href="{{ Nyelv::ut('/programok') }}"><span aria-hidden="true">←</span> {{ __('publikus.pk_vissza') }}</a>

    @if ($kategoriak->isNotEmpty())
      <div class="pd-pills">
        @foreach ($kategoriak as $k)
          <a @class(['pd-pill', 'pd-pill-extra' => ! $loop->first]) href="{{ Nyelv::ut('/programok') }}?kategoria={{ $k->slug }}">{{ Nyelv::valaszt($k->name_hu, $k->name_ro) }}</a>
        @endforeach
      </div>
    @endif

    <h1 class="article-title">{{ $cim['szoveg'] }}</h1>
    @if ($gepi)
      <p class="pd-mt">🌐 {{ __('publikus.pk_gepi') }}</p>
    @endif

    @if ($program->status === ProgramStatus::Cancelled)
      <p class="pd-status is-cancelled">{{ __('publikus.pk_elmarad') }}</p>
    @elseif ($program->status === ProgramStatus::Changed)
      <p class="pd-status">{{ __('publikus.pk_valtozott') }}</p>
    @endif

    @if ($program->cover)
      {{-- A kép 4:3-as kereten jelenik meg, és pontosan erre az arányra készül (egy vágás). --}}
      <img class="pd-hero" src="{{ url($program->cover->getGlideUrl(['w' => 1200, 'h' => 900, 'fit' => 'crop', 'fm' => 'webp', 'q' => 74])) }}"
           width="1200" height="900" alt="" decoding="async">
      @if (filled($program->cover->photographer_credit))
        <p class="pd-credit">© {{ $program->cover->photographer_credit }}</p>
      @endif
    @endif

    <div class="pd-facts">
      <section class="pd-fact">
        <h2>{{ __('publikus.pk_mikor') }}</h2>
        <ul>
          @foreach ($sorok as $sor)
            <li>
              <a href="{{ Nyelv::ut('/nap/' . $sor->nap()) }}">{{ Festival::fullDayLabel(\Carbon\CarbonImmutable::parse($sor->nap()), $ny) }}</a>,
              <strong>{{ Festival::clock($sor->kezd) }}@if ($sor->veg !== null)–{{ Festival::clock($sor->veg) }}@endif</strong>
              @if ($sor->ejjel()) {{ __('publikus.pk_ejjel') }} @endif
              @if (filled($sor->idopont->note))<span class="pd-note">{{ $sor->idopont->note }}</span>@endif
            </li>
          @endforeach
        </ul>
        @if (filled($program->schedule_note))
          <p class="pd-note">{{ $program->schedule_note }}</p>
        @endif
      </section>

      <section class="pd-fact">
        <h2>{{ __('publikus.pk_hol') }}</h2>
        @if ($v)
          <p class="pd-venue">{{ Nyelv::valaszt($v->name, $v->name_ro) }}</p>
          @if ($v->parent)
            <p>{{ Nyelv::valaszt($v->parent->name, $v->parent->name_ro) }}</p>
          @endif
          @if (filled($v->address))
            <p>{{ Nyelv::valaszt($v->address, $v->address_ro) }}</p>
          @endif
        @endif
        <p class="pd-entry">
          <span class="pd-label">{{ __('publikus.pk_belepes') }}:</span>
          @if ($program->entry_type === EntryType::Ticketed)
            {{ __('publikus.pk_jegy') }}
          @elseif ($program->entry_type === EntryType::Donation)
            {{ __('publikus.pk_adomany') }}
          @else
            {{ __('publikus.pk_ingyenes') }}
          @endif
        </p>
        @if ($program->entry_type === EntryType::Ticketed && trim($jegy['szoveg']) !== '')
          <div class="pd-ticket">{{ ProgramSzoveg::html($jegy['szoveg']) }}</div>
        @endif
      </section>
    </div>

    <div class="article-body pd-desc">
      {{ ProgramSzoveg::html($leiras['szoveg']) }}
    </div>

    @if (filled($program->organizer_names))
      <p class="pd-org"><span class="pd-label">{{ __('publikus.pk_szervezo') }}:</span> {{ $program->organizer_names }}</p>
    @endif

    <div class="pd-actions">
      @if ($v)
        <a class="pd-ghost" href="{{ Nyelv::ut('/programok') }}?helyszin={{ $v->slug }}">{{ __('publikus.pk_helyszin_tovabbi') }} →</a>
      @endif
      @if ($program->category)
        <a class="pd-ghost" href="{{ Nyelv::ut('/programok') }}?kategoria={{ $program->category->slug }}">{{ __('publikus.pk_kategoria_tovabbi') }} →</a>
      @endif
    </div>
    <div class="article-foot"><a class="art-btn" href="{{ Nyelv::ut('/programok') }}"><span aria-hidden="true">←</span> {{ __('publikus.pk_vissza') }}</a></div>
  </article>
</div>
@endsection
