@extends('bekuldes.layout')

@section('title', 'Elfelejtett jelszó')
@section('wrapClass', 'narrow')

@section('hero')
  <p class="kicker">Programgazdák <em>belépése</em></p>
  <h1>Elfelejtett jelszó</h1>
  <p>Add meg a belépési e-mail-címedet, és küldünk egy linket, amellyel új jelszót állíthatsz be.</p>
@endsection

@section('content')
  <div class="card">
    @if (session('siker'))
      <div class="msg msg-ok">{{ session('siker') }}</div>
    @endif
    @if ($errors->any())
      <div class="msg msg-err"><strong>{{ $errors->first() }}</strong></div>
    @endif
    <form method="post" action="{{ route('bekuldes.jelszo.kuld') }}">
      @csrf
      <div class="field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" required>
      </div>
      <button type="submit" class="btn btn-sun btn-block">Link küldése</button>
    </form>
  </div>

  <p class="foot"><a href="{{ route('bekuldes.belepes') }}">Vissza a belépéshez</a></p>
@endsection
