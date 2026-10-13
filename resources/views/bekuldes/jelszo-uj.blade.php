@extends('bekuldes.layout')

@section('title', 'Új jelszó')
@section('wrapClass', 'narrow')

@section('hero')
  <p class="kicker">Programgazdák <em>belépése</em></p>
  <h1>Új jelszó</h1>
  <p>Add meg az új jelszavadat kétszer. Legalább {{ \App\Http\Controllers\Bekuldes\JelszoController::MIN_HOSSZ }} karakter legyen.</p>
@endsection

@section('content')
  <div class="card">
    @if ($errors->any())
      <div class="msg msg-err"><strong>{{ $errors->first() }}</strong></div>
    @endif
    <form method="post" action="{{ route('bekuldes.jelszo.ment') }}">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <input type="hidden" name="email" value="{{ $email }}">
      <div class="field">
        <label for="email_lathato">E-mail</label>
        <input type="email" id="email_lathato" value="{{ $email }}" autocomplete="username" readonly>
      </div>
      <div class="field">
        <label for="password">Új jelszó</label>
        <input type="password" id="password" name="password" autocomplete="new-password" required>
      </div>
      <div class="field">
        <label for="password_confirmation">Új jelszó még egyszer</label>
        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
      </div>
      <button type="submit" class="btn btn-sun btn-block">Jelszó beállítása</button>
    </form>
  </div>
@endsection
