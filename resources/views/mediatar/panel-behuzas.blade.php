{{-- Képválasztó (Curator-panel): a fájl a TELJES panelre behúzható, nem csak a jobb oldali kis mezőre — mint a
     WordPress médiatárában (tulajdonosi kérés, 2026-10-28). A ledobott fájlokat a panel saját feltöltő mezőjének
     adjuk át, így minden ellenőrzés (típus, méret) ugyanaz marad. A kék „ide engedd” réteg a Curator saját
     stílusa (.curator-panel.dragging). --}}
<script>
    (() => {
        let melyseg = 0;
        const panel = (e) => e.target.closest?.('.curator-panel');
        const vanFajl = (e) => [...(e.dataTransfer?.types || [])].includes('Files');
        const bemenet = (p) => p.querySelector('.curator-panel-sidebar input[type=file], input.filepond--browser');

        document.addEventListener('dragenter', (e) => {
            const p = panel(e);
            if (! p || ! vanFajl(e) || ! bemenet(p)) return;
            melyseg++;
            p.classList.add('dragging');
        });
        document.addEventListener('dragover', (e) => {
            const p = panel(e);
            if (p && vanFajl(e) && bemenet(p)) e.preventDefault(); // enélkül a böngésző megnyitná a fájlt
        });
        document.addEventListener('dragleave', (e) => {
            const p = panel(e);
            if (! p || ! vanFajl(e)) return;
            if (--melyseg <= 0) {
                melyseg = 0;
                p.classList.remove('dragging');
            }
        });
        document.addEventListener('drop', (e) => {
            const p = panel(e);
            if (! p || ! vanFajl(e)) return;
            melyseg = 0;
            p.classList.remove('dragging');
            // A jobb oldali mező saját ledobását nem írjuk felül (az a FilePond dolga).
            if (e.target.closest('.filepond--root')) return;
            const input = bemenet(p);
            if (! input) return;
            e.preventDefault();
            const dt = new DataTransfer();
            [...e.dataTransfer.files].forEach((f) => dt.items.add(f));
            input.files = dt.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    })();
</script>
