{{-- Egy menüpont a fában (menuk.blade.php). $melyseg: 1 = főmenüpont, 2 = almenü. --}}
@php($cel = $elem->target())
<li class="fgm-elem" x-sortable-item="{{ $elem->id }}" wire:key="fgm-{{ $elem->id }}">
    <div @class(['fgm-sor', 'fgm-rejtett' => ! $elem->show_hu && ! $elem->show_ro])>
        <span class="fgm-fogo" x-sortable-handle title="Húzd a helyére" aria-hidden="true">⠿</span>
        <div class="fgm-szoveg">
            <span class="fgm-cim">{{ $elem->label_hu }}</span>
            @if (filled($elem->label_ro))<span class="fgm-ro">{{ $elem->label_ro }}</span>@endif
            <span class="fgm-cel">
                @if ($elem->type === \App\Enums\MenuItemType::Group)
                    csak almenü-fejléc
                @elseif ($cel === null)
                    <span class="fgm-hiba">nincs cél (a hozzá kötött aloldal törölve?)</span>
                @else
                    {{ $cel }}@if ($elem->new_tab) ↗@endif
                @endif
            </span>
        </div>
        <div class="fgm-gombok">
            @foreach (['hu' => 'HU', 'ro' => 'RO'] as $kod => $felirat)
                @php($latszik = $elem->{"show_{$kod}"})
                <button type="button" wire:click="nyelv({{ $elem->id }}, '{{ $kod }}')"
                    @class(['fgm-nyelv', 'fgm-nyelv-ki' => ! $latszik])
                    aria-pressed="{{ $latszik ? 'true' : 'false' }}"
                    title="{{ $latszik ? "Látszik a {$felirat} oldalon — kattints az elrejtéshez" : "Rejtve a {$felirat} oldalon — kattints a megjelenítéshez" }}">{{ $felirat }}</button>
            @endforeach
            <span class="fgm-nyilak">
                <button type="button" class="fgm-nyil" wire:click="mozgat({{ $elem->id }}, 'fel')" @disabled($elso) title="Feljebb" aria-label="Feljebb">↑</button>
                <button type="button" class="fgm-nyil" wire:click="mozgat({{ $elem->id }}, 'le')" @disabled($utolso) title="Lejjebb" aria-label="Lejjebb">↓</button>
                <button type="button" class="fgm-nyil" wire:click="mozgat({{ $elem->id }}, 'beljebb')" @disabled($elso || $melyseg >= \App\Support\MenuTree::MAX_DEPTH) title="Beljebb (az előző menüpont alá)" aria-label="Beljebb">→</button>
                <button type="button" class="fgm-nyil" wire:click="mozgat({{ $elem->id }}, 'kijjebb')" @disabled($melyseg === 1) title="Kijjebb" aria-label="Kijjebb">←</button>
            </span>
            {{ ($this->szerkesztAction)(['id' => $elem->id]) }}
            {{ ($this->torolAction)(['id' => $elem->id]) }}
        </div>
    </div>
    @if ($melyseg < \App\Support\MenuTree::MAX_DEPTH)
        <ul class="fgm-kids fgm-lista" x-sortable x-sortable-group="{{ $csoport }}" data-sortable-animation-duration="150">
            @foreach ($elem->children as $j => $gyerek)
                @include('filament.pages.menuk-sor', ['elem' => $gyerek, 'melyseg' => $melyseg + 1, 'elso' => $j === 0, 'utolso' => $loop->last, 'csoport' => $csoport])
            @endforeach
        </ul>
    @endif
</li>
