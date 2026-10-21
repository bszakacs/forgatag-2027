<!doctype html>
<html lang="hu" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Arculat — előnézet</title>
{{-- Az Arculat-szerkesztő előnézete (iframe). A publikus lapok VALÓDI stílusaival (betűk, beküldő űrlap)
     — a Filament CSS-e ide nem jön. A téma-CSS-t a szerkesztő írja a #tema-vazlat elembe, élőben;
     kint lévő téma-link itt szándékosan NINCS (különben a régi szabályai a vázlat mellett is hatnának). --}}
<link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}?v={{ filemtime(public_path('fonts/fonts.css')) }}">
<style id="tema-vazlat"></style>
<link rel="stylesheet" href="{{ asset('css/bekuldes.css') }}?v={{ filemtime(public_path('css/bekuldes.css')) }}">
<link rel="stylesheet" href="{{ asset('css/tema-elonezet.css') }}?v={{ filemtime(public_path('css/tema-elonezet.css')) }}">
</head>
<body>
<header class="pv-head">
  <img class="pv-logo-h" data-asset="assets.logoHeader" alt="Fejléc-logó">
  <nav class="pv-nav"><a href="#">Program</a><a href="#">Helyszínek</a><a href="#">Hírek</a></nav>
  <span class="pv-sp"></span>
  <a class="btn btn-sun btn-sm" href="#">Fejléc-gomb</a>
</header>

<div class="wrap">
  <div class="hero" data-decor="hero">
    <span class="glow g1" aria-hidden="true"></span>
    <span class="glow g2" aria-hidden="true"></span>
    <span class="eyebrow">2027. augusztus · Marosvásárhely</span>
    <p class="kicker">Hozd a <em>programod</em> a Forgatagba</p>
    <h1>Címsor — Vásárhelyi Forgatag</h1>
    <p>Törzsszöveg halvány színnel (--fg-muted). Ékezetpróba: ő ű Ő Ű · ș ț ă î â.</p>
  </div>

  <div class="card">
    <h2>Kártya címe (kisebb címsor)</h2>
    <div class="field">
      <label class="req">Beviteli mező</label>
      <input type="text" placeholder="Helykitöltő (--fg-faint)">
      <p class="help">Súgószöveg (--fg-muted)</p>
    </div>
    <div class="field">
      <label>Fókuszban lévő mező</label>
      <input type="text" class="pv-focus" value="A fókusz-keret színe (--focus)">
    </div>
    <div class="radio-group field">
      <label class="radio sel"><input type="radio" checked> Kijelölt</label>
      <label class="radio"><input type="radio"> Nem kijelölt</label>
    </div>
    <div class="notice"><strong>Figyelem:</strong> figyelmeztető doboz (--warn).</div>
    <div class="msg msg-ok">Sikerüzenet (--ok)</div>
    <div class="msg msg-err"><strong>Hiba:</strong> hibaüzenet (--danger)</div>
    <p class="pv-row">
      <button type="button" class="btn btn-sun">Fő gomb (akcent)</button>
      <button type="button" class="btn btn-ghost">Másodlagos</button>
    </p>
    <p class="pv-row">
      <span class="badge open">várakozik</span>
      <span class="badge done">közzétéve</span>
      <span class="pv-ma">MA</span>
      <a href="#">Egy link</a>
    </p>
  </div>

  <section class="pv-lap">
    <h2>Nappali lap</h2>
    <p>Világos sáv a sötét oldalon (--lap-*): szöveg <span class="pv-lap-muted">és halvány szöveg</span>.</p>
    <div class="pv-lap-card">Nappali kártya</div>
  </section>

  <section class="pv-motifs-wrap">
    <h2 class="pv-small">Firka-motívumok (a 6 hely)</h2>
    <div class="pv-motifs" data-decor="ambient">
      @for ($i = 0; $i < \App\Support\Theme::MOTIF_SLOTS; $i++)
        <div class="decor pv-motif" data-motif="{{ $i }}"></div>
      @endfor
    </div>
  </section>

  <footer class="pv-foot">
    <img class="pv-logo" data-asset="assets.logo" alt="Fő logó">
    <span class="pv-favicon"><img data-asset="assets.favicon" alt=""> böngészőfül</span>
  </footer>
</div>
</body>
</html>
