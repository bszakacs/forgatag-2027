<x-filament-panels::page>
    {{-- A menü-szerkesztő (App\Filament\Pages\Menuk). Saját fa: a fogd-és-vidd a Filament beépített
         Sortable-direktívája (x-sortable, közös csoport → szintek között is húzható); a húzás végén a böngésző
         a TELJES fát küldi (mentFa). Mobilon / billentyűzettel a nyíl-gombok ugyanazt érik el.
         A stílus saját osztályokkal (fgm-), hogy ne kelljen hozzá az admin-téma újrafordítása. --}}
    <link rel="stylesheet" href="{{ asset('css/menu-szerkeszto.css') }}?v={{ filemtime(public_path('css/menu-szerkeszto.css')) }}">

    @php($menu = $this->menu())
    @php($menuk = $this->menus())

    @if ($menu === null)
        <p class="fgm-ures">Még nincs menü az adatbázisban. (A 2026-os menüt a telepítés hozza be: <code>forgatag:tartalom-import</code>.)</p>
    @else
        @if ($menuk->count() > 1)
            <nav class="fgm-tabok" aria-label="Menük">
                @foreach ($menuk as $m)
                    <button type="button" wire:click="valtMenu({{ $m->id }})"
                        @class(['fgm-tab', 'fgm-tab-akt' => $m->id === $menu->id])
                        aria-current="{{ $m->id === $menu->id ? 'page' : 'false' }}">{{ $m->title_hu }}</button>
                @endforeach
            </nav>
        @endif

        <p class="fgm-sugo">Húzd a <span aria-hidden="true">⠿</span> fogónál fogva, vagy használd a nyilakat
            (<b>↑ ↓</b> sorrend, <b>→</b> az előző alá, <b>←</b> kijjebb). A <b>HU</b> / <b>RO</b> gomb a menüpontot
            az adott nyelvű oldalon rejti el vagy mutatja. Legfeljebb {{ \App\Support\MenuTree::MAX_DEPTH }} szint.</p>

        @php($fa = $this->items())
        <div wire:key="fa-{{ $menu->id }}-{{ $rev }}"
             x-data="{
                fa(ul) {
                    if (! ul) return []
                    return [...ul.children].filter((li) => li.hasAttribute('x-sortable-item')).map((li) => ({
                        id: Number(li.getAttribute('x-sortable-item')),
                        children: this.fa(li.querySelector(':scope > .fgm-kids')),
                    }))
                },
                kezdo: null,
                init() { this.kezdo = JSON.stringify(this.fa(this.$refs.gyoker)) },
                vege() {
                    const most = this.fa(this.$refs.gyoker)
                    if (JSON.stringify(most) !== this.kezdo) $wire.mentFa(most)
                },
             }"
             x-on:end.stop="vege()">
            @if ($fa->isEmpty())
                <p class="fgm-ures">Ez a menü üres. Az „Új menüpont” gombbal adhatsz hozzá.</p>
            @endif
            <ul class="fgm-lista" x-ref="gyoker" x-sortable x-sortable-group="fgm-{{ $menu->id }}" data-sortable-animation-duration="150">
                @foreach ($fa as $i => $elem)
                    @include('filament.pages.menuk-sor', ['elem' => $elem, 'melyseg' => 1, 'elso' => $i === 0, 'utolso' => $loop->last, 'csoport' => "fgm-{$menu->id}"])
                @endforeach
            </ul>
        </div>
    @endif
</x-filament-panels::page>
