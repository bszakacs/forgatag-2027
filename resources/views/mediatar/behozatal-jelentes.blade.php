{{-- A 2026-os képanyag behozatalának utolsó jelentése (App\Support\MediatarImport) — SZÁMSZERŰEN, mert
     1091 fájlnál a „sikerült” önmagában semmit nem jelent. Hiánynál piros és nem csukható be. --}}
@if ($jelentes)
    @php($hiba = $jelentes['hibak'] ?? [])
    @php($teendo = $jelentes['figyelmeztetesek'] ?? [])
    <details class="mt-jel {{ $hiba ? 'mt-jel--hiba' : '' }}" @if ($hiba || $teendo) open @endif>
        <style>
            .mt-jel{border:1px solid var(--gray-200);border-radius:.75rem;padding:.75rem 1rem;font-size:.875rem;background:var(--gray-50)}
            .dark .mt-jel{border-color:rgb(255 255 255 / .1);background:rgb(255 255 255 / .03)}
            .mt-jel--hiba{border-color:var(--danger-500);background:color-mix(in srgb, var(--danger-500) 8%, transparent)}
            .mt-jel summary{cursor:pointer;font-weight:600}
            .mt-jel table{margin-top:.5rem;border-collapse:collapse;width:100%;max-width:44rem}
            .mt-jel td{padding:.2rem .5rem .2rem 0;vertical-align:top}
            .mt-jel td.sz{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
            .mt-jel ul{margin:.5rem 0 0 1.1rem;list-style:disc}
        </style>
        <summary>
            2026-os képanyag behozatala ({{ $jelentes['ido'] ?? '' }}):
            {{ $jelentes['osszegzes'] ?? '' }}
        </summary>
        <table>
            @foreach ($jelentes['sorok'] ?? [] as $sor)
                <tr><td>{{ $sor[0] }}</td><td class="sz">{{ $sor[1] }}</td></tr>
            @endforeach
        </table>
        @if ($teendo)
            <p style="margin-top:.75rem;font-weight:600">Tennivaló ({{ count($teendo) }}):</p>
            <ul>@foreach ($teendo as $t)<li>{{ $t }}</li>@endforeach</ul>
        @endif
        @if ($hiba)
            <ul>
                @foreach (array_slice($hiba, 0, 30) as $h)<li>{{ $h }}</li>@endforeach
                @if (count($hiba) > 30)<li>… és még {{ count($hiba) - 30 }} hiba (a teljes lista: storage/app/import-2026/jelentes.json)</li>@endif
            </ul>
        @endif
    </details>
@endif
