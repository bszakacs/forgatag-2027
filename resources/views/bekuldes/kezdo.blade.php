@extends('bekuldes.layout')

@section('title', 'Programbeküldés')

@section('hero')
  <div class="eyebrow">{{ \App\Support\Festival::spanLabel() }} · Marosvásárhely</div>
  @if ($nyitva)
    <p class="kicker">Hozd a <em>programod</em> a Forgatagba</p>
    <h1>Programbeküldés</h1>
    <p>Töltsd ki az űrlapot a programod adataival.</p>
  @else
    <p class="kicker">Programbeküldés</p>
    <h1>Lezárult</h1>
  @endif
@endsection

@section('content')
  <div class="navrow">
    <a class="btn btn-ghost btn-sm" href="{{ route('bekuldes.sajat') }}">Saját beküldéseim</a>
  </div>

  @if (session('hiba'))
    <div class="msg msg-err"><strong>{{ session('hiba') }}</strong></div>
  @endif

  @if (! $nyitva)
    <div class="card" id="zarva">
      <h2>A programleadás lezárult</h2>
      <p class="help">{{ \App\Support\Submission::closedMessage() }}</p>
    </div>
  @elseif ($kategoriak->isEmpty())
    <div class="card">
      <h2>Nincs kategóriád</h2>
      <p class="help">A fiókodhoz még nincs programkategória rendelve, ezért most nem tudsz programot beküldeni. Keresd a szervezőt.</p>
    </div>
  @else
    <div id="gate" class="card">
      <h2>Válaszd ki a programnak megfelelő kategóriát</h2>
      <p class="help">Ha nem kaptál közvetlen kategória-hivatkozást a szervezőtől, válassz az alábbi listából.</p>
      <div id="catList" class="row" style="margin-top:12px">
        @foreach ($kategoriak as $k)
          <a class="btn btn-ghost btn-sm" href="{{ route('bekuldes.urlap', $k) }}">{{ $k->name_hu }}</a>
        @endforeach
      </div>
    </div>
  @endif
@endsection
