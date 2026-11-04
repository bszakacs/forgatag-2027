@extends('layouts.app')
@use('App\Support\Festival')
@use('App\Support\Nyelv')
@use('App\Support\ProgramKartya')
@use('App\Support\ProgramSzoveg')
@use('App\Enums\ProgramStatus')
@use('App\Enums\TranslatableField')
@php
    /*
     * NAPI NÉZET (/nap/<dátum> és /nap/allando) — a 2026-os components/DailyProgramView.astro jelölése BETŰRE (CLAUDE.md 2/e,
     * PROMPT-15; a korábbi, a programkereső kártyáiból épített változat helyett): napfülek (+ „Állandó”), helyszín-választó +
     * kereső (a lapon belül szűr, public/js/napi.js), darabszám + óra, és a nap sorai idősávval (vagy az „Állandó” fülön a
     * több napos programok kártyái). JS nélkül a teljes nap látszik.
     * A sorrend a fesztiválnap skáláján (a hajnali vágás után — a 23:30-as még aznap, a 00:30-as a nap végén; 2027-es szabály).
     */
    $ny = Nyelv::aktualis();
    $allando = $datum === 'allando';
    $cim = $allando ? __('publikus.b_allando_cim')
        : ProgramKartya::rovidNap($datum) . ' · ' . Festival::weekday(\Carbon\CarbonImmutable::parse($datum), $ny);
    $kereso = fn ($p) => mb_strtolower(collect([ProgramSzoveg::mezo($p, TranslatableField::Title)['szoveg'], ProgramKartya::helyszinNev($p->venue),
        $p->organizer_names])->merge(collect([$p->category])->filter()->merge($p->extraCategories)->map(fn ($k) => Nyelv::valaszt($k->name_hu, $k->name_ro)))
        ->filter()->implode(' '));
    $ar = function ($p) {
        $alap = e(ProgramKartya::belepesFelirat($p->entry_type?->value ?? 'free'));
        $info = ProgramSzoveg::mezo($p, TranslatableField::TicketInfo)['szoveg'];

        return ($p->entry_type?->value === 'ticketed' && trim($info) !== '') ? $alap . ' · ' . ProgramSzoveg::linkek($info, __('publikus.b_jegyvasarlas')) : $alap;
    };
    $megjegyzes = fn (?string $a, ?string $b) => collect([$a, $b])->map(fn ($s) => trim((string) $s))->filter()->unique()->implode(' · ');
    $rovidit = function (string $s, int $max) {
        $t = trim(preg_replace('/\s+/u', ' ', $s));
        if (mb_strlen($t) <= $max) {
            return $t;
        }
        $c = mb_substr($t, 0, $max);
        $i = mb_strrpos($c, ' ');

        return preg_replace('/[,;:.\-–—]+$/u', '', ($i !== false && $i > $max * 0.5) ? mb_substr($c, 0, $i) : $c) . '…';
    };
@endphp
@section('title', $allando ? __('publikus.b_allando') : ProgramKartya::rovidNap($datum))
@include('partials.alap-fej')
@push('scripts')
<script src="@eszkoz('js/napi.js')" defer></script>
@endpush

@section('content')
  <h2 class="section-title">{{ __('publikus.b_napi_program') }}</h2>

  <div class="daytabs">
    @foreach ($napok as $d)
      <a href="{{ Nyelv::ut('/nap/' . $d['id']) }}" class="{{ $d['id'] === $datum ? 'active' : '' }}" data-day="{{ $d['id'] }}">{{ ProgramKartya::rovidNap($d['id']) }}<small>{{ Festival::weekday($d['date'], $ny) }}</small></a>
    @endforeach
    <a href="{{ Nyelv::ut('/nap/allando') }}" class="{{ $allando ? 'active allando-tab' : 'allando-tab' }}">◔ {{ __('publikus.b_allando') }}</a>
  </div>

  <div class="filterbar">
    <select id="venue-filter" aria-label="{{ __('publikus.b_helyszin') }}">
      <option value="">📍 {{ __('publikus.b_minden_helyszin') }}</option>
      @foreach ($fohelyszinek as $v)<option value="{{ $v->slug }}">{{ ProgramKartya::helyszinNev($v) }}</option>@endforeach
    </select>
    <input id="prog-search" type="search" placeholder="{{ __('publikus.b_kereses_hely') }}" autocomplete="off">
    <button id="clear-filters" type="button" class="clear-btn">{{ __('publikus.b_szurok_torlese') }} ✕</button>
  </div>

  <div class="rail-head">
    <strong><span id="prog-count">{{ $allando ? $programok->count() : $sorok->count() }}</span> {{ __('publikus.b_program_szo') }}</strong>
    <span class="rail-sub">· {{ $cim }}</span>
    @unless ($allando)<span class="now-clock" id="now-clock" hidden></span>@endunless
  </div>

  @if ($allando ? $programok->isEmpty() : $sorok->isEmpty())
    <div class="empty">{{ __('publikus.b_nincs_napon') }}</div>
  @elseif ($allando)
    <div class="allando-grid">
      @foreach ($programok as $program)
        <div class="filterable" data-venue="{{ $program->venue?->slug }}" data-vparent="{{ $program->venue?->parent?->slug ?? $program->venue?->slug }}" data-search="{{ $kereso($program) }}">
          @include('partials.programkartya', ['program' => $program])
        </div>
      @endforeach
    </div>
  @else
    <div class="rail" id="rail">
      @foreach ($sorok as $sor)
        @php
            $program = $sor->program();
            $o = $sor->idopont;
            $megj = $megjegyzes($o->note, $program->schedule_note);
        @endphp
        <div class="rail-row filterable" data-venue="{{ $program->venue?->slug }}" data-vparent="{{ $program->venue?->parent?->slug ?? $program->venue?->slug }}"
             data-date="{{ $o->date->format('Y-m-d') }}" data-start="{{ ProgramKartya::ora($o->start_time) }}" data-end="{{ ProgramKartya::ora($o->end_time) }}"
             data-cancelled="{{ $program->status === ProgramStatus::Cancelled ? '1' : '0' }}" data-search="{{ $kereso($program) }}">
          <div class="rail-time"><span>{{ ProgramKartya::ora($o->start_time) }}</span>@if ($o->end_time)<small>{{ ProgramKartya::ora($o->end_time) }}</small>@endif</div>
          <article class="rail-card">
            <button class="fav-btn" data-id="{{ $program->id }}" type="button" aria-pressed="false" aria-label="{{ __('publikus.b_kedvencekhez') }}" disabled></button>
            <span class="rail-status" hidden></span>
            <h3 class="rail-title"><a href="{{ Nyelv::ut('/program/' . $program->slug) }}">{{ ProgramSzoveg::mezo($program, TranslatableField::Title)['szoveg'] }}</a></h3>
            <div class="rail-meta">📍 {{ ProgramKartya::helyszinMegj(ProgramKartya::helyszinNev($program->venue)) }} · {!! $ar($program) !!}</div>
            @if ($megj !== '')
              <div class="rail-note" title="{{ $megj }}">{{ $rovidit($megj, 60) }}</div>
            @endif
          </article>
        </div>
      @endforeach
    </div>
  @endif
  <div class="empty" id="no-match" hidden>{{ __('publikus.b_nincs_szuro') }}</div>

  <script type="application/json" id="dp-labels">{!! json_encode(['now' => __('publikus.b_most'), 'soon' => __('publikus.b_hamarosan'), 'cancelled' => __('publikus.b_elmarad'), 'min' => __('publikus.b_perc'), 'programs' => __('publikus.b_program_szo')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endsection
