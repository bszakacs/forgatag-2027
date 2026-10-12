@php
    $beku = auth('bekuldo')->user();
    $fesztNev = \App\Support\Festival::name();
@endphp
<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex">
<title>@yield('title') – {{ $fesztNev }} {{ \App\Support\Festival::year() }}</title>
<meta name="theme-color" content="#1D1935">
<link rel="preload" href="{{ asset('fonts/HankenGrotesk-400-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ asset('fonts/Archivo-var-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
<link rel="stylesheet" href="{{ asset('css/tema.css') }}">
<link rel="stylesheet" href="{{ asset('css/bekuldes.css') }}?v={{ filemtime(public_path('css/bekuldes.css')) }}">
</head>
<body>
<div class="wrap @yield('wrapClass')">
  <div class="topbar">
    <a class="brand" href="https://forgatag.ro" rel="noopener"><span class="bname">{{ $fesztNev }}</span></a>
    <span class="sp"></span>
    @if ($beku)
      <span class="who">Belépve: {{ $beku->name }}</span>
      <form method="post" action="{{ route('bekuldes.kilepes') }}">
        @csrf
        <button class="btn btn-ghost btn-sm" type="submit">Kilépés</button>
      </form>
    @endif
  </div>

  <div class="hero">
    <span class="glow g1" aria-hidden="true"></span>
    <span class="glow g2" aria-hidden="true"></span>
    <svg class="doodle d1" width="46" height="46" viewBox="0 0 60 60" fill="none" aria-hidden="true"><circle cx="30" cy="30" r="15" style="stroke:var(--doodle-lime)" stroke-width="6"></circle><circle cx="30" cy="30" r="5" style="fill:var(--doodle-orange)"></circle><circle cx="52" cy="18" r="3.5" style="fill:var(--doodle-orange)"></circle></svg>
    <svg class="doodle d2" width="52" height="28" viewBox="0 0 80 40" fill="none" aria-hidden="true"><path d="M4 10c8-8 12 8 20 0s12 8 20 0 12 8 20 0" style="stroke:var(--doodle-blue)" stroke-width="5" stroke-linecap="round"></path><path d="M12 30c8-8 12 8 20 0s12 8 20 0 12 8 20 0" style="stroke:var(--doodle-purple)" stroke-width="5" stroke-linecap="round"></path></svg>
    @yield('hero')
  </div>

  @yield('content')
</div>
@stack('scripts')
</body>
</html>
