/*
 * PROGRAMKERESŐ — a kliens-réteg. A SZŰRÉS a szerveren fut (App\Support\ProgramKereso); ez a fájl a 2026-os
 * `programkereso/script.js` KEZELÉSÉT és ELRENDEZÉSI viselkedését adja (CLAUDE.md 2/e) — a 2026-os kód blokkjai, ahol
 * lehet, változatlanul (lásd a „2026:” megjegyzéseket). Nélküle is minden működik (GET-űrlap, linkek).
 *
 *   · a szűrő-állapot kezelése az ŰRLAPON (az az igazság): a kiválasztott nap / „Most” újbóli megnyomása kikapcsolja,
 *     a „Most” és a napszak kizárja egymást, a gépelés minden napra vált (kiürítve vissza a kezdőnapra), az aktív
 *     szűrő-címke törlése a mezőt állítja — a kérést a htmx indítja az űrlap `change` eseményére;
 *   · a gombok kijelölése AZONNAL (a lista egy körfordulóval később jön);
 *   · a helyszín- és kategórialista szövegkeresője;
 *   · felcsúszó szűrőlap (mobil), legördülők bezárása (asztal), beúszó sáv, a logó mérése, mobil eszközsávok;
 *   · a felugró ablak: fókusz, Esc, háttérre kattintás, megosztás.
 */
