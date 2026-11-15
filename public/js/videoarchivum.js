/* ============================================================================
   VIDEÓ ARCHÍVUM — a nyitókép „szélben” sodródó firkái. A 2026-os ../forgatag-os/frontend/src/lib/decor.ts (decorScript)
   átirata (CLAUDE.md 2/e, PROMPT-17): darabszám (asztalon 16, ≤760 px-en 8), méret, sebesség, átlátszóság betűre.
   A csúszka a közös rail.js, a lejátszó a közös video.js (2026: railScript, videoScript).
   Eltérések (mind a 2027-es rétegből):
    · a felúszás (2026: a szkript első része, .reveal → .in) a közös mozgas.js-é;
    · a motívumok a téma 12 motívumából (<use href="#fgm-N">, Theme::motifSprite) — 2026-ban ugyanez a 12 rajz a szkriptbe
      égetve (lib/decor-motifs). A réteg [data-decor="hero"], a firka `.decor` osztályt is kap → a téma kapcsolói hatnak
      (sűrűség „nincs”, nyitókép ki, lebegés ki); ha a lebegés áll, a firkák szétszórva, állva jelennek meg (mint 2026-ban a
      „kevesebb mozgás” beállításnál);
    · csak SZÉLESSÉG-változásra épül újra (2026: minden átméretezésre — telefonon a címsáv mozgására is).
   ========================================================================= */
(function () {
  var reduce = window.matchMedia('(prefers-reduced-motion:reduce)').matches || document.documentElement.hasAttribute('data-motion');
  // 🔴 A keret viewBox-a „0 0 sz m” (NEM a motívum sajátja) — különben a <use> a rajzot a kereten kívülre teszi (mozgas.js).
  var SVGS = [].map.call(document.querySelectorAll('.fg-motivumok symbol'), function (s) {
    var v = (s.getAttribute('viewBox') || '').trim().split(/[\s,]+/);
    return '<svg viewBox="0 0 ' + v[2] + ' ' + v[3] + '"><use href="#' + s.id + '"/></svg>';
  });
  if (!SVGS.length) return;
  function rnd(a, b) { return a + Math.random() * (b - a); }
  function pick() { return SVGS[(Math.random() * SVGS.length) | 0]; }
  function fill(host, count) {
    if (!host) return;
    host.innerHTML = '';
    var mobile = window.innerWidth <= 760;
    for (var i = 0; i < count; i++) {
      var outer = document.createElement('div'); outer.className = 'drift decor';
      var inner = document.createElement('span'); inner.className = 'bob';
      inner.innerHTML = pick();
      outer.appendChild(inner);
      outer.style.width = rnd(mobile ? 26 : 34, mobile ? 54 : 88).toFixed(0) + 'px';
      outer.style.top = rnd(1, 96).toFixed(1) + '%';
      var dur = rnd(mobile ? 26 : 22, mobile ? 46 : 40);
      outer.style.setProperty('--dur', dur.toFixed(1) + 's');
      outer.style.setProperty('--bdur', rnd(5, 9).toFixed(1) + 's');
      outer.style.setProperty('--dl', (-rnd(0, dur)).toFixed(1) + 's'); // szórt kezdés → mindig van kép útközben
      outer.style.setProperty('--r0', rnd(-10, 2).toFixed(1) + 'deg');
      outer.style.setProperty('--r1', rnd(-2, 10).toFixed(1) + 'deg');
      // Az átlátszóság a --doodle-opacity tokenre skálázva (a /tema „Firka-átlátszóság" csúszkája);
      // a gyári .72-nél az eredeti 0,5–0,85 sávot adja vissza.
      outer.style.opacity = 'calc(var(--doodle-opacity, .72) * ' + (rnd(mobile ? 0.5 : 0.55, 0.85) / 0.72).toFixed(3) + ')';
      host.appendChild(outer);
      // reduced-motion (vagy a téma leállította a lebegést): szétszórt, statikus
      if (reduce || getComputedStyle(outer).animationName === 'none') outer.style.left = rnd(1, 95).toFixed(1) + '%';
    }
  }
  var heroDecor = document.getElementById('heroDecor');
  function buildAll() { fill(heroDecor, window.innerWidth <= 760 ? 8 : 16); }
  buildAll();
  var szel = window.innerWidth, rt;
  window.addEventListener('resize', function () {
    if (window.innerWidth === szel) return;
    szel = window.innerWidth;
    clearTimeout(rt); rt = setTimeout(buildAll, 200);
  });
})();
