@use('App\Support\Nyelv')
{{-- Hír-kártya. A borító négyzetesen, a megjelenítés méretén készül (egy vágás — CLAUDE.md 3.). --}}
<a class="ncard" href="{{ Nyelv::ut('/hirek/' . $hir->slug) }}">
  <div class="thumb">
    @if ($hir->cover)
      <img src="{{ url($hir->cover->getGlideUrl(['w' => 800, 'h' => 800, 'fit' => 'crop', 'fm' => 'webp', 'q' => 72])) }}"
           width="800" height="800" alt="" loading="lazy" decoding="async">
    @endif
  </div>
  <div class="body">
    @if ($hir->published_on)
      <span class="date">{{ Nyelv::datum($hir->published_on) }}</span>
    @endif
    <h2>{{ Nyelv::valaszt($hir->title_hu, $hir->title_ro) }}</h2>
    <p>{{ Nyelv::valaszt($hir->excerpt_hu, $hir->excerpt_ro) }}</p>
  </div>
</a>
