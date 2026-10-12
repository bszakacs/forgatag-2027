@extends('bekuldes.layout')

{{--
  A programbeküldő űrlap — egy hosszú lap, nem varázsló; a hibák egy listában, a lap tetején.
  Mezők, feliratok, segédszövegek: a 2026-os submit-api/public/index.html-ből, változatlanul.
  Ugyanez a lap szolgálja a beküldő javítását is (jóváhagyásig): $program !== null.
--}}
@php
    /** @var \App\Models\Category $kategoria */
    $e = $ertekek; // a mezők kezdőértékei (új beküldésnél üres)
    $ora = fn (?string $t) => $t ? substr($t, 0, 2) : '';
    $perc = fn (?string $t) => $t ? substr($t, 3, 2) : '';
@endphp

@section('title', $program ? 'Program javítása' : 'Programbeküldés')

@section('hero')
  <div class="eyebrow" id="festSpan">{{ $config['festivalSpan'] }} · Marosvásárhely</div>
  <p class="kicker">Hozd a <em>programod</em> a Forgatagba</p>
  <h1 id="catTitle">{{ $kategoria->name_hu }}</h1>
  <p id="catSub">{{ $program ? 'A beküldött programod javítása – a jóváhagyásig módosíthatod.' : 'Töltsd ki a program adatait – a kategóriád: ' . $kategoria->name_hu . '.' }}</p>
@endsection

