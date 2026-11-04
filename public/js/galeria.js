/*
 * A GALÉRIA három szintje — a 2026-os lib/gallery galleryScript() BETŰRE (CLAUDE.md 2/e):
 *  1. év-borító ([data-year], a kezdőlap csúszkáján) → 2. az év képei hasábokban (#gmlModal) → 3. egy kép teljes méretben (#gmlLb).
 * Egyetlen eltérés: a képlista (2026: a lapba ágyazott #gallery-data JSON, ~470 kép címe minden betöltéskor) az ablak
 * data-adat címéről jön, az ELSŐ megnyitáskor — utána ugyanaz, mint 2026-ban. Ha nem jön meg, az ablak kiírja (nem néma).
 */
(() => {
  const modal = document.getElementById('gmlModal'), feed = document.getElementById('gmlFeed');
  const mYear = document.getElementById('gmlYear'), mEd = document.getElementById('gmlEdition');
  const lb = document.getElementById('gmlLb'), lbFrame = document.getElementById('gmlFrame');
  const lbCounter = document.getElementById('gmlCounter'), lbCapYear = document.getElementById('gmlCapYear'), lbCapIdx = document.getElementById('gmlCapIdx');
  if (!modal || !feed || !lb) return;
  const KEP = modal.dataset.kep || 'kép';
  let DATA = null, betoltes = null;
  const adat = () => betoltes || (betoltes = fetch(modal.dataset.adat, { headers: { Accept: 'application/json' } })
    .then((r) => { if (!r.ok) throw new Error(r.status); return r.json(); })
    .then((d) => { DATA = d; return d; })
    .catch((e) => { betoltes = null; throw e; }));
  let items = [], lbIdx = 0, curYear = '', lastFocus = null;
  const colCount = () => { const w = window.innerWidth; return w <= 760 ? 2 : (w <= 1000 ? 3 : 4); };

  // LEVEL 2 — masonry: a pillanatnyilag legrövidebb oszlopba tesszük → LEFELE növő, csak függőleges görgetés.
  function buildFeed(year) {
    const g = DATA[year]; if (!g) return; items = g.images || [];
    mYear.textContent = year; mEd.textContent = g.edition || '';
    feed.innerHTML = '';
    const cols = colCount(), colEls = [], colH = [];
    for (let c = 0; c < cols; c++) { const d = document.createElement('div'); d.className = 'gml-col'; feed.appendChild(d); colEls.push(d); colH.push(0); }
    items.forEach((im, i) => {
      const t = document.createElement('button');
      t.className = 'gml-tile'; t.setAttribute('data-idx', i);
      t.setAttribute('aria-label', (i + 1) + '. ' + KEP);
      const dim = (im.w && im.h) ? ' width="' + im.w + '" height="' + im.h + '"' : '';
      t.innerHTML = '<img class="gml-ph" src="' + im.t + '" alt="" loading="lazy"' + dim + '>';
      let m = 0; for (let c = 1; c < cols; c++) { if (colH[c] < colH[m]) m = c; }
      colH[m] += ((im.w && im.h) ? im.h / im.w : 1.2) + 0.08;
      colEls[m].appendChild(t);
    });
  }
  function openFeed(year) {
    year = String(year);
    lastFocus = document.activeElement; curYear = year;
    modal.classList.add('open'); document.body.classList.add('gml-scrolllock'); feed.scrollTop = 0;
    const c = modal.querySelector('[data-close]'); if (c) c.focus();
    if (DATA) { buildFeed(year); return; }
    mYear.textContent = year; mEd.textContent = ''; feed.innerHTML = '';
    adat().then(() => { if (curYear === year && modal.classList.contains('open')) buildFeed(year); })
      .catch(() => { feed.textContent = modal.dataset.hiba || ''; });
  }
  function closeFeed() { modal.classList.remove('open'); document.body.classList.remove('gml-scrolllock'); if (lastFocus) lastFocus.focus(); }
  let rt; window.addEventListener('resize', () => { if (modal.classList.contains('open') && curYear && DATA) { clearTimeout(rt); rt = setTimeout(() => buildFeed(curYear), 200); } });

  // LEVEL 1 → FEED: rail (.gal-card) ÉS grid (.ycard) — mindkettő [data-year]. Dokumentumra delegált (dinamikus/kevert gyerekek).
  document.addEventListener('click', (e) => { const b = e.target && e.target.closest && e.target.closest('[data-year]'); if (b) { e.preventDefault(); openFeed(b.getAttribute('data-year')); } });
  modal.querySelectorAll('[data-close]').forEach((elm) => elm.addEventListener('click', closeFeed));

  // LEVEL 3 — lightbox: a masonry egy csempéjéről.
  function renderLb() { const im = items[lbIdx]; if (!im) return;
    lbFrame.innerHTML = '<img src="' + im.f + '" alt="">';
    lbCounter.textContent = (lbIdx + 1) + ' / ' + items.length;
    if (lbCapYear) lbCapYear.textContent = curYear;
    if (lbCapIdx) lbCapIdx.textContent = (lbIdx + 1) + '. ' + KEP; }
  function openLightbox(i) { lbIdx = i; lb.classList.add('open'); renderLb(); const c = lb.querySelector('[data-lbclose]'); if (c) c.focus(); }
  function closeLightbox() { lb.classList.remove('open'); }
  function step(d) { const n = items.length; if (!n) return; lbIdx = (lbIdx + d + n) % n; renderLb(); }
  feed.addEventListener('click', (e) => { const t = e.target && e.target.closest && e.target.closest('.gml-tile'); if (t) openLightbox(+t.getAttribute('data-idx')); });
  lb.querySelector('[data-lbclose]').addEventListener('click', closeLightbox);
  lb.querySelector('[data-lbprev]').addEventListener('click', () => step(-1));
  lb.querySelector('[data-lbnext]').addEventListener('click', () => step(1));
  lb.addEventListener('click', (e) => { if (e.target === lb) closeLightbox(); });

  document.addEventListener('keydown', (e) => {
    if (lb.classList.contains('open')) { if (e.key === 'Escape') closeLightbox(); else if (e.key === 'ArrowLeft') step(-1); else if (e.key === 'ArrowRight') step(1); }
    else if (modal.classList.contains('open')) { if (e.key === 'Escape') closeFeed(); }
  });
})();
