{{--
  A 2026-os Base.astro lapjainak közös része (helyszín, kategória, napi nézet, program lapja, térkép): a stíluslap
  (public/css/alap.css = a 2026-os global.css betűre) és a lap-szkript (public/js/alap.js = a Base szkriptjéből a „MA”
  jelölés és a nyomtatás). A 2027-es kiegészítés külön: alap-szerver.css.
--}}
@push('styles')
<link rel="stylesheet" href="@eszkoz('css/alap.css')">
<link rel="stylesheet" href="@eszkoz('css/alap-szerver.css')">
@endpush
@push('scripts')
<script src="@eszkoz('js/alap.js')" defer></script>
@endpush
