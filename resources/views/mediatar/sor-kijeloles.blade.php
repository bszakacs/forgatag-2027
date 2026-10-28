{{-- Médiatár, listanézet: a sorra kattintás kijelöli a képet (a jelölőnégyzetre kattint helyette).
     Linkre, gombra, jelölőnégyzetre vagy legördülőre kattintva nem avatkozik közbe. --}}
<script>
    document.addEventListener('click', (e) => {
        const sor = e.target.closest('.fi-resource-mediatar .fi-ta-table tr.fi-ta-row');
        if (! sor || e.target.closest('a, button, input, label, select, .fi-dropdown-panel')) {
            return;
        }
        sor.querySelector('input.fi-ta-record-checkbox')?.click();
    });
</script>
