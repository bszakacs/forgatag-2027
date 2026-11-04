@use('App\Support\Nyelv')
{{--
  A GALÉRIA-ABLAK — a 2026-os lib/gallery GALLERY_MODAL_HTML jelölése BETŰRE: 2. szint (az év képei hasábokban) és 3. szint
  (egy kép teljes méretben, lapozható). A tartalmat a public/js/galeria.js tölti; a képlista a data-adat címről jön,
  csak az első megnyitáskor (2026-ban a lapba ágyazott #gallery-data JSON volt). Kinézet: public/css/galeria.css.
--}}
@php
    $x = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg>';
@endphp
<div class="gml-modal" id="gmlModal" role="dialog" aria-modal="true" aria-labelledby="gmlYear"
     data-adat="{{ Nyelv::ut('/galeria-adat.json') }}" data-kep="{{ __('publikus.kp_galeria_kep') }}" data-hiba="{{ __('publikus.kp_galeria_hiba') }}">
  <div class="gml-backdrop" data-close></div>
  <div class="gml-panel">
    <div class="gml-head">
      <div class="gml-htxt"><h2><span class="gml-brush" id="gmlYear"></span></h2><div class="gml-edition" id="gmlEdition"></div></div>
      <button class="gml-iconbtn gml-close" data-close aria-label="{{ __('publikus.bezaras') }}">{!! $x !!}</button>
    </div>
    <div class="gml-feed" id="gmlFeed"></div>
  </div>
</div>
<div class="gml-lb" id="gmlLb" role="dialog" aria-modal="true" aria-label="{{ __('publikus.kp_galeria_teljes') }}">
  <div class="gml-counter" id="gmlCounter">1 / 1</div>
  <button class="gml-iconbtn gml-lbclose" data-lbclose aria-label="{{ __('publikus.bezaras') }}">{!! $x !!}</button>
  <div class="gml-stage">
    <button class="gml-nav gml-prev" data-lbprev aria-label="{{ __('publikus.kp_galeria_elozo') }}"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 5-7 7 7 7"/></svg></button>
    <div class="gml-frame" id="gmlFrame"></div>
    <button class="gml-nav gml-next" data-lbnext aria-label="{{ __('publikus.kp_galeria_kovetkezo') }}"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 5 7 7-7 7"/></svg></button>
    <div class="gml-cap"><b id="gmlCapYear"></b> · <span id="gmlCapIdx"></span></div>
  </div>
</div>
