@use('App\Support\Terkep')
{{--
  A TÉRKÉP — a 2026-os `renderFinderMap()` (lib/finder-map.ts) + `offmapBadgeHtml()` (lib/offmap.ts) jelölése BETŰRE.
  EGYSZER rajzolódik (a htmx-csere célterületén KÍVÜL: #pkMapCol, keskeny nézetben a JS a #pkMapSheetIn-be költözteti,
  mint 2026-ban); a szűrésre a #pkTerkepAdat adat-elemből frissíti magát (public/js/terkep.js) — újrarajzolás nincs.
  A helyszín azonosítója a slug (2026: a lap-adatcsomag indexe).
--}}
@php
    $nyil = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"/></svg>';
    $sor = fn (array $it, bool $kid) => '<button class="pk-legrow' . ($kid ? ' is-kid' : '') . ($it['onMap'] ? '' : ' is-off') . '" type="button" data-legend="' . e($it['slug']) . '"'
        . ($it['onMap'] ? ' data-zone="' . e($it['zone']) . '"' : ' title="' . e(__('publikus.pk_terkep_nincs_rajta')) . '"')
        . ' aria-pressed="false"><span class="pk-legno">' . e($it['onMap'] ? $it['no'] : '') . '</span><span class="pk-legname">' . e($it['name'])
        . '</span><span class="pk-legn" aria-hidden="true"></span></button>';
@endphp
{{-- $mod = 'lap' (a /terkep lapon, PROMPT-15): a kattintás nem szűr, hanem a helyszín lapjára visz ($helyszinUt + slug). --}}
<div class="pk-map" data-comp="program-map" data-slot="venues" @if (($mod ?? null) === 'lap') data-mod="lap" data-helyszin-ut="{{ $helyszinUt }}" @endif data-csalad="{{ json_encode((object) $terkep['csalad'], JSON_UNESCAPED_UNICODE) }}" data-egesz="{{ __('publikus.pk_terkep_egesz_helyszin') }}" data-programszo="{{ __('publikus.pk_program_szo') }}">
  <div class="pk-maptabs" role="tablist" aria-label="{{ __('publikus.pk_terkep_cim') }}">@foreach ($terkep['zonak'] as $i => $m)<button class="btn btn-fill {{ $i === 0 ? 'lime' : 'pink' }} pk-maptab" type="button" role="tab" id="pkTab-{{ $m['zone'] }}" data-mapzone="{{ $m['zone'] }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="pkMap-{{ $m['zone'] }}"><span>{{ $m['label'] }}</span><span class="pk-tabn"><b>{{ count($m['marks']) + count($m['edges']) }}</b></span></button>@endforeach</div>
  <div class="pk-mapstage">
    <div class="pk-deck" id="pkDeck" hidden></div>
    {{-- Helyszín-buborék: a jelölő fölé húzva / fókuszba kerülve a helyszín neve és a mostani szűrésre eső programszám. --}}
    <div class="pk-tip" id="pkTip" role="status" aria-live="polite"></div>
    @foreach ($terkep['zonak'] as $i => $m)
    <div class="pk-mapview" id="pkMap-{{ $m['zone'] }}" role="tabpanel" aria-labelledby="pkTab-{{ $m['zone'] }}" @if ($i > 0) hidden @endif>
      <div class="pk-zoom" role="group" aria-label="{{ __('publikus.pk_terkep_cim') }}">
        <button type="button" data-mapzoom="in" aria-label="{{ __('publikus.pk_terkep_nagyitas') }}">+</button>
        <button type="button" data-mapzoom="out" aria-label="{{ __('publikus.pk_terkep_kicsinyites') }}">&minus;</button>
        <button type="button" data-mapzoom="reset" aria-label="{{ __('publikus.pk_terkep_teljes') }}">⤢</button>
      </div>
      <div class="pk-canvas" data-canvas="{{ $m['zone'] }}" data-w="{{ $m['w'] }}" data-h="{{ $m['h'] }}" style="--pk-ar:{{ $m['w'] }}/{{ $m['h'] }}">
        <div class="pk-layer" data-layer="{{ $m['zone'] }}">
          <img class="pk-mapimg" src="@eszkoz($m['src'])" alt="" width="{{ (int) round($m['w']) }}" height="{{ (int) round($m['h']) }}" decoding="async" draggable="false">
          <div class="pk-marks">@foreach ($m['marks'] as $k)<button type="button" class="pk-marker{{ $k['kids'] ? ' pk-stack' : '' }}" data-venue="{{ $k['slug'] }}" data-kids="{{ implode(',', $k['kids']) }}" aria-pressed="false" style="left:{{ $k['x'] }}%;top:{{ $k['y'] }}%" title="{{ $k['name'] }}" aria-label="{{ $k['name'] }}{{ $k['kids'] ? ' — ' . __('publikus.pk_terkep_tobb_terem') : '' }}"><span class="pk-mk-n">{{ $k['no'] }}</span><i class="pk-mk-hit" aria-hidden="true"></i></button>@endforeach</div>
        </div>
        <div class="pk-offwrap" data-offwrap="{{ $m['zone'] }}">@foreach ($m['edges'] as $e)<button type="button" class="pk-offmap" data-venue="{{ $e['slug'] }}" aria-pressed="false" style="--pk-rot:{{ $e['bearing'] }}deg" data-bearing="{{ $e['bearing'] }}" data-ord="{{ (int) $e['no'] }}" title="{{ $e['name'] }}" aria-label="{{ $e['name'] }} — {{ __('publikus.pk_terkep_jelolo', ['no' => $e['no']]) }}, {{ Terkep::IRANYOK[(($e['bearing'] % 360) + 360) % 360] ?? $e['bearing'] . '°' }}"><span class="pk-offnum">{{ $e['no'] }}</span><span class="pk-offtxt"><b>{{ $e['label'] }}</b></span>{!! $nyil !!}</button>@endforeach</div>
      </div>
    </div>
    @endforeach
  </div>
  @if ($terkep['jelmagyarazat'])
  <div class="pk-legend" data-comp="map-legend">
    <div class="pk-legend-head"><h3>{{ __('publikus.pk_terkep_jelmagyarazat') }}</h3><span>{{ __('publikus.pk_terkep_jel_sugo') }}</span></div>
    <div class="pk-legend-list">@foreach ($terkep['jelmagyarazat'] as $g)<div class="pk-leggroup">{!! $sor($g['head'], false) !!}@foreach ($g['kids'] as $k){!! $sor($k, true) !!}@endforeach</div>@endforeach</div>
  </div>
  @endif
  <div class="pk-mapinfo">
    <h3>{{ __('publikus.pk_terkep_kozerdeku') }}</h3>
    <p><span class="pk-ii">🚻</span> {{ __('publikus.pk_terkep_mosdo') }} · <span class="pk-ii">➕</span> {{ __('publikus.pk_terkep_elsosegely') }} · <span class="pk-ii">🅿️</span> {{ __('publikus.pk_terkep_parkolas') }}</p>
    <p><span class="pk-ii">☔</span> {{ __('publikus.pk_terkep_eso') }}</p>
  </div>
</div>
