@extends('bekuldes.layout')

@section('title', 'Belépés')
@section('wrapClass', 'narrow')

@section('hero')
  <p class="kicker">Programgazdák <em>belépése</em></p>
  <h1>Belépés</h1>
  <p>A programbeküldő űrlap csak belépés után érhető el. Használd a szervezőtől kapott e-mailt és jelszót.</p>
@endsection

@section('content')
  <div class="card">
    @if (session('siker'))
      <div class="msg msg-ok">{{ session('siker') }}</div>
    @endif
    @if ($errors->any())
      <div class="msg msg-err"><strong>{{ $errors->first() }}</strong></div>
    @endif
    <form method="post" action="{{ route('bekuldes.belepes') }}">
      @csrf
      <div class="field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
      </div>
      <div class="field">
        <label for="password">Jelszó</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-sun btn-block">Belépés</button>
    </form>
    <p class="foot" style="margin-top:14px"><a href="{{ route('bekuldes.jelszo') }}">Elfelejtett jelszó?</a></p>
  </div>

  <p class="foot">Ha nincs belépésed, keresd a szervezőt. A programbeküldés a jóváhagyás után jelenik meg.</p>
@endsection
