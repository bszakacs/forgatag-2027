@use('App\Support\Theme')
@php
    $beku = auth('bekuldo')->user();
    $fesztNev = \App\Support\Festival::name();
@endphp
<!doctype html>
<html lang="hu" data-theme="dark">
<head>
<meta charset="utf-8">
<script>
  // Világos/sötét mód villanás nélkül (2026-os beküldő űrlap: ugyanaz a kulcs és logika; alap a sötét).
  // A két mód színei a témából jönnek (:root és :root[data-theme="light"]).
  (function(){try{var s=localStorage.getItem('forgatag-theme');
    document.documentElement.dataset.theme=(s==='light'||s==='dark')?s:'dark';}catch(e){}})();
</script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex">
<title>@yield('title') – {{ $fesztNev }} {{ \App\Support\Festival::year() }}</title>
{{-- A szín, betű, forma, mozgás és firka MIND a publikált témából jön (App\Support\Theme) — itt semmi nincs beégetve.
     Téma nélkül a lap hibával áll meg (nincs csendes tartalék). --}}
<meta name="theme-color" content="{{ Theme::get('colors.bg.dark') }}">
<link rel="icon" type="image/png" href="{{ Theme::assetUrl('favicon') }}">
<link rel="apple-touch-icon" href="{{ Theme::assetUrl('favicon') }}">
@foreach (Theme::fontPreloads() as $betu)
<link rel="preload" href="{{ $betu }}" as="font" type="font/woff2" crossorigin>
@endforeach
<link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}?v={{ filemtime(public_path('fonts/fonts.css')) }}">
<link rel="stylesheet" href="{{ Theme::cssUrl() }}">
<link rel="stylesheet" href="{{ asset('css/bekuldes.css') }}?v={{ filemtime(public_path('css/bekuldes.css')) }}">
</head>
<body>
<div class="wrap @yield('wrapClass')">
  <div class="topbar">
    <a class="brand" href="https://forgatag.ro" rel="noopener"><span class="bname">{{ $fesztNev }}</span></a>
    <span class="sp"></span>
    <button class="btn btn-ghost btn-sm theme-toggle" type="button" id="themeToggle" aria-label="Világos/sötét mód váltása" title="Világos/sötét mód">◐</button>
    @if ($beku)
      <span class="who">Belépve: {{ $beku->name }}</span>
      <form method="post" action="{{ route('bekuldes.kilepes') }}">
        @csrf
        <button class="btn btn-ghost btn-sm" type="submit">Kilépés</button>
      </form>
    @endif
  </div>

  <div class="hero" data-decor="hero">
    <span class="glow g1" aria-hidden="true"></span>
    <span class="glow g2" aria-hidden="true"></span>
    <svg class="doodle decor d1" width="46" height="46" viewBox="0 0 60 60" fill="none" aria-hidden="true"><circle cx="30" cy="30" r="15" style="stroke:var(--doodle-lime)" stroke-width="6"></circle><circle cx="30" cy="30" r="5" style="fill:var(--doodle-orange)"></circle><circle cx="52" cy="18" r="3.5" style="fill:var(--doodle-orange)"></circle></svg>
    <svg class="doodle decor d2" width="52" height="28" viewBox="0 0 80 40" fill="none" aria-hidden="true"><path d="M4 10c8-8 12 8 20 0s12 8 20 0 12 8 20 0" style="stroke:var(--doodle-blue)" stroke-width="5" stroke-linecap="round"></path><path d="M12 30c8-8 12 8 20 0s12 8 20 0 12 8 20 0" style="stroke:var(--doodle-purple)" stroke-width="5" stroke-linecap="round"></path></svg>
    @yield('hero')
  </div>

  @yield('content')
</div>
<script>
  document.getElementById('themeToggle').addEventListener('click', function () {
    var r = document.documentElement, next = r.dataset.theme === 'dark' ? 'light' : 'dark';
    r.dataset.theme = next;
    try { localStorage.setItem('forgatag-theme', next); } catch (e) {}
  });
</script>
@stack('scripts')
</body>
</html>
