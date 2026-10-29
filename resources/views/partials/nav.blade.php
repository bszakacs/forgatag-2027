{{--
  MENÜ — a fa (App\Support\PublikusMenu::fa) két rajzolata ugyanabból az adatból:
  · asztali: vízszintes sáv, alpontok legördülőben (egérrel ÉS billentyűzettel: :focus-within nyitja);
  · mobil: a burger panelje, a főpontok címbetűvel, alattuk az alpontok.
  Link nélküli főpont (csak-fejléc csoport) felirat marad; az „új lapon” pont target=_blank.
--}}
@if ($valtozat === 'asztali')
  @foreach ($fa as $pont)
    <div class="navitem">
      @if ($pont['cim'])
        <a href="{{ $pont['cim'] }}" @if ($pont['uj_lap']) target="_blank" rel="noopener" @endif>{{ $pont['felirat'] }}@if ($pont['gyerekek']) <i class="caret" aria-hidden="true"></i>@endif</a>
      @else
        <button type="button" class="navhead">{{ $pont['felirat'] }} <i class="caret" aria-hidden="true"></i></button>
      @endif
      @if ($pont['gyerekek'])
        <div class="drop">
          @foreach ($pont['gyerekek'] as $g)
            @if ($g['cim'])
              <a href="{{ $g['cim'] }}" @if ($g['uj_lap']) target="_blank" rel="noopener" @endif>{{ $g['felirat'] }}</a>
            @endif
          @endforeach
        </div>
      @endif
    </div>
  @endforeach
@else
  @foreach ($fa as $pont)
    <div class="mnav-group">
      @if ($pont['cim'])
        <a class="mnav-top" href="{{ $pont['cim'] }}" @if ($pont['uj_lap']) target="_blank" rel="noopener" @endif>{{ $pont['felirat'] }}</a>
      @else
        <span class="mnav-top">{{ $pont['felirat'] }}</span>
      @endif
      @if ($pont['gyerekek'])
        <div class="mnav-sub">
          @foreach ($pont['gyerekek'] as $g)
            @if ($g['cim'])
              <a href="{{ $g['cim'] }}" @if ($g['uj_lap']) target="_blank" rel="noopener" @endif>{{ $g['felirat'] }}</a>
            @endif
          @endforeach
        </div>
      @endif
    </div>
  @endforeach
@endif