(function () {
  var reduceP = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var urlap = function () { return document.getElementById('pkUrlap'); };
  var napRadio = function (ertek) {
    var u = urlap();
    return u ? u.querySelector('input[name="nap"][value="' + ertek + '"]') : null;
  };
  var kerj = function (el) { el.dispatchEvent(new Event('change', { bubbles: true })); };
  /* ékezet-független összevetés (2026: fgNorm) */
  var NORM = function (s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim(); };

  /* ---- a gombok kijelölése az űrlap állapotából (azonnal, a válasz előtt) ---- */
  function jelolesek() {
    var u = urlap();
    if (!u) return;
    u.querySelectorAll('.pk-day, .pk-now').forEach(function (l) {
      var on = l.querySelector('input').checked;
      l.classList.toggle('is-on', on);
    });
    u.querySelectorAll('.pk-chip').forEach(function (l) {
      l.setAttribute('aria-pressed', l.querySelector('input').checked ? 'true' : 'false');
    });
    u.querySelectorAll('.pk-opt').forEach(function (l) {
      var on = l.querySelector('input').checked;
      l.classList.toggle('is-on', on);
      if (on) l.classList.remove('is-off');
    });
  }

  document.addEventListener('click', function (e) {
    /* 2026: a kiválasztott nap / „Most” újbóli megnyomása kikapcsolja (S.day = null / S.now = false). A címke után a
       böngésző a rádióra is „kattint” — az már bejelölt állapotban fut ide, azt hagyni kell. */
    var nap = e.target.closest && e.target.closest('#pkDays .pk-day, #pkDays .pk-now');
    if (nap && e.target.tagName !== 'INPUT' && nap.querySelector('input').checked) {
      e.preventDefault();
      var mind = napRadio('mind');
      mind.checked = true;
      jelolesek();
      kerj(mind);
      return;
    }
    /* AKTÍV SZŰRŐ-CÍMKE / „Összes törlése”: az űrlapot állítja (JS nélkül link). */
    var t = e.target.closest && e.target.closest('[data-pk-torol]');
    if (t && urlap()) {
      e.preventDefault();
      var u = urlap(), mezo = t.dataset.pkTorol, q = document.getElementById('pkQ');
      if (mezo === '*' || mezo === 'q') q.value = '';
      if (mezo === '*') {
        u.querySelectorAll('input[type=checkbox][data-pk-szuro]').forEach(function (i) { i.checked = false; });
      } else if (mezo === 'helyszin' || mezo === 'kategoria' || mezo === 'napszak') {
        u.querySelectorAll('input[name="' + mezo + '"]').forEach(function (i) { if (i.value === t.dataset.ertek) i.checked = false; });
      }
      /* 2026 clearAll: MINDENT töröl, a kiválasztott napot is — nap nélkül a lista minden programot mutat. */
      var m2 = napRadio('mind');
      if (mezo === '*' || mezo === 'nap') m2.checked = true;
      jelolesek();
      kerj(m2);
      var rs = document.querySelector('[data-section="programs-results"]');
      if (mezo === '*' && rs) {
        var top = rs.getBoundingClientRect().top + window.scrollY - 90;
        if (window.scrollY > top) window.scrollTo({ top: Math.max(0, top), behavior: reduceP ? 'auto' : 'smooth' });
      }
    }
  });

  document.addEventListener('change', function (e) {
    var i = e.target;
    if (!i.matches || !i.matches('#pkUrlap [data-pk-szuro]')) return;
    /* 2026: a „Most zajlik” és a napszak egymást kizárja */
    if (i.name === 'nap' && i.value === 'most' && i.checked) {
      urlap().querySelectorAll('input[name="napszak"]').forEach(function (x) { x.checked = false; });
    }
    if (i.name === 'napszak' && i.checked) {
      var most = napRadio('most');
      if (most && most.checked) napRadio('mind').checked = true;
    }
    jelolesek();
  }, true);

  document.addEventListener('input', function (e) {
    if (e.target.id === 'pkQ') {
      /* 2026 (programkereso/script.js 690–692. sor): a keresés FELÜLÍRJA a napsávot („minden nap”, a „Most” is ki);
         a kiürítés — ha közben nem a „Most” van bekapcsolva — vissza a kezdőnapra (startDayIdx: egy NAP, nem a „Most”). */
      if (e.target.value.trim() !== '') {
        napRadio('mind').checked = true;
      } else if (!napRadio('most').checked) {
        var kezdo = napRadio(urlap().dataset.kezdonap);
        if (kezdo) kezdo.checked = true;
      }
      jelolesek();
    }
    if (e.target.id === 'pkVenueQ' || e.target.id === 'pkCatQ') listaSzures();
  });

  /* A LISTA SZÖVEGKERESŐJE (2026: buildVenues/buildCats): a nem illő sor rejtve; a KIJELÖLT mindig látszik. */
  function listaSzures() {
    [['pkVenueQ', '#pkVenues .pk-opt'], ['pkCatQ', '#pkCats .pk-opt']].forEach(function (par) {
      var mezo = document.getElementById(par[0]);
      var q = NORM(mezo ? mezo.value : '');
      document.querySelectorAll(par[1]).forEach(function (l) {
        l.hidden = !!q && NORM(l.dataset.nev).indexOf(q) < 0 && !l.querySelector('input').checked;
      });
    });
  }

  /* ---- 2026: legördülők a vízszintes sávban (1041 px fölött) — kívülre kattintás / Esc bezárja ---- */
  function barMode() { return window.matchMedia('(min-width: 1041px)').matches; }
  document.addEventListener('click', function (e) {
    if (!barMode()) return;
    if (e.target.closest && e.target.closest('.pk-mapcol, .pk-map, .pk-deck, #pkDeck')) return;
    document.querySelectorAll('.pk-panel-groups .pk-group[open]').forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape' || !barMode()) return;
    document.querySelectorAll('.pk-panel-groups .pk-group[open]').forEach(function (d) { d.removeAttribute('open'); });
  });

  /* ---- 2026: iOS — a LÁTHATÓ képernyőmagasság (--pk-vv) ---- */
  function vvSync() {
    var vv = window.visualViewport;
    var h = (vv && vv.height) || window.innerHeight || 0;
    if (h > 0) document.documentElement.style.setProperty('--pk-vv', Math.round(h) + 'px');
  }
  vvSync();
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', vvSync);
    window.visualViewport.addEventListener('scroll', vvSync);
  }
  window.addEventListener('resize', vvSync);
  window.addEventListener('orientationchange', function () { setTimeout(vvSync, 120); });

  /* ---- 2026: felcsúszó panel (mobil) ---- */
  function openSheet(target) {
    document.body.classList.add('pk-sheet-open');
    document.body.classList.toggle('pk-sheet-map', target === 'map');
    document.body.classList.toggle('pk-sheet-filter', target !== 'map');
  }
  function closeSheet() {
    document.body.classList.remove('pk-sheet-open', 'pk-sheet-filter', 'pk-sheet-map', 'pk-map-changed');
  }
  document.addEventListener('click', function (e) {
    var zar = e.target.closest && e.target.closest('[data-pk-close-sheet], #pkSheetClose, #pkSheetScrim');
    if (zar) {
      /* az „Alkalmaz” JS nélkül küld; JS-sel a szűrés már megtörtént — csak bezár */
      if (zar.type === 'submit') e.preventDefault();
      closeSheet();
      return;
    }
    var b = e.target.closest && e.target.closest('[data-pk-sheet]');
    if (!b || b.disabled) return;
    if (window.innerWidth > 720) {   /* desktopon a beúszó gomb felgörget a panelhez */
      document.querySelector('.pk-explore').scrollIntoView({ behavior: reduceP ? 'auto' : 'smooth', block: 'start' });
      return;
    }
    openSheet(b.dataset.pkSheet);
  });

  /* ---- 2026: mobil eszközsáv, ragadó panelfej, beúszó sáv, a logó mérése ---- */
  function elrendezes() {
    var toolsFixed = document.querySelector('.pk-mobar-fixed');
    var toolsInline = document.querySelector('.pk-mobar-inline');
    var foot = document.querySelector('footer');
    if (toolsFixed && toolsInline && 'IntersectionObserver' in window) {
      new IntersectionObserver(function (es) {
        toolsFixed.classList.toggle('is-on', !es[0].isIntersecting);
      }, { rootMargin: '-90px 0px 0px 0px', threshold: 0 }).observe(toolsInline);
    }
    if (toolsFixed && foot && 'IntersectionObserver' in window) {
      new IntersectionObserver(function (es) {
        toolsFixed.classList.toggle('is-away', es[0].isIntersecting);
      }, { threshold: 0.06 }).observe(foot);
    }
    var pHead = document.querySelector('.pk-panel-head');
    var pSent = document.getElementById('pkHeadSentinel');
    if (pHead && pSent && 'IntersectionObserver' in window) {
      new IntersectionObserver(function (es) {
        pHead.classList.toggle('is-stuck', !es[0].isIntersecting);
      }, { rootMargin: '-100px 0px 0px 0px', threshold: 0 }).observe(pSent);
    }
  }

  var utilObs = null;
  function buildUtilObs() {
    var util = document.getElementById('pkUtil');
    var sentinel = document.getElementById('pkUtilSentinel');
    if (!util || !sentinel || !('IntersectionObserver' in window)) return;
    if (utilObs) utilObs.disconnect();
    var hd = document.querySelector('header');
    var top = hd ? Math.round(hd.getBoundingClientRect().height) : 80;
    var sav = Math.round(util.getBoundingClientRect().height) || 60;
    utilObs = new IntersectionObserver(function (es) {
      util.classList.toggle('is-on', !es[0].isIntersecting);
    }, { rootMargin: '-' + (top + sav + 4) + 'px 0px 0px 0px', threshold: 0 });
    utilObs.observe(sentinel);
  }
  function syncStickyTop() {
    var hd = document.querySelector('header');
    if (!hd) return;
    var t = Math.round(hd.getBoundingClientRect().height) - 1;
    document.querySelectorAll('.pk-utilbar, .pk-panel-head').forEach(function (e) {
      e.style.setProperty('--pk-sticky-top', t + 'px');
    });
    /* A logó SZÁNDÉKOSAN lenyúlik a fejléc alá: a beúszó sáv tartalmát annyival húzzuk be, ameddig ér. */
    var inn = document.querySelector('.pk-utilbar-in');
    var lg = document.querySelector('.logo img') || document.querySelector('.logo');
    if (inn && lg) {
      inn.style.paddingLeft = '';
      var lr = lg.getBoundingClientRect();
      var kids = [].slice.call(inn.children).filter(function (k) {
        return k.offsetWidth > 0 && getComputedStyle(k).display !== 'none';
      });
      var left = Infinity, top2 = Infinity, bottom = -Infinity;
      kids.forEach(function (k) {
        var r = k.getBoundingClientRect();
        left = Math.min(left, r.left); top2 = Math.min(top2, r.top); bottom = Math.max(bottom, r.bottom);
      });
      var hits = kids.length && left < lr.right && bottom > lr.top && top2 < lr.bottom;
      if (hits) inn.style.paddingLeft = Math.round(lr.right - inn.getBoundingClientRect().left + 14) + 'px';
    }
    /* A LELÓGÓ LOGÓ ÉS A SZŰRŐSÁV (2026-08-26, tulajdonosi döntés): a sáv teljes szélességű marad; a legfelső sora a
       TALÁLATSZÁM (nem kattintható) — bal behúzás és minimális magasság a logó MÉRT rect-jéből, így a gombok mindig
       a logó ALATT kezdődnek. */
    var cnt = document.querySelector('.pk-panel .pk-count');
    if (cnt) {
      cnt.style.paddingLeft = '';
      cnt.style.minHeight = '';
      var lg3 = document.querySelector('.logo img') || document.querySelector('.logo');
      if (lg3 && window.matchMedia('(min-width: 1041px)').matches) {
        var lr3 = lg3.getBoundingClientRect();
        var cr = cnt.getBoundingClientRect();
        if (lr3.bottom > cr.top && lr3.right > cr.left) {
          cnt.style.paddingLeft = Math.round(lr3.right + 12 - cr.left) + 'px';
          cnt.style.minHeight = Math.round(lr3.bottom + 6 - cr.top) + 'px';
        }
      }
    }
    var el = document.documentElement;
    var ub = document.getElementById('pkUtil');
    if (ub) el.style.setProperty('--pk-utilbar-h', Math.round(ub.getBoundingClientRect().height) + 'px');
    var mb = document.querySelector('.pk-mobar-fixed');
    if (mb) el.style.setProperty('--pk-mobar-h', Math.round(mb.getBoundingClientRect().height) + 'px');
    buildUtilObs();
  }

  /* ---- a felugró ablak (2026: lib/program-modal.js nyit/zar) ---- */
  var lastFocus = null;
  function modalAllapot() {
    var m = document.getElementById('pkModal');
    if (!m) return;
    if (!m.hidden) {
      if (!lastFocus) lastFocus = document.activeElement;
      document.body.style.overflow = 'hidden';
      var x = m.querySelector('.pk-close');
      if (x) x.focus();
    } else {
      document.body.style.overflow = '';
      if (lastFocus && document.contains(lastFocus)) { try { lastFocus.focus({ preventScroll: true }); } catch (_) { } }
      lastFocus = null;
    }
  }
  document.addEventListener('click', function (e) {
    var sc = e.target.closest && e.target.closest('#pkModal .pk-scrim');
    if (sc) { e.preventDefault(); var x = document.querySelector('#pkModal .pk-close'); if (x) x.click(); return; }
    var sh = e.target.closest && e.target.closest('#pkMShare');
    if (sh) {
      var url = location.href, cim = sh.dataset.cim || document.title;
      if (navigator.share) { navigator.share({ title: cim, url: url }).catch(function () {}); return; }
      if (navigator.clipboard) navigator.clipboard.writeText(url).then(function () {
        var s = sh.querySelector('span'); var regi = s.textContent; s.textContent = sh.dataset.masolva;
        setTimeout(function () { s.textContent = regi; }, 1600);
      });
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var m = document.getElementById('pkModal');
    if (m && !m.hidden) { var x = m.querySelector('.pk-close'); if (x) x.click(); }
  });

  /* ---- htmx-cserék után: a jelvények szülője (has-n) a szerverről jön; a lista-kereső és a kijelölés újra ---- */
  document.addEventListener('htmx:afterSettle', function () {
    listaSzures();
    jelolesek();
    modalAllapot();
  });

  document.addEventListener('DOMContentLoaded', function () {
    jelolesek();
    elrendezes();
    syncStickyTop();
    modalAllapot();
    window.addEventListener('load', syncStickyTop);
    window.addEventListener('resize', function () { clearTimeout(window.__pkST); window.__pkST = setTimeout(syncStickyTop, 120); });
    if ('ResizeObserver' in window) {
      var hdEl = document.querySelector('header');
      if (hdEl) new ResizeObserver(syncStickyTop).observe(hdEl);
      var lgEl = document.querySelector('.logo img') || document.querySelector('.logo');
      if (lgEl) new ResizeObserver(syncStickyTop).observe(lgEl);
    }
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(syncStickyTop);
  });
})();