@section('content')
  <div class="navrow">
    <a class="btn btn-ghost btn-sm" href="{{ route('bekuldes.sajat') }}">Saját beküldéseim</a>
  </div>

  <form id="form" method="post" action="{{ $action }}" enctype="multipart/form-data" novalidate>
    @csrf
    <div id="msg" aria-live="assertive"></div>

    <div class="card">
      <h2>A program</h2>
      <div class="field">
        <label class="req" for="title">Program címe</label>
        <input type="text" id="title" name="title" value="{{ $e['title'] }}" autocomplete="off" required>
      </div>
      <div class="field">
        <label class="req" for="organizer_names">Szervező(k)</label>
        <input type="text" id="organizer_names" name="organizer_names" value="{{ $e['organizer_names'] }}" autocomplete="off" required>
        <p class="help">Több szervező vesszővel elválasztva (pl. Csicsergő Egyesület, Teleki Téka). Ez jelenik meg a program „Szervező" adataként.</p>
      </div>
      <div class="field">
        <label class="req">Időpont</label>
        <div class="notice">
          <strong>Fontos:</strong> csak a <strong>főszervezővel előzetesen egyeztetett időpontot</strong> add meg.
          Ha ettől eltérő időpontot vagy napot írsz be, a programot vissza kell küldenünk egyeztetésre,
          és nem kerül be a programba. Ha bizonytalan vagy, egyeztess a szervezővel a beküldés előtt.
        </div>
        <div class="row time-row">
          @foreach (['start' => 'Kezdés', 'end' => 'Vége'] as $mezo => $felirat)
            <div>
              <label @class(['req' => $mezo === 'start']) for="{{ $mezo }}_h">{{ $felirat }}</label>
              <div class="timepick">
                <select id="{{ $mezo }}_h" aria-label="{{ $felirat }} – óra">
                  <option value="">óra</option>
                  @for ($h = 0; $h < 24; $h++)
                    @php $v = str_pad((string) $h, 2, '0', STR_PAD_LEFT); @endphp
                    <option value="{{ $v }}" @selected($ora($e[$mezo . '_time']) === $v)>{{ $v }}</option>
                  @endfor
                </select>
                <span class="tsep">:</span>
                <select id="{{ $mezo }}_m" aria-label="{{ $felirat }} – perc">
                  <option value="">perc</option>
                  @for ($m = 0; $m < 60; $m += 5)
                    @php $v = str_pad((string) $m, 2, '0', STR_PAD_LEFT); @endphp
                    <option value="{{ $v }}" @selected($perc($e[$mezo . '_time']) === $v)>{{ $v }}</option>
                  @endfor
                </select>
              </div>
              @if ($mezo === 'end')
                <p class="help">{{ \App\Support\SubmissionForm::dayCutHelp() }}</p>
              @endif
            </div>
          @endforeach
        </div>
        <div class="field" style="margin-top:12px;margin-bottom:0">
          <label for="schedule_note">Megjegyzés</label>
          <input type="text" id="schedule_note" name="schedule_note" value="{{ $e['schedule_note'] }}" placeholder="pl. 12–13 ebédszünet (opcionális)">
          <p class="help">Ha van, minden napra érvényes.</p>
        </div>
      </div>
      <div class="field">
        <label class="req">Ismétlődő napok</label>
        <p class="help">Mely fesztiválnapokon fut a program ugyanebben az időpontban? Pipálj ki legalább egyet.</p>
        <div class="radio-group" id="daysGroup">
          @foreach ($config['festivalDays'] as $nap)
            @php $be = in_array($nap['value'], $e['days'], true); @endphp
            <label @class(['radio', 'sel' => $be])><input type="checkbox" class="day-cb" name="recurrence_days[]" value="{{ $nap['value'] }}" @checked($be)><span> {{ $nap['label'] }}</span></label>
          @endforeach
        </div>
        <p class="help" id="daysCount"></p>
      </div>
      <div class="field">
        <label class="req" for="venue">Helyszín</label>
        <select id="venue" name="venue" required>
          <option value="">— Válassz helyszínt —</option>
          @foreach ($helyszinek as $v)
            <option value="{{ $v->id }}" @selected((string) $e['venue'] === (string) $v->id)>{{ $v->parent ? $v->parent->name . ' → ' : '' }}{{ $v->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field">
        <label class="req" for="description">Rövid leírás</label>
        <textarea id="description" name="description" maxlength="{{ \App\Support\SubmissionForm::DESCRIPTION_MAX }}">{{ $e['description'] }}</textarea>
        <div class="counter" id="descCount"></div>
      </div>
    </div>

    <div class="card">
      <h2>Belépés</h2>
      <div class="field">
        <label class="req">Belépés típusa</label>
        <div class="radio-group" id="entryGroup">
          @foreach (\App\Enums\EntryType::cases() as $t)
            <label @class(['radio', 'sel' => $e['entry_type'] === $t->value])><input type="radio" name="entry_type" value="{{ $t->value }}" @checked($e['entry_type'] === $t->value)> {{ $t->label() }}</label>
          @endforeach
        </div>
      </div>
      <div @class(['field', 'hidden' => $e['entry_type'] !== 'ticketed']) id="ticketField">
        <label for="ticket_info">Jegyinformáció</label>
        <input type="text" id="ticket_info" name="ticket_info" value="{{ $e['ticket_info'] }}" placeholder="Pl. 30 lej, kapható a helyszínen – vagy tegyél be egy linket">
        <p class="help">Linket is beírhatsz (pl. jegyfoglaló oldal).</p>
      </div>
    </div>

    @if ($config['category']['extraFields'])
      <div id="extraCard" class="card">
        <h2 id="extraTitle">A kategória extra adatai</h2>
        <div id="extras">
          @foreach ($config['category']['extraFields'] as $f)
            @php $ertek = $e['extra'][$f['key']] ?? null; @endphp
            <div class="field" data-key="{{ $f['key'] }}" @if (isset($f['requiredIf'])) data-required-if="{{ $f['requiredIf'] }}" @endif>
              @if ($f['type'] === 'checkbox')
                <label class="check"><input type="checkbox" id="x_{{ $f['key'] }}" name="extra[{{ $f['key'] }}]" value="1" @checked($ertek)><span style="font-weight:600">{{ $f['label'] }}</span></label>
              @else
                <label @class(['req' => $f['required']]) for="x_{{ $f['key'] }}">{{ $f['label'] }}</label>
                @if ($f['type'] === 'longtext')
                  <textarea id="x_{{ $f['key'] }}" name="extra[{{ $f['key'] }}]" data-clean="multi">{{ $ertek }}</textarea>
                @else
                  <input id="x_{{ $f['key'] }}" name="extra[{{ $f['key'] }}]" value="{{ $ertek }}"
                    type="{{ $f['type'] === 'number' ? 'number' : ($f['type'] === 'url' ? 'url' : 'text') }}"
                    @if ($f['type'] === 'text') data-clean="single" @endif>
                @endif
              @endif
              @if (! empty($f['help']))
                <p class="help">{{ $f['help'] }}</p>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    @endif

    <div class="card">
      <h2>Fotó a programhoz</h2>
      <p class="help" id="photoHelp">{{ $program ? 'A beküldött fotó megmarad, ha nem cserélsz.' : 'Kötelező. Ajánlott a tavalyi eseményről készült, jó minőségű kép.' }}</p>
      <div @class(['photo-drop', 'ok' => $fotoUrl]) id="photoDrop" role="button" tabindex="0">
        <div id="photoText">{{ $fotoUrl ? '✓ A beküldött fotó. Kattints a cseréhez.' : '📷 Kattints ide, vagy húzz ide egy képet (JPG/PNG/WebP)' }}</div>
        <img id="photoPrev" @class(['photo-prev', 'hidden' => ! $fotoUrl]) alt="Előnézet" @if ($fotoUrl) src="{{ $fotoUrl }}" @endif>
      </div>
      <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" class="hidden">
      <div class="field-err hidden" id="photoErr"></div>
      <div class="field" style="margin-top:14px">
        <label class="req" for="photographer_credit">Fotós neve</label>
        <input type="text" id="photographer_credit" name="photographer_credit" value="{{ $e['photographer_credit'] }}">
      </div>
      <label class="check">
        <input type="checkbox" id="photo_rights_declared" name="photo_rights_declared" value="1" @checked($e['photo_rights_declared'])>
        <span class="req" style="font-weight:600">Kijelentem, hogy jogosult vagyok a fotó felhasználására, és hozzájárulok a közléséhez.</span>
      </label>
    </div>

    <button type="submit" class="btn btn-sun" id="submitBtn">{{ $program ? 'Módosítás mentése' : 'Program beküldése' }}</button>
    <p class="foot">A beküldött program a szervezők jóváhagyása után jelenik meg. A román fordítást mi készítjük.</p>
  </form>

  <div id="done" class="card success hidden">
    <div class="big">🎉</div>
    @if ($program)
      <h2>Köszönjük, a módosítás elmentve!</h2>
      <p class="help">A szervezők a javított változatot nézik át.</p>
    @else
      <h2>Köszönjük, megérkezett!</h2>
      <p class="help">A programot a szervezők átnézik, és jóváhagyás után megjelenik a Forgatag oldalán.</p>
    @endif
    <div class="row" style="justify-content:center;margin-top:18px;gap:10px">
      @unless ($program)
        <a class="btn btn-sun" style="flex:0 0 auto" href="{{ route('bekuldes.urlap', $kategoria) }}">Új program</a>
      @endunless
      <a class="btn btn-ghost" style="flex:0 0 auto" href="{{ route('bekuldes.sajat') }}">Saját beküldéseim</a>
    </div>
  </div>

  <script type="application/json" id="cfg">@json($config + ['editing' => (bool) $program, 'hasPhoto' => (bool) $fotoUrl])</script>
@endsection

@push('scripts')
  <script src="{{ asset('js/bekuldes.js') }}?v={{ filemtime(public_path('js/bekuldes.js')) }}"></script>
@endpush
