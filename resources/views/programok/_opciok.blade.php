{{--
  A választható helyszínek / kategóriák — a 2026-os `optRow()` jelölése (label.pk-opt > jelölőnégyzet + név + darabszám;
  is-on = kiválasztva, is-off = 0 találat a többi szűrő mellett: halványabb, de választható). Az id állandó, a sor
  minden szűréskor `hx-swap-oob`-bal frissül. A lista szövegkeresője (2026: pkVenueQ/pkCatQ) a nem illő sort rejti —
  a kiválasztottat soha (public/js/programkereso.js). EGY sablon az egész listára (soronként külön sablon lassú volt).
  Paraméterek: $csoport (venue|cat), $nev (a mező neve), $nevek (slug → felirat), $db (slug → darab), $kivalasztva.
--}}
@foreach ($nevek as $slug => $felirat)
@php $be = in_array($slug, $kivalasztva, true); $n = $db[$slug] ?? 0; @endphp
<label @class(['pk-opt', 'is-on' => $be, 'is-off' => $n === 0 && ! $be]) id="pk-o-{{ $csoport }}-{{ $slug }}" hx-swap-oob="true" data-group="{{ $csoport }}" data-id="{{ $slug }}" data-nev="{{ $felirat }}"><input type="checkbox" id="pk-{{ $csoport }}-{{ $slug }}" name="{{ $nev }}" value="{{ $slug }}" data-pk-szuro @checked($be)><span class="pk-lab">{{ $felirat }}</span><span class="pk-n">{{ $n }}</span></label>
@endforeach
