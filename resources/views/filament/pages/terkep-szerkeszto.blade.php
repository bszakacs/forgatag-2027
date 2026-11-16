<x-filament-panels::page>
    {{-- A térkép-szerkesztő (App\Filament\Pages\TerkepSzerkeszto, PROMPT-18). A 2026-os MapEditorView felülete; a
         felületet a böngésző építi (public/js/terkep-szerkeszto.js) a lent beágyazott induló adatból, a Livewire nem
         rajzolja újra (wire:ignore) — különben egy frissítés eldobná a mentetlen húzásokat. Mentés: $wire.mentes(). --}}
    <link rel="stylesheet" href="@eszkoz('css/terkep-szerkeszto.css')">

    <div class="me-shell" id="meApp" wire:ignore x-data x-init="window.fgTerkepSzerkeszto($el, $wire)">
        <div class="me-top">
            <p class="me-lead">
                Húzd rá a helyszínt a térképre (egérrel vagy ujjal). Ahová húztad, ott lesz a jelölő. A térkép körüli
                <b>szegélyre</b> húzva a helyszín a térkép <b>szélére</b> kerül, irányjelvénnyel (ami a kivágáson kívül van).
                Visszahúzva a listába (vagy a „Levétel” gombbal) lekerül. A <b>Mentés</b> írja be; a publikus térképen azonnal látszik.
            </p>
            <div class="me-actions">
                <a class="me-btn me-btn-ghost" href="{{ route('hu.terkep') }}" target="_blank" rel="noopener">A publikus térkép ↗</a>
                <button class="me-btn me-btn-primary" type="button" id="meSave" disabled>Mentés</button>
                <span id="meStatus" class="me-status" role="status" aria-live="polite"></span>
            </div>
        </div>

        {{-- HANGOS jelzés (2026: me-alert): hiba vagy a böngészőben maradt mentetlen munka — nem a pici státuszsorban. --}}
        <div class="me-alert" id="meAlert" role="alert" hidden>
            <div><b id="meAlertTitle"></b><p id="meAlertText"></p></div>
            <button class="me-btn me-btn-primary" type="button" id="meAlertBtn" hidden></button>
        </div>

        <div class="me-body">
            <aside class="me-side">
                <div class="me-maptabs" role="tablist" aria-label="Melyik térképet szerkesztem">
                    @foreach ($this->config()['maps'] as $i => $m)
                        <button class="me-maptab" type="button" role="tab" data-zone="{{ $m['zone'] }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}">
                            {{ $m['label'] }} <b class="me-tabn" data-zonecount="{{ $m['zone'] }}">0</b>
                        </button>
                    @endforeach
                </div>

                <div class="me-block">
                    <p class="me-h">Helyszínek <b id="meUnplacedN">0 / 0 elhelyezve</b></p>
                    <p class="me-note">A <strong>kiemelt</strong> helyszínek nincsenek még a térképen — azok a teendő.
                        A halványakat már elhelyezted; rájuk kattintva felvillan a jelölőjük.</p>
                    <input type="search" id="meSearch" class="me-search" placeholder="Keresés (csak segítség)…" aria-label="Keresés a helyszínek között">
                    <ul class="me-list" id="meUnplaced" aria-label="Helyszínek"></ul>
                </div>

                <div class="me-block" id="meInspBlock" hidden>
                    <p class="me-h">Kiválasztott jelölő</p>
                    <p class="me-selname" id="meSelName"></p>
                    <label class="me-lbl">Sorszám a jelölőn
                        <input type="number" id="meSelNo" min="1" max="999" step="1">
                    </label>
                    <label class="me-lbl">Rövid név (a térkép szélére, ha a teljes nem fér ki)
                        <input type="text" id="meSelShort" maxlength="60" placeholder="pl. Somostető">
                    </label>
                    <fieldset class="me-fs">
                        <legend class="me-lbl">Hol jelenjen meg?</legend>
                        <label class="me-radio"><input type="radio" name="mePlace" value="map" id="mePlaceMap">
                            <span>A térképen (behúzott pont)</span></label>
                        <label class="me-radio"><input type="radio" name="mePlace" value="edge" id="mePlaceEdge">
                            <span>A térkép szélén, irányjelzővel</span></label>
                        <p class="me-note" id="meEdgeHint" hidden>A helyszín a kivágáson kívül van: nem kell behúzni,
                            csak az irányt megadni. A jelvény a megfelelő szélre kerül, a sorrendet a sorszám adja.</p>
                    </fieldset>
                    <div class="me-lbl" id="meBearingBox" hidden>Merre van?
                        <div class="me-bearing" id="meBearing" role="radiogroup" aria-label="Irány"></div>
                    </div>
                    <button class="me-btn me-btn-ghost" type="button" id="meSelRemove">Levétel a térképről</button>
                </div>
            </aside>

            <section class="me-map" aria-label="Térkép">
                {{-- KOPPINTÁSOS elhelyezés (telefonon nem lehet a listából a térképre húzni — egymás alatt vannak) --}}
                <div class="me-armed" id="meArmed" role="status" hidden>
                    <span id="meArmedText"></span>
                    <button class="me-btn me-btn-ghost" type="button" id="meArmedNo">Mégse</button>
                </div>
                <div class="me-toolbar">
                    <div class="me-zoom" role="group" aria-label="Nagyítás">
                        <button type="button" id="meZoomOut" aria-label="Kicsinyítés">&minus;</button>
                        <span id="meZoomVal">100%</span>
                        <button type="button" id="meZoomIn" aria-label="Nagyítás">+</button>
                    </div>
                    <p class="me-hint">A jelölőt kattintással választod ki, húzással áthelyezed. Nagyítva a térkép görgethető.</p>
                </div>
                @foreach ($this->config()['maps'] as $i => $m)
                    <div class="me-canvas" data-canvas="{{ $m['zone'] }}" @if ($i > 0) hidden @endif>
                        {{-- A SZEGÉLY (me-frame): ide ejtve a helyszín a térkép SZÉLÉRE kerül, az ejtés irányából. --}}
                        <div class="me-frame" data-frame="{{ $m['zone'] }}" title="A térkép szélére (irányjelvénnyel)">
                            <div class="me-layer" data-layer="{{ $m['zone'] }}" style="aspect-ratio:{{ $m['w'] }}/{{ $m['h'] }}">
                                <img src="{{ $m['src'] }}" alt="" width="{{ (int) round($m['w']) }}" height="{{ (int) round($m['h']) }}" draggable="false">
                                {{-- KOORDINÁTA-RÉTEG: a rajz dobozán 0–100-as saját koordináta. A képernyő-pontot a
                                     getScreenCTM().inverse() váltja át — nagyítás és görgetés mellett is pontosan. --}}
                                <svg class="me-hit" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"></svg>
                                {{-- a jelölőket a szkript teszi ide; a szélső jelvények a közös elhelyezővel (terkep-szel.js) --}}
                                <div class="pk-offwrap me-offwrap" data-offwrap="{{ $m['zone'] }}"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>
        </div>
    </div>

    <script type="application/json" id="me-config">@json($this->config(), JSON_UNESCAPED_UNICODE)</script>
    <script src="@eszkoz('js/terkep-szel.js')"></script>
    <script src="@eszkoz('js/terkep-szerkeszto.js')"></script>
</x-filament-panels::page>
