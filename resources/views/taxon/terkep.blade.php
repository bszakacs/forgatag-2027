@extends('layouts.app')
@use('App\Support\Nyelv')
{{--
  A /terkep LAP (PROMPT-15). TULAJDONOSI DÖNTÉS (2026-11-04): a 2026-os MapView OpenStreetMap-térkép volt, ami csak GPS-es
  helyszínt jelölt — GPS egyiknek sem volt, ezért üresen állt. A rendszer VALÓDI mechanizmusa a rajzolt Vár/Város térkép
  (a helyszínek a térkép-szerkesztőben kézzel húzva: map_zone/x/y/no, a távoliak a SZÉLÉN irányjelvénnyel) — ez áll itt,
  a programkereső 2026-os térképének jelölésével (programok/_terkep), „lap” módban: a jelölő, a jelmagyarázat sora és a
  szélső jelvény a helyszín LAPJÁRA visz; a `?helyszin=<slug>` (a helyszínlap „Mutasd a térképen” linkje) a jelölőt kiemeli.
  A lap kerete a 2026-os MapView-é (cím + bevezető, Base).
--}}
@section('title', __('publikus.b_terkep'))
@include('partials.alap-fej')
@push('styles')
<link rel="stylesheet" href="@eszkoz('css/terkep.css')">
<link rel="stylesheet" href="@eszkoz('css/terkep-lap.css')">
@endpush
@push('scripts')
<script src="@eszkoz('js/terkep.js')" defer></script>
@endpush

@section('content')
  <h2>{{ __('publikus.b_terkep') }}</h2>
  <p class="lead">{{ __('publikus.b_terkep_bevezeto') }}</p>
  <div class="map-lap" data-section="map" role="region" aria-label="{{ __('publikus.b_terkep') }}">
    <script type="application/json" id="pkTerkepAdat">{!! json_encode($terkepAllapot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @include('programok._terkep', ['terkep' => $terkep, 'mod' => 'lap', 'helyszinUt' => Nyelv::ut('/helyszin/')])
  </div>
@endsection
