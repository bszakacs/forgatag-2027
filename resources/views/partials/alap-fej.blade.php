{{--
  A 2026-os Base.astro lapjainak közös stílusa (public/css/alap.css = a 2026-os global.css betűre; a 2027-es kiegészítés:
  alap-szerver.css). Használói: a helyszín- és kategórialista, a program lapja, a /terkep. A PROGRAMOT LISTÁZÓ lapok (napi
  nézet, kategória-, helyszínlap) NEM ezt használják, hanem a programkereső sorait (programok/lista — tulajdonosi szabály,
  2026-11-04).
--}}
@push('styles')
<link rel="stylesheet" href="@eszkoz('css/alap.css')">
<link rel="stylesheet" href="@eszkoz('css/alap-szerver.css')">
@endpush
