{{-- A csapatszínek (2026: renderColors). Az elvitt szín letiltva + áthúzva. Ezt rajzolja az első betöltés ÉS az ütközés utáni friss
     lista is (OlimpiaJelentkezes::friss) — egy sablon, két hely nem csúszhat el. --}}
@foreach ($allapot['szinek'] as $c)
  <label class="color{{ $c['selectable'] ? '' : ' gone' }}">
    <input type="radio" name="color" value="{{ $c['key'] }}" @disabled(! $c['selectable'])>
    <span class="dot" @if ($c['hex']) style="background:{{ $c['hex'] }}" @endif></span>
    <span class="cname">{{ $c['name'] }}</span>
    @unless ($c['selectable'])
      <span class="sp"></span><span class="tag">{{ $c['taken'] ? 'elfogyott' : 'nem elérhető' }}</span>
    @endunless
  </label>
@endforeach
