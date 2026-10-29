@extends('layouts.app')
@use('App\Support\Nyelv')
{{--
  RÓLUNK — a 2026-os lap (forgatag-os/frontend/src/rolunk/body.html) szerkezete. MINDEN látható szöveg és kép
  szerkeszthető: Tartalom → Aloldalak → Rólunk → Szerkeszthető szövegek (a kulcsok: App\Support\LapSablon).
  Ami 2026-ban beégetett volt (a logók neve, a webshop címe), az is kulcsot kapott.
  Kimaradt (dekor, nem szöveg): a görgetésre rajzolódó „fonál” és a szekciók felúszása — a kezdőlap körében jön.
--}}
@section('title', Nyelv::valaszt($lap->title_hu, $lap->title_ro))
@section('leiras', Nyelv::valaszt($lap->seo_description_hu, $lap->seo_description_ro))
@push('styles')
<link rel="stylesheet" href="{{ asset('css/rolunk.css') }}?v={{ filemtime(public_path('css/rolunk.css')) }}">
@endpush
@php
    $kep = fn (string $kulcs, array $p) => ($m = $sz->kep($kulcs)) ? url($m->getGlideUrl($p + ['fm' => 'webp', 'q' => 78])) : null;
    $logo = ['w' => 460];
@endphp

@section('content')
<div class="about-page" x-data="{ borkert: false }">

<section class="subhero">
  <div class="wrap">
    <h1><span class="ln c-w">{{ $sz->szoveg('about_hero_line1') }}</span><span class="ln c-y">{{ $sz->szoveg('about_hero_line2') }}</span><span class="ln brush">{{ $sz->szoveg('about_hero_line3') }}</span></h1>
  </div>
</section>

<section class="story">
  <div class="wrap">
    @foreach ([1 => ['about_story_p1', 'about_story_p2'], 2 => ['about_story_p3'], 3 => ['about_story_p4'], 4 => ['about_story_p5'], 5 => ['about_story_p6']] as $i => $bekezdesek)
      <div @class(['srow', 'rev' => $i % 2 === 0])>
        <div class="stxt">
          @foreach ($bekezdesek as $k)
            <p @class(['intro' => $k === 'about_story_p1'])>{{ $sz->szoveg($k) }}</p>
          @endforeach
        </div>
        <figure class="sfig">
          @php $ev = $sz->szoveg("about_story_year{$i}"); $felirat = $sz->szoveg("about_story_caption{$i}"); $src = $kep("about_story_photo{$i}", ['w' => 1200, 'h' => 900, 'fit' => 'crop']); @endphp
          <div @class(['sfig-img', 'noimg' => ! $src])>
            @if ($src)
              <img src="{{ $src }}" width="1200" height="900" alt="{{ trim($ev . ' – ' . $felirat, ' –') }}" loading="lazy">
            @endif
            <figcaption class="ycap"><span class="yr">{{ $ev }}</span><span class="lbl">{{ $felirat }}</span></figcaption>
          </div>
        </figure>
      </div>
    @endforeach
  </div>
</section>

<section class="blk white">
  <div class="wrap">
    <div class="sec-head"><h2>{{ $sz->szoveg('about_organizers_title') }} <span class="brush">{{ $sz->szoveg('about_organizers_title_accent') }}</span></h2></div>
    <div class="org-row">
      @foreach ([1, 2] as $i)
        @if ($src = $kep("about_organizer_logo{$i}", $logo))
          <div class="org"><img src="{{ $src }}" alt="{{ $sz->szoveg("about_organizer_name{$i}") }}" loading="lazy"></div>
        @else
          <div class="org org-nev">{{ $sz->szoveg("about_organizer_name{$i}") }}</div>
        @endif
      @endforeach
    </div>
  </div>
</section>

<section class="blk">
  <div class="wrap">
    <div class="sec-head"><h2>{{ $sz->szoveg('about_related_title') }} <span class="brush">{{ $sz->szoveg('about_related_title_accent') }}</span></h2></div>
    <div class="relrow">
      {{-- JavaScript nélkül a link a #borkert ablakra ugrik (CSS :target nyitja); Alpine-nal helyben nyit. --}}
      <a class="relcard borkert" href="#borkert" role="button" aria-haspopup="dialog" aria-controls="borkert"
         @click.prevent="borkert = true">
        @if ($src = $kep('about_related_logo1', $logo))
          <img src="{{ $src }}" alt="{{ $sz->szoveg('about_related_name1') }} – {{ __('publikus.bovebben') }}" loading="lazy">
        @else
          <span class="relnev">{{ $sz->szoveg('about_related_name1') }}</span>
        @endif
      </a>
      <a class="relcard vasarhely" href="{{ $sz->link('about_related_url2') }}" target="_blank" rel="noopener noreferrer">
        @if ($src = $kep('about_related_logo2', $logo))
          <img src="{{ $src }}" alt="{{ $sz->szoveg('about_related_name2') }} ({{ __('publikus.uj_lapon') }})" loading="lazy">
        @else
          <span class="relnev">{{ $sz->szoveg('about_related_name2') }}</span>
        @endif
      </a>
    </div>
  </div>
</section>

<div class="modal" id="borkert" role="dialog" aria-modal="true" aria-labelledby="borkertCim"
     :class="{ 'nyitva': borkert }" x-effect="borkert && $nextTick(() => $refs.zar.focus())" @keydown.escape.window="borkert = false">
  <a class="modal-scrim" href="#" tabindex="-1" aria-hidden="true" @click.prevent="borkert = false"></a>
  <div class="modal-card">
    <a class="modal-x" href="#" x-ref="zar" aria-label="{{ __('publikus.bezaras') }}" @click.prevent="borkert = false">&times;</a>
    @if ($src = $kep('about_borkert_photo', ['w' => 1280, 'h' => 720, 'fit' => 'crop']))
      <img class="modal-img" src="{{ $src }}" width="1280" height="720" alt="" loading="lazy">
    @endif
    <div class="modal-body">
      <h2 id="borkertCim">{{ $sz->szoveg('about_borkert_title') }}</h2>
      @foreach (['about_borkert_p1', 'about_borkert_p2', 'about_borkert_p3', 'about_borkert_p4'] as $k)
        <p>{{ $sz->szoveg($k) }}</p>
      @endforeach
    </div>
  </div>
</div>

</div>
@endsection
