@extends('bekuldes.layout')
@use('App\Support\Onkentes')
@use('App\Support\PublikusUrlap')

{{-- Az önkéntes jelentkező űrlap (PROMPT-20) — a 2026-os volunteer-api/public/index.html jelölése, ugyanazokkal az osztályokkal és
     sorrendben. A napok, a területek és a korhatár a Beállításokból (Önkéntesek blokk); a bevezető szövege szerkeszthető
     (Tartalom → Aloldalak → Önkéntes-jelentkezés). KISKORÚAK adatai: a dokumentumok a zárt tárba kerülnek. --}}

@php
    $idoszak = Onkentes::idoszak();
    $korhatar = Onkentes::korhatar();
@endphp

@section('title', 'Önkéntes-jelentkezés')
@section('indexelheto', '1')

@push('styles')
<link rel="stylesheet" href="@eszkoz('css/jelentkezes.css')">
@endpush

@section('hero')
  <p class="kicker">{{ PublikusUrlap::html($sz->szoveg('onkentes_kicker')) }}</p>
  <h1>{{ PublikusUrlap::html($sz->szoveg('onkentes_title')) }}</h1>
  @if (! $nyitva)
    {{-- Zárva a hero csak semleges tényt ír — a lezárt-üzenet a kártyán áll, ne kétszer ugyanaz (2026). --}}
    @if ($idoszak)<p>{{ $idoszak }} · {{ \App\Support\Festival::name() }}</p>@endif
  @elseif ($idoszak)
    <p>{{ PublikusUrlap::html($sz->szoveg('onkentes_intro'), ['idoszak' => $idoszak]) }}</p>
  @endif
@endsection

