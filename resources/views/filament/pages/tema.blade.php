<x-filament-panels::page>
    {{-- Az Arculat-szerkesztő. A felületet a böngésző építi (public/js/tema-szerkeszto.js) a lent
         beágyazott induló állapotból; a Livewire nem rajzolja újra (wire:ignore) — különben egy
         Livewire-frissítés felülírná az élő előnézetet és a mentetlen módosításokat. --}}
    <link rel="stylesheet" href="{{ asset('css/tema-szerkeszto.css') }}?v={{ filemtime(public_path('css/tema-szerkeszto.css')) }}">

    <div id="te-app" class="te-app" wire:ignore>
        <div class="te-bar">
            <span id="te-state" class="te-state">Betöltés…</span>
            <span class="te-sp"></span>
            <button type="button" class="te-btn" id="te-save" disabled>Vázlat mentése</button>
            <button type="button" class="te-btn te-btn-ghost" id="te-discard" hidden>Vázlat eldobása</button>
            <button type="button" class="te-btn te-btn-primary" id="te-publish">Publikálás…</button>
            <details class="te-more">
                <summary class="te-btn te-btn-ghost">További ▾</summary>
                <div class="te-more-menu">
                    <button type="button" id="te-history">⏱ Előzmény (utolsó 10)</button>
                    <button type="button" id="te-export">⬇ Export JSON</button>
                    <label for="te-import-file">⬆ Import JSON</label>
                    <input type="file" id="te-import-file" accept="application/json,.json" hidden>
                    <button type="button" id="te-factory">↺ Gyári visszaállítás</button>
                </div>
            </details>
        </div>
        <div id="te-alert" class="te-alert" hidden></div>
        <div id="te-guard" class="te-guard" hidden></div>

        <div class="te-grid">
            <div class="te-controls" id="te-sections" aria-label="Szekciók"></div>
            <div class="te-preview">
                <div class="te-preview-bar">
                    <strong>Élő előnézet</strong>
                    <span class="te-sp"></span>
                    <div class="te-seg" role="group" aria-label="Előnézet módja">
                        <button type="button" data-mode="dark" class="on">Sötét</button>
                        <button type="button" data-mode="light">Világos (◐)</button>
                    </div>
                </div>
                <iframe id="te-frame" title="A téma élő előnézete" src="{{ route('filament.admin.tema.elonezet') }}"></iframe>
                <p class="te-note">Az előnézet a böngésződben számolódik — a látogatók csak a publikálás után látják.</p>
            </div>
        </div>

        <dialog id="te-dlg" class="te-dlg">
            <form method="dialog" class="te-dlg-in">
                <h2 id="te-dlg-title"></h2>
                <div id="te-dlg-body"></div>
                <div class="te-dlg-foot" id="te-dlg-foot"></div>
            </form>
        </dialog>
    </div>

    <script type="application/json" id="te-config">@json($this->config(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)</script>
    <script src="{{ asset('js/tema-css.js') }}?v={{ filemtime(public_path('js/tema-css.js')) }}"></script>
    <script src="{{ asset('js/tema-szerkeszto.js') }}?v={{ filemtime(public_path('js/tema-szerkeszto.js')) }}"></script>
</x-filament-panels::page>
