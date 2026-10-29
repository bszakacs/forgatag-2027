@use('App\Support\Theme')
@php
    // Közösségi linkek az Arculatból (csak a kitöltöttek). Az ikonok a 2026-os lábléc ikonjai.
    $ikonok = [
        'facebook' => ['Facebook', '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 13.5h2.5l1-4H14v-2c0-1.03 0-2 2-2h1.5V2.14c-.326-.043-1.557-.14-2.857-.14C11.928 2 10 3.657 10 6.7v2.8H7v4h3V22h4v-8.5z"/></svg>'],
        'instagram' => ['Instagram', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.6" cy="6.4" r="1.2" fill="currentColor" stroke="none"/></svg>'],
        'youtube' => ['YouTube', '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23 12s0-3.2-.4-4.7a2.5 2.5 0 0 0-1.75-1.75C19.3 5.1 12 5.1 12 5.1s-7.3 0-8.85.45A2.5 2.5 0 0 0 1.4 7.3C1 8.8 1 12 1 12s0 3.2.4 4.7a2.5 2.5 0 0 0 1.75 1.75C4.7 18.9 12 18.9 12 18.9s7.3 0 8.85-.45a2.5 2.5 0 0 0 1.75-1.75C23 15.2 23 12 23 12zM9.75 15.02V8.98L15.5 12l-5.75 3.02z"/></svg>'],
        'tiktok' => ['TikTok', '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.5 3c.31 2.13 1.5 3.42 3.5 3.55v2.4c-1.16.11-2.28-.16-3.5-.86v5.55c0 3.2-2.55 5.44-5.56 4.94-2.5-.41-4.02-2.53-3.9-5.02.12-2.4 2.2-4.27 4.66-4.09v2.52c-.5-.12-1.02-.1-1.5.1-1 .4-1.5 1.42-1.2 2.42.3 1 1.3 1.6 2.4 1.4 1.02-.18 1.6-1.03 1.6-2.12V3h3.5z"/></svg>'],
    ];
    $linkek = collect($ikonok)->filter(fn ($i, $k) => filled(Theme::get("social.{$k}")));
@endphp
@if ($linkek->isNotEmpty())
  <div class="social">
    @foreach ($linkek as $k => [$nev, $svg])
      <a href="{{ Theme::get("social.{$k}") }}" target="_blank" rel="noopener" aria-label="{{ $nev }}">{!! $svg !!}</a>
    @endforeach
  </div>
@endif
