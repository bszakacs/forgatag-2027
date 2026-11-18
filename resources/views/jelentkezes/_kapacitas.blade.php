{{-- A kapacitás-jelzés (2026: renderCapacity). --}}
@if ($allapot['varolista'])
  <div class="notice">A {{ $allapot['max'] }} induló hely már betelt. A jelentkezést továbbra is elfogadjuk: a csapat VÁRÓLISTÁRA kerül, és ha valaki visszalép, a sorban következő csapatot értesítjük.</div>
@elseif ($allapot['szabad'] <= 2)
  <div class="notice">Már csak {{ $allapot['szabad'] }} szabad hely van a {{ $allapot['max'] }}-ból.</div>
@endif
