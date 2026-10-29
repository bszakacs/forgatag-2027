@use('App\Support\Nyelv')
@use('App\Support\Festival')
@use('App\Support\Settings')
@use('App\Support\PublikusMenu')
@php
    // LÁBLÉC — EGY helyen. A bemutatkozó szöveg: Beállítások → Publikus oldal; a dátum a fesztivál adataiból;
    // a közösségi linkek: Arculat; a korábbi évek: Tartalom → Menü („archive”, mindig új lapon nyílik).
    $ny = Nyelv::aktualis();
    $archiv = collect(PublikusMenu::fa('archive'))->flatMap(fn ($p) => [$p, ...$p['gyerekek']])->filter(fn ($p) => $p['cim'])->values();
@endphp
<footer class="site-ft">
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <h2 class="ft-h">{{ $fesztNev }}</h2>
        <p class="ft-blurb">{{ Festival::spanLabel($ny) }} · {{ __('publikus.varos') }}. {{ Nyelv::valaszt(Settings::get('public.footer_text_hu'), Settings::get('public.footer_text_ro')) }}</p>
        @include('partials.social')
      </div>
      <div>
        <h2 class="ft-h">{{ __('publikus.tamogatok_cim') }}</h2>
        <a href="{{ Nyelv::ut('/tamogatok') }}">{{ __('publikus.tamogatok_link') }}</a>
      </div>
    </div>
    @if ($archiv->isNotEmpty())
      <nav class="foot-arch" aria-label="{{ __('publikus.archivum') }}">
        @foreach ($archiv as $p)
          <a href="{{ $p['cim'] }}" target="_blank" rel="noopener">{{ $p['felirat'] }}</a>
        @endforeach
      </nav>
    @endif
    {{-- 2013: az első Forgatag éve (tény, nem változik); a záró év a Beállítások kezdő dátumából. --}}
    <p class="copy">© {{ Festival::name('hu') }} 2013–{{ Festival::year() }}</p>
  </div>
</footer>
