/*
 * A VIDEÓ-ABLAK — a 2026-os lib/video videoScript() BETŰRE (CLAUDE.md 2/e): a borítóra ([data-yt]) kattintva a
 * youtube-nocookie lejátszó egy ablakban nyílik (automatikus indítással); bezáráskor törlődik. Kattintásig NINCS iframe.
 */
(() => {
  const lb = document.getElementById('vidLb'), frame = document.getElementById('vidLbFrame');
  if (!lb || !frame) return;
  const open = (id) => {
    if (!id) return;
    frame.innerHTML = '<iframe src="https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0" title="YouTube" ' +
      'allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>';
    lb.hidden = false; lb.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden';
  };
  const close = () => { lb.hidden = true; lb.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; frame.innerHTML = ''; };
  document.addEventListener('click', (e) => { const b = e.target && e.target.closest && e.target.closest('[data-yt]'); if (b) { e.preventDefault(); open(b.getAttribute('data-yt')); } });
  document.getElementById('vidLbClose').addEventListener('click', close);
  lb.addEventListener('click', (e) => { if (e.target === lb) close(); });
  document.addEventListener('keydown', (e) => { if (!lb.hidden && e.key === 'Escape') close(); });
})();
