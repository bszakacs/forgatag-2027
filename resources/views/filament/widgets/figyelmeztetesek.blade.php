<x-filament-widgets::widget>
    {{-- App\Filament\Widgets\Figyelmeztetesek — inline stílussal, hogy ne kelljen hozzá az admin-téma újrafordítása. --}}
    <div role="alert" style="display:flex;flex-direction:column;gap:.75rem">
        @foreach (\App\Filament\Widgets\Figyelmeztetesek::uzenetek() as $u)
            <div style="border-left:4px solid var(--danger-600);background:color-mix(in oklab, var(--danger-500) 12%, transparent);border-radius:.5rem;padding:.75rem 1rem">
                <p style="font-weight:700;color:var(--danger-600);margin:0 0 .25rem">⚠ {{ $u['cim'] }}</p>
                <p style="margin:0;font-size:.875rem">{{ $u['szoveg'] }}</p>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
