@use('App\Support\Nyelv')
@use('App\Support\HtmlSzuro')
{{--
  Egy lap blokkjai (Tartalom → Aloldalak → Blokkok), sorrendben. A nyelvenként rejtett blokk kimarad.
  A szöveg a szerkesztőből HTML — CSAK szűrve (App\Support\HtmlSzuro). Románul üres mező → a magyar látszik.
--}}
@php $ny = Nyelv::aktualis(); @endphp
<div class="blokkok">
@foreach ($blokkok as $b)
  @continue (! $b->{"show_{$ny}"})
  @php $cim = Nyelv::valaszt($b->title_hu, $b->title_ro); @endphp
  @switch ($b->type)
    @case('richtext')
      <section class="blokk blokk-szoveg">
        @if ($cim !== '') <h2>{{ $cim }}</h2> @endif
        <div class="article-body">{!! HtmlSzuro::tisztit(Nyelv::valasztHtml($b->body_hu, $b->body_ro)) !!}</div>
      </section>
      @break
    @case('image')
      @if ($b->media)
        <figure class="blokk blokk-kep">
          <img src="{{ url($b->media->getGlideUrl(['w' => 1600, 'fm' => 'webp', 'q' => 78])) }}" alt="{{ $cim }}" loading="lazy">
          @if ($cim !== '') <figcaption>{{ $cim }}</figcaption> @endif
        </figure>
      @endif
      @break
    @case('cta')
      <section class="blokk blokk-cta">
        @if ($cim !== '') <h2>{{ $cim }}</h2> @endif
        <div class="article-body">{!! HtmlSzuro::tisztit(Nyelv::valasztHtml($b->body_hu, $b->body_ro)) !!}</div>
        @if (filled($b->cta_url))
          <a class="art-btn" href="{{ Nyelv::ut($b->cta_url) }}">{{ Nyelv::valaszt($b->cta_label_hu, $b->cta_label_ro) }}</a>
        @endif
      </section>
      @break
    @case('divider')
      <hr class="blokk blokk-hr">
      @break
    @case('videos')
      <section class="blokk blokk-videok">
        @if ($cim !== '') <h2>{{ $cim }}</h2> @endif
        <div class="video-racs">
          @foreach ($b->videos ?? [] as $v)
            <a class="video" href="https://www.youtube.com/watch?v={{ $v['youtube_id'] }}" target="_blank" rel="noopener">
              <img src="https://i.ytimg.com/vi/{{ $v['youtube_id'] }}/hqdefault.jpg" width="480" height="360" alt="" loading="lazy">
              <span>{{ Nyelv::valaszt($v['title_hu'] ?? '', $v['title_ro'] ?? null) }}</span>
            </a>
          @endforeach
        </div>
      </section>
      @break
  @endswitch
@endforeach
</div>
