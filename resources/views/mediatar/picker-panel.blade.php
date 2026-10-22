{{-- A Curator panel-indítójának (curator::components.modals.curator-panel) mása, a MI panelünkkel
     (App\Livewire\MediaPanel — a feltöltés helyéből jövő gyűjteménnyel). --}}
<livewire:media-panel
    :settings="$settings"
    @insertMedia="callSchemaComponentMethod({{ \Illuminate\Support\Js::from($key) }}, 'updateState', $event.detail); close()"
/>
