/* KÖZÖS függőleges görgető — a 2026-os lib/rail vscrollScript() BETŰRE. MINDEN [data-vscroll] elemre (húzás, nyilak,
   billentyűk, resize); a „végén vagyunk” állapot a burkolón (`[data-vscrollwrap]`) is-vend osztály. A jelölés: partials/vrail. */
(() => {
  const RM = matchMedia('(prefers-reduced-motion:reduce)').matches;
  document.querySelectorAll('[data-vscroll]').forEach((el) => {
    const key = el.getAttribute('data-vscroll');
    const nav = document.querySelector('[data-vscrollnav="' + key + '"]');
    if (!nav) return;
    const line = nav.querySelector('.vrailline'), knob = nav.querySelector('.vrailknob');
    const up = nav.querySelector('.vrailup'), down = nav.querySelector('.vraildown');
    const wrap = el.closest('[data-vscrollwrap]') || el.parentElement;
    const maxS = () => Math.max(0, el.scrollHeight - el.clientHeight);
    function sync() {
      const m = maxS();
      nav.hidden = (m <= 1);
      if (wrap) wrap.classList.toggle('is-vend', m <= 1 || el.scrollTop >= m - 4);
      if (m <= 1) return;
      const r = el.scrollTop / m;
      if (knob) { knob.style.top = (r * 100) + '%'; knob.setAttribute('aria-valuenow', Math.round(r * 100)); }
      if (up) up.disabled = el.scrollTop <= 1;
      if (down) down.disabled = el.scrollTop >= m - 1;
    }
    function page(dir) { el.scrollBy({ top: dir * Math.max(160, el.clientHeight * 0.8), behavior: RM ? 'auto' : 'smooth' }); }
    el.addEventListener('scroll', sync, { passive: true });
    let rt; window.addEventListener('resize', () => { clearTimeout(rt); rt = setTimeout(sync, 150); });
    if (up) up.addEventListener('click', () => page(-1));
    if (down) down.addEventListener('click', () => page(1));
    if (knob && line) {
      let dragging = false, grabDy = 0;
      const drag = (e) => { const rr = line.getBoundingClientRect(); const y = (e.clientY - grabDy - rr.top) / rr.height; el.scrollTop = Math.max(0, Math.min(1, y)) * maxS(); };
      knob.addEventListener('pointerdown', (e) => { dragging = true; const kr = knob.getBoundingClientRect(); grabDy = e.clientY - (kr.top + kr.height / 2); try { knob.setPointerCapture(e.pointerId); } catch (_) {} e.preventDefault(); });
      knob.addEventListener('pointermove', (e) => { if (dragging) drag(e); });
      knob.addEventListener('pointerup', (e) => { dragging = false; try { knob.releasePointerCapture(e.pointerId); } catch (_) {} });
      knob.addEventListener('keydown', (e) => { const m = maxS(); if (e.key === 'ArrowUp') { el.scrollTop -= m * 0.1; e.preventDefault(); } else if (e.key === 'ArrowDown') { el.scrollTop += m * 0.1; e.preventDefault(); } else if (e.key === 'Home') { el.scrollTop = 0; e.preventDefault(); } else if (e.key === 'End') { el.scrollTop = m; e.preventDefault(); } });
    }
    el.__vsync = sync;   // a modál nyitásakor újraszinkronizálható
    sync(); window.addEventListener('load', sync);
  });
})();
