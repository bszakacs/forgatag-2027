{{--
  Egy választható helyszín/kategória. Az id-je állandó, és `hx-swap-oob`-bal frissül minden szűréskor (a darabszám
  „a többi szűrő mellett” számol; a nulla csak halványít, semmi nem tűnik el). A szűrő-lista szövegkeresője
  (szo.<rov>) ékezet-függetlenül rejti a nem illőket — ez csak böngészés, nem szűrés.
--}}
<label @class(['pk-opt', 'is-child' => $gyerek, 'is-off' => $db === 0]) id="pk-o-{{ $rov }}-{{ $slug }}" hx-swap-oob="true"
       x-show="illik(szo.{{ $rov }}, @js($felirat), {{ $be ? 'true' : 'false' }})">
  <input type="checkbox" id="pk-{{ $rov }}-{{ $slug }}" name="{{ $nev }}" value="{{ $slug }}" data-pk-szuro @checked($be)>
  <span class="pk-lab">{{ $felirat }}</span>
  <span class="pk-n">{{ $db }}</span>
</label>
