/* ============================================================================
   KÉZMŰVES VÁSÁR — viselkedés. Nincs külső függőség.
   A 2026-os ../forgatag-os/frontend/src/lib/craft.ts (craftScript) átirata (CLAUDE.md 2/e, PROMPT-17): úszó firka-dekor,
   lapozó (18/lap, felül + alul), hover-fény a firka-palettából, felugró ablak (scrim + X + Escape, görgetés-zár, fókusz,
   Tab-csapda). A számok, időzítések, méretek betűre. A felugró ablak görgetője a közös vrail.js (2026: lib/rail).
   Eltérések (mind a 2027-es rétegből):
    · a felúszás (2026: (1) reveal, .cv-reveal) a közös mozgas.js-é — itt nincs második példánya;
    · a firkák a téma 12 motívumából (a lap közös készlete: <use href="#fgm-N">, Theme::motifSprite) — 2026-ban ugyanez a
      12 rajz a szkriptbe volt égetve (lib/decor-motifs). A réteg [data-decor="ambient"] jelölést kapott;
    · a firkák csak SZÉLESSÉG-változásra szóródnak újra (2026: minden átméretezésre — telefonon a címsáv be-/kicsúszására is,
      ettől ugráltak; mint a mozgas.js és a kezdőlap);
    · a „Minimális” mozgás-fokozat (data-motion) a „kevesebb mozgás” szerint (nincs firka).
   ========================================================================= */
