{{--
  Egy választható helyszín/kategória — a 2026-os `optRow()` jelölése (label.pk-opt > jelölőnégyzet + név + darabszám;
  is-on = kiválasztva, is-off = 0 találat a többi szűrő mellett: halványabb, de választható). Az id állandó, a sor
  minden szűréskor `hx-swap-oob`-bal frissül. A lista szövegkeresője (2026: pkVenueQ/pkCatQ) a nem illő sort rejti —
  a kiválasztottat soha (public/js/programkereso.js).
--}}
<label @class(['pk-opt', 'is-on' => $be, 'is-off' => $db === 0 && ! $be]) id="pk-o-{{ $csoport }}-{{ $slug }}" hx-swap-oob="true"
       data-group="{{ $csoport }}" data-id="{{ $slug }}" data-nev="{{ $felirat }}"><input type="checkbox" id="pk-{{ $csoport }}-{{ $slug }}" name="{{ $nev }}" value="{{ $slug }}" data-pk-szuro @checked($be)><span class="pk-lab">{{ $felirat }}</span><span class="pk-n">{{ $db }}</span></label>
