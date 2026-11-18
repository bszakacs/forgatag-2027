@extends('bekuldes.layout')
@use('App\Support\Olimpia')
@use('App\Support\PublikusUrlap')

{{-- A Családi Olimpia jelentkező űrlapja (PROMPT-20) — a 2026-os olympics-api/public/index.html jelölése, ugyanazokkal az
     osztályokkal és sorrendben. A JS-ben épített részeket (tagsorok, színek, kapacitás) a szerver rajzolja, ugyanazzal a jelöléssel.
     A bevezető és a tudnivalók szövege szerkeszthető (Tartalom → Aloldalak → Családi Olimpia); a mezők és a nyilatkozat a kódban. --}}

@section('title', 'Családi Olimpia – jelentkezés')
@section('indexelheto', '1')

@push('styles')
<link rel="stylesheet" href="@eszkoz('css/jelentkezes.css')">
@endpush

@section('hero')
  <p class="kicker">{{ PublikusUrlap::html($sz->szoveg('olimpia_kicker')) }}</p>
  <h1>{{ PublikusUrlap::html($sz->szoveg('olimpia_title')) }}</h1>
  <p>{{ PublikusUrlap::html($sz->szoveg('olimpia_intro_1')) }}</p>
  @if (Olimpia::napSzoveg())
    <p>{{ PublikusUrlap::html($sz->szoveg('olimpia_intro_2'), ['datum' => Olimpia::napSzoveg()]) }}</p>
  @endif
@endsection

@section('content')
  @if (! $nyitva)
    <div id="zarva" class="card success">
      <div class="big">🙏</div>
      <h2>Jelentkezés lezárult</h2>
      <p class="help">{{ Olimpia::zartUzenet() }}</p>
    </div>
  @else
    <div class="card">
      <h2>{{ $sz->szoveg('olimpia_who_title') }}</h2>
      @foreach (['olimpia_who_1', 'olimpia_who_2', 'olimpia_who_3', 'olimpia_who_4'] as $k)
        <p>{{ PublikusUrlap::html($sz->szoveg($k), ['max' => Olimpia::maxCsapat()]) }}</p>
      @endforeach
    </div>

    <form id="form" class="jel-urlap" method="post" action="{{ route('olimpia.bekuld') }}" novalidate>
      @csrf
      <div id="msg"></div>
      <div id="capacity">@include('jelentkezes._kapacitas')</div>

      <div class="card">
        <h2>A csapat</h2>
        <div class="field">
          <label class="req" for="team_name">Csapatnév</label>
          <input type="text" id="team_name" name="team_name" autocomplete="off" maxlength="120">
        </div>
        <div id="members">
          @for ($i = 1; $i <= Olimpia::TAGOK; $i++)
            <div class="mrow">
              <div>
                <div class="mlab">{{ $i }}. tag neve{{ $i <= Olimpia::KOTELEZO_TAGOK ? ' *' : ' (opcionális)' }}</div>
                <input type="text" id="member{{ $i }}_name" name="member{{ $i }}_name" autocomplete="off" maxlength="120">
              </div>
              <div>
                <div class="mlab">Életkor{{ $i <= Olimpia::KOTELEZO_TAGOK ? ' *' : '' }}</div>
                <input type="number" id="member{{ $i }}_age" name="member{{ $i }}_age" min="1" max="120" inputmode="numeric">
              </div>
            </div>
          @endfor
        </div>
        <p class="help">Az 1–5. tag megadása kötelező, a 6–7. opcionális.</p>
      </div>

      <div class="card">
        <h2>Csapatkapitány</h2>
        <div class="row">
          <div class="field">
            <label class="req" for="captain_phone">Telefonszám</label>
            <input type="tel" id="captain_phone" name="captain_phone" autocomplete="tel" placeholder="+40 7xx xxx xxx" maxlength="40">
          </div>
          <div class="field">
            <label class="req" for="captain_email">E-mail</label>
            <input type="email" id="captain_email" name="captain_email" autocomplete="email" maxlength="190">
          </div>
        </div>
        <p class="help">Ezeken az elérhetőségeken keressük a csapatot a részletekkel.</p>
      </div>

      <div class="card">
        <h2>Csapatszín</h2>
        <p class="help" style="margin-bottom:10px"><span class="warn-lead">FIGYELEM!!!</span> {{ PublikusUrlap::html($sz->szoveg('olimpia_color_note')) }}</p>
        <div class="colors" id="colors">@include('jelentkezes._szinek')</div>
      </div>

      <div class="card">
        <h2>Nyilatkozat</h2>
        <label class="chk decl" id="declChk">
          <input type="checkbox" id="declaration" name="declaration" value="1">
          <span id="declText">{{ Olimpia::NYILATKOZAT }}</span>
        </label>
      </div>

      <div class="hp" aria-hidden="true">
        <label for="website">Website</label>
        <input type="text" id="website" name="{{ PublikusUrlap::MEZ_BODON }}" tabindex="-1" autocomplete="off">
      </div>

      <button class="btn btn-sun" id="submitBtn" type="submit">Jelentkezés elküldése</button>
      <p class="foot">A jelentkezéssel a fenti nyilatkozatot fogadod el a csapat nevében.</p>
    </form>

    <div class="card success hidden" id="done">
      <div class="big" id="doneIcon">🏅</div>
      <h2 id="doneTitle">Megvan, köszönjük!</h2>
      <p class="help" id="doneText"></p>
    </div>
  @endif
@endsection

@push('scripts')
  @if ($nyitva)
    <script src="@eszkoz('js/olimpia.js')" defer></script>
  @endif
@endpush
