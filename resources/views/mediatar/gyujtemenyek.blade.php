{{-- A médiatár baloldali gyűjtemény-listája (a „mappa”-érzet, CLAUDE.md 2/d). Mobilon vízszintes, görgethető sor. --}}
@php
    $lw = $schemaComponent->getLivewire();
    $aktiv = $lw->gyujtemeny;
@endphp
<nav class="mt-gyujt" aria-label="Gyűjtemények"
     x-data x-init="$nextTick(() => $el.querySelector(&quot;[aria-current=true]&quot;)?.scrollIntoView({ inline: &quot;center&quot;, block: &quot;nearest&quot; }))">
    <style>
        .mt-gyujt ul{display:flex;gap:.375rem;overflow-x:auto;padding:0 0 .25rem;margin:0;list-style:none;-webkit-overflow-scrolling:touch}
        .mt-gyujt li{flex:none}
        .mt-gyujt button{display:flex;align-items:center;justify-content:space-between;gap:.75rem;width:100%;
            padding:.45rem .7rem;border-radius:.5rem;font-size:.875rem;line-height:1.25rem;text-align:left;white-space:nowrap;
            color:var(--gray-700);background:var(--gray-100)}
        .dark .mt-gyujt button{color:var(--gray-200);background:rgb(255 255 255 / .05)}
        .mt-gyujt button:hover{background:var(--gray-200)}
        .dark .mt-gyujt button:hover{background:rgb(255 255 255 / .1)}
        .mt-gyujt button[aria-current=true]{background:var(--primary-500);color:#fff;font-weight:600}
        .mt-gyujt .db{font-variant-numeric:tabular-nums;opacity:.75;font-size:.8125rem}
        .mt-gyujt .elv{display:none}
        @media (min-width:1024px){
            .mt-gyujt ul{flex-direction:column;overflow:visible;gap:.125rem}
            .mt-gyujt button{white-space:normal}
            .mt-gyujt .elv{display:block;height:1px;background:var(--gray-200);margin:.375rem 0}
            .dark .mt-gyujt .elv{background:rgb(255 255 255 / .1)}
        }
    </style>
    <ul>
        @foreach ($lw->gyujtemenyLista() as $i => $g)
            @if ($i === 2)<li class="elv" aria-hidden="true"></li>@endif
            <li>
                <button type="button" wire:click="valaszt(@js($g['id']))" aria-current="{{ $aktiv === $g['id'] ? 'true' : 'false' }}">
                    <span>{{ $g['name'] }}</span><span class="db">{{ $g['count'] }}</span>
                </button>
            </li>
        @endforeach
    </ul>
</nav>