(() => {
  var RM = matchMedia('(prefers-reduced-motion:reduce)').matches || document.documentElement.hasAttribute('data-motion');
  function rnd(a,b){ return a + Math.random()*(b-a); }

  /* ---------- (2) firka-dekor (a Hírek/videó-archívum motívumaival) ---------- */
  // 🔴 A keret viewBox-a „0 0 sz m” (NEM a motívum sajátja) — különben a <use> a rajzot a kereten kívülre teszi (mozgas.js).
  var SVGS = [].map.call(document.querySelectorAll('.fg-motivumok symbol'), function (s) {
    var v = (s.getAttribute('viewBox') || '').trim().split(/[\s,]+/);
    return '<svg viewBox="0 0 ' + v[2] + ' ' + v[3] + '"><use href="#' + s.id + '"/></svg>';
  });
  var decor = document.getElementById('pageDecor');
  function buildDecor() {
    if (!decor || RM || !SVGS.length) return;
    decor.innerHTML = '';
    var hgt = decor.offsetHeight || window.innerHeight;
    var count = Math.max(10, Math.min(52, Math.round(hgt / 95)));
    for (var i = 0; i < count; i++) {
      var el = document.createElement('div');
      el.className = 'decor';
      el.innerHTML = SVGS[(Math.random() * SVGS.length) | 0];
      var side = Math.random() < 0.5;
      el.style.width = rnd(40, 108).toFixed(0) + 'px';
      el.style.left = (side ? rnd(0, 30) : rnd(70, 100)).toFixed(1) + '%';
      el.style.top = rnd(0, 100).toFixed(1) + '%';
      // Az átlátszóság a --doodle-opacity tokenre skálázva (a /tema csúszkája);
      // a gyári .72-nél a design eredeti 0,14–0,26 sávját adja vissza.
      el.style.opacity = 'calc(var(--doodle-opacity, .72) * ' + (rnd(.14, .26) / .72).toFixed(3) + ')';
      el.style.setProperty('--dx', rnd(-8, 8).toFixed(1) + 'px');
      el.style.setProperty('--dy', rnd(92, 140).toFixed(0) + 'px');
      el.style.setProperty('--dur', rnd(12, 17).toFixed(1) + 's');
      el.style.setProperty('--dl', (-rnd(0, 20)).toFixed(1) + 's');
      el.style.setProperty('--r0', rnd(-8, 8).toFixed(1) + 'deg');
      el.style.setProperty('--r1', rnd(-8, 8).toFixed(1) + 'deg');
      decor.appendChild(el);
    }
  }
  buildDecor();
  window.addEventListener('load', buildDecor);
  var szel = window.innerWidth, rt;
  window.addEventListener('resize', function () {
    if (window.innerWidth === szel) return;
    szel = window.innerWidth;
    clearTimeout(rt); rt = setTimeout(buildDecor, 220);
  });

  /* ---------- (3) LAPOZÓ ---------- */
  var grid = document.getElementById('cvGrid');
  var pagers = document.querySelectorAll('[data-cv-pager]');
  var counter = document.getElementById('cvCount');
  var section = document.getElementById('kezmuvesek');
  var T = {};
  try { T = JSON.parse((grid && grid.getAttribute('data-cv-i18n')) || '{}'); } catch (e) { T = {}; }
  if (grid && pagers.length) {
    var per = parseInt(grid.getAttribute('data-per-page'), 10) || 18;
    var cards = Array.prototype.slice.call(grid.querySelectorAll('.cv-card'));
    var total = cards.length, pages = Math.max(1, Math.ceil(total / per)), cur = 1;

    function arrow(dir) {
      return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" ' +
        'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' +
        (dir < 0 ? 'M15 5l-7 7 7 7' : 'M9 5l7 7-7 7') + '"/></svg>';
    }
    function pageList() {
      if (pages <= 7) { var a = []; for (var i = 1; i <= pages; i++) a.push(i); return a; }
      var want = [1, pages, cur, cur - 1, cur + 1], seen = {}, list = [];
      want.forEach(function (n) { if (n >= 1 && n <= pages && !seen[n]) { seen[n] = 1; list.push(n); } });
      list.sort(function (x, y) { return x - y; });
      var out = [], prev = 0;
      list.forEach(function (n) { if (prev && n - prev > 1) out.push('…'); out.push(n); prev = n; });
      return out;
    }
    function render() {
      cards.forEach(function (c, i) { c.hidden = !(i >= (cur - 1) * per && i < cur * per); });
      var from = (cur - 1) * per + 1, to = Math.min(cur * per, total);
      if (counter && T.count) {
        counter.textContent = T.count.replace('{from}', from).replace('{to}', to).replace('{total}', total);
      }
      if (pages <= 1) { pagers.forEach(function (pg) { pg.hidden = true; }); return; }
      var html = '<button class="cv-pg" type="button" data-cv-go="' + (cur - 1) + '"' +
        (cur === 1 ? ' disabled' : '') + ' aria-label="' + (T.prev || 'Előző') + '">' + arrow(-1) + '</button>';
      pageList().forEach(function (n) {
        if (n === '…') { html += '<span class="cv-gap" aria-hidden="true">…</span>'; return; }
        html += '<button class="cv-pg" type="button" data-cv-go="' + n + '"' +
          (n === cur ? ' aria-current="page"' : '') +
          ' aria-label="' + (T.page || '{n}').replace('{n}', n) + '">' + n + '</button>';
      });
      html += '<button class="cv-pg" type="button" data-cv-go="' + (cur + 1) + '"' +
        (cur === pages ? ' disabled' : '') + ' aria-label="' + (T.next || 'Következő') + '">' + arrow(1) + '</button>';
      pagers.forEach(function (pg) { pg.innerHTML = html; });
    }
    function go(n) {
      n = Math.max(1, Math.min(pages, n));
      if (n === cur) return;
      cur = n; render();
      if (section) {
        section.scrollIntoView({ behavior: RM ? 'auto' : 'smooth', block: 'start' });
        var first = cards[(cur - 1) * per];
        if (first) {
          var b = first.querySelector('.cv-open');
          if (b) setTimeout(function () { b.focus({ preventScroll: true }); }, RM ? 0 : 420);
        }
      }
    }
    pagers.forEach(function (pg) {
      pg.addEventListener('click', function (e) {
        var b = e.target.closest('[data-cv-go]'); if (!b || b.disabled) return;
        go(parseInt(b.getAttribute('data-cv-go'), 10));
      });
    });
    render();
  }

  /* ---------- hover-glow: véletlen neon szín a firka-palettából ---------- */
  if (grid) {
    var NEON = ['--doodle-pink','--doodle-lime','--doodle-yellow','--doodle-blue',
                '--doodle-purple','--doodle-orange','--doodle-red'];
    var reroll = function (el) {
      var prev = el.getAttribute('data-cv-glow'), n;
      do { n = NEON[(Math.random() * NEON.length) | 0]; } while (n === prev);
      el.setAttribute('data-cv-glow', n);
      el.style.setProperty('--cv-glow', 'var(' + n + ')');
    };
    grid.addEventListener('mouseover', function (e) {
      var el = e.target.closest('.cv-open'); if (!el) return;
      if (e.relatedTarget && el.contains(e.relatedTarget)) return;   /* csak belépéskor */
      reroll(el);
    });
    grid.addEventListener('focusin', function (e) {
      var el = e.target.closest('.cv-open'); if (el) reroll(el);
    });
  }

  /* ---------- (4) MODÁL — a Borkert-popup viselkedése ---------- */
  (function () {
    var modal = document.getElementById('cvModal'); if (!modal) return;
    var mImg = document.getElementById('cvModalImg'),
        mTitle = document.getElementById('cvModalTitle'),
        mText = document.getElementById('cvModalText'),
        mBody = document.getElementById('cvModalBody'),
        last = null;

    function open(btn) {
      last = btn;
      var card = btn.closest('.cv-card');
      var nameEl = btn.querySelector('.cv-name');
      var full = card && card.querySelector('.cv-full');
      var img = btn.getAttribute('data-cv-img') || '';
      var alt = btn.getAttribute('data-cv-alt') || (nameEl ? nameEl.textContent : '');
      mTitle.textContent = nameEl ? nameEl.textContent : '';
      if (mImg) {
        mImg.innerHTML = img ? '<img src="' + img + '" alt="" width="1000" height="563">' : '';
        mImg.setAttribute('aria-label', alt);
      }
      mText.innerHTML = full ? full.innerHTML : '';
      modal.hidden = false;
      document.body.style.overflow = 'hidden';
      mBody.scrollTop = 0;
      requestAnimationFrame(function () { if (mBody.__vsync) mBody.__vsync(); });
      var x = modal.querySelector('.cv-x'); if (x) x.focus();
    }
    function close() {
      modal.hidden = true;
      document.body.style.overflow = '';
      if (last) { try { last.focus(); } catch (e) {} }
    }
    document.addEventListener('click', function (e) {
      var b = e.target.closest('.cv-open'); if (b) { open(b); return; }
      if (e.target.closest('[data-cv-close]')) close();
    });
    document.addEventListener('keydown', function (e) {
      if (modal.hidden) return;
      if (e.key === 'Escape') { close(); return; }
      if (e.key !== 'Tab') return;
      var f = modal.querySelectorAll('button,[href],[tabindex]:not([tabindex="-1"])');
      if (!f.length) return;
      var first = f[0], lastEl = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); lastEl.focus(); }
      else if (!e.shiftKey && document.activeElement === lastEl) { e.preventDefault(); first.focus(); }
    });
  })();
})();