@section('content')
  @if (! $nyitva)
    <div id="zarva" class="card success">
      <div class="big">🙏</div>
      <h2>Jelentkezés lezárult</h2>
      <p class="help">{{ Onkentes::zartUzenet() }}</p>
    </div>
  @else
  <form id="form" class="jel-urlap" method="post" action="{{ route('onkentes.bekuld') }}" enctype="multipart/form-data" novalidate
        data-korhatar="{{ $korhatar }}" data-max-mb="{{ Onkentes::FAJL_MAX_MB }}">
    @csrf
    <div id="msg"></div>

    <div class="card">
      <h2>Rólad</h2>
      <div class="field">
        <label class="req" for="full_name">Teljes név</label>
        <input type="text" id="full_name" name="full_name" autocomplete="name" maxlength="120">
      </div>
      <div class="row">
        <div class="field">
          <label class="req" for="age">Életkor</label>
          <input type="number" id="age" name="age" min="1" max="120" inputmode="numeric">
        </div>
        <div class="field">
          <label class="req" for="email">E-mail</label>
          <input type="email" id="email" name="email" autocomplete="email" maxlength="190">
        </div>
      </div>
      <div class="field">
        <label class="req">Voltál már önkéntes?</label>
        <div class="radio-group" id="wasVolGroup">
          <label class="radio"><input type="radio" name="was_volunteer" value="yes"> Igen</label>
          <label class="radio"><input type="radio" name="was_volunteer" value="no" checked> Nem</label>
        </div>
      </div>
      <div class="field hidden" id="prevField">
        <label for="prev_experience">Hol / mit önkénteskedtél?</label>
        <textarea id="prev_experience" name="prev_experience"></textarea>
      </div>
      <div class="field">
        <label class="req" for="motivation">Miért szeretnél önkéntes lenni?</label>
        <textarea id="motivation" name="motivation"></textarea>
      </div>
    </div>

    <div class="card">
      <h2>Mikor és hol segítenél?</h2>
      <div class="field">
        <label class="req">Mely napokon tudsz jönni?</label>
        <p class="help">Több is választható.</p>
        <div class="checks grid" id="onkNapok">
          @foreach (Onkentes::napok() as $n)
            <label class="chk"><input type="checkbox" class="cb-days" name="available_days[]" value="{{ $n['value'] }}"><span>{{ $n['label'] }}</span></label>
          @endforeach
        </div>
      </div>
      <div class="field">
        <label class="req">Mely területeken segítenél?</label>
        <p class="help">Több is választható.</p>
        <div class="checks" id="areasGroup">
          @foreach (Onkentes::teruletek() as $t)
            <label class="chk"><input type="checkbox" class="cb-areas" name="areas[]" value="{{ $t }}"><span>{{ $t }}</span></label>
          @endforeach
        </div>
      </div>
      <div class="field">
        <label class="req">Van jogosítványod?</label>
        <div class="radio-group" id="licenseGroup">
          <label class="radio"><input type="radio" name="has_license" value="yes"> Igen</label>
          <label class="radio"><input type="radio" name="has_license" value="no" checked> Nem</label>
        </div>
      </div>
    </div>

    <div class="card">
      <h2>Egészség és étkezés</h2>
      <div class="field">
        <label class="req" for="health_notes">Van bármilyen egészségügyi tudnivaló?</label>
        <textarea id="health_notes" name="health_notes" placeholder="Ha nincs, írd: nincs"></textarea>
      </div>
      <div class="field">
        <label>Étkezési érzékenység</label>
        <p class="help">Több is választható; ha nincs, hagyd üresen.</p>
        <div class="checks grid" id="dietaryGroup">
          @foreach (Onkentes::ETREND as $d)
            <label class="chk"><input type="checkbox" class="cb-dietary" name="dietary[]" value="{{ $d }}"><span>{{ $d }}</span></label>
          @endforeach
        </div>
        <label class="chk" id="dietaryOtherChk" style="margin-top:8px">
          <input type="checkbox" id="dietary_other_cb">
          <span>Egyéb</span>
        </label>
        <input type="text" id="dietary_other" name="dietary_other" class="hidden" placeholder="Írd le, mire vagy érzékeny" style="margin-top:8px" maxlength="255">
      </div>
    </div>

    <div class="card">
      <h2>Vészhelyzeti kontakt</h2>
      <div class="row">
        <div class="field">
          <label class="req" for="emergency_name">Név</label>
          <input type="text" id="emergency_name" name="emergency_name" maxlength="120">
        </div>
        <div class="field">
          <label class="req" for="emergency_phone">Telefonszám</label>
          <input type="tel" id="emergency_phone" name="emergency_phone" maxlength="40">
        </div>
      </div>
    </div>

    <div class="card hidden" id="minorCard">
      <h2>{{ $korhatar }} éven aluliaknak</h2>
      <div class="notice">Mivel {{ $korhatar }} éven aluli vagy, kérünk tölts fel <strong>egy szülői beleegyező nyilatkozatot</strong> és egy <strong>kézzel írt motivációs levelet</strong> (fotó vagy PDF).</div>
      <div class="field">
        <label class="req" for="parental_file">Szülői beleegyezés (PDF/JPG/PNG)</label>
        <div class="filebox" id="parentalBox" tabindex="0" role="button"><span id="parentalText">📎 Kattints ide, vagy húzd ide a fájlt (PDF/JPG/PNG)</span></div>
        <input type="file" id="parental_file" accept="application/pdf,image/jpeg,image/png" class="hidden">
      </div>
      <div class="field">
        <label class="req" for="motivation_file">Kézzel írt motivációs levél (PDF/JPG/PNG)</label>
        <div class="filebox" id="motivationBox" tabindex="0" role="button"><span id="motivationText">📎 Kattints ide, vagy húzd ide a fájlt (PDF/JPG/PNG)</span></div>
        <input type="file" id="motivation_file" accept="application/pdf,image/jpeg,image/png" class="hidden">
      </div>
    </div>

    <div class="card">
      <label class="chk" style="border:0;padding:0;background:transparent">
        <input type="checkbox" id="privacy_consent" name="privacy_consent" value="1">
        <span class="req" style="font-weight:600">{{ Onkentes::HOZZAJARULAS }}</span>
      </label>
    </div>

    {{-- méz-bödön: emberi szem elől rejtve; robotok kitöltik --}}
    <div class="hp" aria-hidden="true"><label>Weboldal<input type="text" id="website" name="{{ PublikusUrlap::MEZ_BODON }}" tabindex="-1" autocomplete="off"></label></div>

    <button type="submit" class="btn btn-sun" id="submitBtn">Jelentkezés elküldése</button>
    <p class="foot">A jelentkezésedet a szervezők átnézik, és felveszik veled a kapcsolatot.</p>
  </form>

  <div id="done" class="card success hidden">
    <div class="big">🎉</div>
    <h2>Köszönjük a jelentkezésed!</h2>
    <p class="help">Átnézzük, és hamarosan felvesszük veled a kapcsolatot a megadott e-mail címen.</p>
  </div>
  @endif
@endsection

@push('scripts')
  @if ($nyitva)
    <script src="@eszkoz('js/onkentes.js')" defer></script>
  @endif
@endpush
