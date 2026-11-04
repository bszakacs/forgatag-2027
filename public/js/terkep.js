/*
 * A PROGRAMKERESŐ TÉRKÉPE — a 2026-os kód BETŰRE (CLAUDE.md 2/e):
 *   · fgPlaceOffmap: lib/offmap.ts placeOffmapBadges + bearingSide (a TS-típusok nélkül);
 *   · fgMakeMap:     lib/finder-map.ts FINDER_MAP_PAN_JS (pásztázás, nagyítás, csippentés);
 *   · a kereső-térkép: programkereso/script.js (syncMap, syncLegend, renderDeck, tipShow, a jelmagyarázat, fgMapInit,
 *     mapPlace, mapTargetOf, pingTarget, a popup „Mutasd a térképen” gombja).
 * Ami MÁS (csak a kiszolgálás): a helyszín-halmaz a 2026-os S.venues helyett az ŰRLAP helyszín-pipái (#pk-venue-<slug>);
 * a jelölő a pipát állítja, és a htmx az űrlap `change` eseményére kér. A programszámok a szerverről jönnek, a
 * #pkTerkepAdat adat-elemben (hx-swap-oob) — a térkép EGYSZER rajzolódik, a szűrésre ebből frissül, újrarajzolás nincs.
 */
(function () {
  function bearingSide(bearing) {
    var b = ((Number(bearing) || 0) % 360 + 360) % 360;
    if (b < 45 || b >= 315) return 'top';
    if (b < 135) return 'right';
    if (b < 225) return 'bottom';
    return 'left';
  }
  window.fgPlaceOffmap = function (wrap, canvas) {
    var btns = Array.from(wrap.querySelectorAll('.pk-offmap'));
    if (!btns.length) return;
    var W = canvas.clientWidth;
    var H = canvas.clientHeight;
    if (!W || !H) return;
    var PAD = 6;
    var groups = { top: [], right: [], bottom: [], left: [] };
    btns.forEach(function (el) {
      var bearing = Number(el.dataset.bearing || 0);
      groups[bearingSide(bearing)].push(el);
    });
    Object.keys(groups).forEach(function (sd) {
      var list = groups[sd].sort(function (a, b) {
        return (Number(a.dataset.ord || 0) - Number(b.dataset.ord || 0))
          || (Number(a.dataset.bearing || 0) - Number(b.dataset.bearing || 0));
      });
      list.forEach(function (el, i) {
        var w = el.offsetWidth || 150;
        var h = el.offsetHeight || 30;
        if (sd === 'top' || sd === 'bottom') {
          el.style.left = Math.round(PAD) + 'px';
          el.style.top = Math.round(sd === 'top' ? PAD + i * (h + 4) : H - PAD - h - i * (h + 4)) + 'px';
        } else if (sd === 'right') {
          el.style.left = Math.round(W - PAD - w) + 'px';
          el.style.top = Math.round(Math.min(H - PAD - h, H / 2 - h / 2 + i * (h + 4))) + 'px';
        } else {
          el.style.left = Math.round(PAD) + 'px';
          el.style.top = Math.round(Math.min(H - PAD - h, H / 2 - h / 2 + i * (h + 4))) + 'px';
        }
      });
    });
  };
})();

(function(){
  var MAXZ = 3.5;
  window.fgMapDragged = false;
  window.fgMakeMap = function (canvas, opts) {
    opts = opts || {};
    var layer = canvas.querySelector('.pk-layer');
    if (!layer) return null;
    var AR = (Number(canvas.dataset.w) || 16) / (Number(canvas.dataset.h) || 9);
    var LW = 0, LH = 0, S = { z: 1, tx: 0, ty: 0 }, raf = null;

    function apply() {
      var W = canvas.clientWidth, H = canvas.clientHeight;
      var w = LW * S.z, h = LH * S.z;
      S.tx = (w <= W) ? (W - w) / 2 : Math.min(0, Math.max(W - w, S.tx));
      S.ty = (h <= H) ? (H - h) / 2 : Math.min(0, Math.max(H - h, S.ty));
      layer.style.transform = 'translate(' + S.tx.toFixed(1) + 'px,' + S.ty.toFixed(1) + 'px) scale(' + S.z.toFixed(3) + ')';
      /* A jelölők ellen-skálázásához: a CSS ebből számol 1/z-t (lásd .pk-marker). */
      layer.style.setProperty('--pk-z', S.z.toFixed(3));
      var zr = canvas.parentNode.querySelector('[data-mapzoom="reset"]');
      if (zr) zr.disabled = (S.z <= 1.01);
      if (opts.onmove) opts.onmove();
    }
    function fit(keep) {
      var W = canvas.clientWidth, H = canvas.clientHeight;
      if (!W || !H) return;
      /* Mobilon CONTAIN (a teljes térkép látszik), desktopon COVER (kitölti a keretet)
         — pontosan úgy, ahogy a makett. */
      LW = (window.innerWidth <= 720) ? Math.min(W, H * AR) : Math.max(W, H * AR);
      LH = LW / AR;
      layer.style.width = LW + 'px'; layer.style.height = LH + 'px';
      if (!keep) { S.z = 1; S.tx = (W - LW) / 2; S.ty = (H - LH) / 2; }
      apply();
    }
    function setZoom(nz, px, py) {
      nz = Math.min(MAXZ, Math.max(1, nz));
      var W = canvas.clientWidth, H = canvas.clientHeight;
      if (px == null) { px = W / 2; py = H / 2; }
      var k = nz / S.z;
      S.tx = px - (px - S.tx) * k;
      S.ty = py - (py - S.ty) * k;
      S.z = nz; apply();
    }
    /* helyszínre közelítés: százalékos térkép-koordinátából réteg-koordináta */
    function zoomTo(xPct, yPct) {
      var z = 2.4;
      var lx = (Number(xPct) || 0) / 100 * LW, ly = (Number(yPct) || 0) / 100 * LH;
      S.z = z;
      S.tx = canvas.clientWidth / 2 - lx * z;
      S.ty = canvas.clientHeight / 2 - ly * z;
      apply();
    }

    var img = canvas.querySelector('.pk-mapimg');
    if (img) {
      if (img.complete) fit(false);
      img.addEventListener('load', function () { fit(false); });
    }
    canvas.addEventListener('wheel', function (e) {
      var down = e.deltaY > 0;
      if (down && S.z <= 1.001) return;                 /* alaphelyzetben a LAP görög */
      if (!down && S.z >= MAXZ - 0.001) return;
      e.preventDefault();
      var r = canvas.getBoundingClientRect();
      setZoom(S.z * (down ? 1 / 1.18 : 1.18), e.clientX - r.left, e.clientY - r.top);
    }, { passive: false });
    canvas.addEventListener('dblclick', function (e) {
      var r = canvas.getBoundingClientRect();
      setZoom(S.z < 1.6 ? 2.2 : 1, e.clientX - r.left, e.clientY - r.top);
    });

    var pd = null, pts = {}, pinch = null;
    function two() { var k = Object.keys(pts); return k.length >= 2 ? k.slice(0, 2) : null; }
    function dist(a, b) { var dx = pts[a].x - pts[b].x, dy = pts[a].y - pts[b].y; return Math.sqrt(dx * dx + dy * dy); }

    canvas.addEventListener('pointerdown', function (e) {
      pts[e.pointerId] = { x: e.clientX, y: e.clientY };
      var t = two();
      if (t) {
        pd = null; window.fgMapDragged = true;
        var r = canvas.getBoundingClientRect();
        pinch = { d: dist(t[0], t[1]), z: S.z,
                  cx: (pts[t[0]].x + pts[t[1]].x) / 2 - r.left,
                  cy: (pts[t[0]].y + pts[t[1]].y) / 2 - r.top };
        return;
      }
      pd = { x: e.clientX, y: e.clientY, tx: S.tx, ty: S.ty, id: e.pointerId, cap: false };
      window.fgMapDragged = false;
    });
    canvas.addEventListener('pointermove', function (e) {
      if (pts[e.pointerId]) pts[e.pointerId] = { x: e.clientX, y: e.clientY };
      if (pinch) {
        var t = two();
        if (t) setZoom(pinch.z * (dist(t[0], t[1]) / pinch.d), pinch.cx, pinch.cy);
        return;
      }
      if (!pd) return;
      var dx = e.clientX - pd.x, dy = e.clientY - pd.y;
      if (!window.fgMapDragged && Math.abs(dx) + Math.abs(dy) <= 4) return;
      if (!pd.cap) {
        pd.cap = true; window.fgMapDragged = true;
        try { canvas.setPointerCapture(pd.id); } catch (_) {}
      }
      S.tx = pd.tx + dx; S.ty = pd.ty + dy;
      if (raf) return;
      raf = requestAnimationFrame(function () { raf = null; apply(); });
    });
    function endPan(e) {
      delete pts[e.pointerId];
      if (Object.keys(pts).length < 2) pinch = null;
      if (pd && pd.cap) { try { canvas.releasePointerCapture(pd.id); } catch (_) {} }
      pd = null;
      setTimeout(function () { window.fgMapDragged = false; }, 30);
    }
    canvas.addEventListener('pointerup', endPan);
    canvas.addEventListener('pointercancel', endPan);
    canvas.addEventListener('pointerleave', function () { if (pd && !pd.cap) pd = null; });

    fit(false);
    return { fit: fit, setZoom: setZoom, zoomTo: zoomTo, z: function () { return S.z; } };
  };
})();

(function () {
  var MAPS = {};
  var deck = document.getElementById('pkDeck');
  var tip = document.getElementById('pkTip');
  var terkep = document.querySelector('.pk-map');
  if (!terkep) return;
  var VKIDS = {};
  try { VKIDS = JSON.parse(terkep.dataset.csalad || '{}'); } catch (_) { VKIDS = {}; }
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var vanHover = !window.matchMedia || window.matchMedia('(hover: hover)').matches;

  /* a szerver adata: helyszín-slug → a mostani szűrésre (a helyszín-szűrő NÉLKÜL) eső programszám, a szülőnél a
     termeivel együtt (2026: filtered('venue') + venueFamily) */
  var ADAT = { db: {}, sel: [] };
  function adatOlvas() {
    var el = document.getElementById('pkTerkepAdat');
    if (!el) return;
    try { ADAT = JSON.parse(el.textContent || '{}'); } catch (_) { /* marad a régi */ }
  }
  function db(slug) { return Number((ADAT.db || {})[slug] || 0); }
  /** a helyszín neve (a jelmagyarázat sorából; 2026: VENS[vi][1]) */
  function nevOf(slug) {
    var r = document.querySelector('.pk-legrow[data-legend="' + slug + '"] .pk-legname');
    if (r) return r.textContent;
    var m = document.querySelector('[data-venue="' + slug + '"]');
    return m ? (m.getAttribute('title') || '') : '';
  }

  /* ---- a helyszín-halmaz = az ŰRLAP helyszín-pipái (2026: S.venues) ---- */
  function box(id) { return document.getElementById('pk-venue-' + id); }
  function venueOn(id) { var b = box(id); return !!(b && b.checked); }
  var utolso = null;
  function setVenue(id, on) { var b = box(id); if (b) { b.checked = !!on; utolso = b; } }
  /* „LAP” MÓD (a /terkep, PROMPT-15, tulajdonosi döntés): nincs szűrő-űrlap — a jelölő, a pakli terme, a jelmagyarázat sora
     és a szélső jelvény a helyszín LAPJÁRA visz. Minden más (fülek, nagyítás, pakli, buborék, kiemelés) ugyanaz. */
  var LAP = terkep.dataset.mod === 'lap';
  function toggleVenue(id) {
    if (LAP) { location.href = terkep.dataset.helyszinUt + encodeURIComponent(id); return; }
    setVenue(id, !venueOn(id));
  }
  function venueFamily(id) { return [String(id)].concat((VKIDS[String(id)] || []).map(String)); }
  function venueSel() {
    var sel = {};
    document.querySelectorAll('#pkUrlap input[name="helyszin"]:checked').forEach(function (b) {
      venueFamily(b.value).forEach(function (x) { sel[x] = 1; });
    });
    return sel;
  }
  /** 2026 render(): itt a kérést a htmx indítja (az űrlap `change`-e); a térkép és a lista AZONNAL a pipákat mutatja,
      a számok a válasszal jönnek. */
  function render() {
    if (utolso) utolso.dispatchEvent(new Event('change', { bubbles: true }));
    utolso = null;
    if (document.body.classList.contains('pk-sheet-map')) document.body.classList.add('pk-map-changed');
    syncMap();
    syncLegend();
  }

  function mapCanvas(zone) { return document.querySelector('[data-canvas="' + zone + '"]'); }
  /* A szélső jelvények a VÁSZON keretéhez igazodnak (nem a mozgó réteghez), ezért
     pásztázáskor a helyükön maradnak. */
  function placeEdges() {
    if (!window.fgPlaceOffmap) return;
    ['castle', 'city'].forEach(function (z) {
      var wrap = document.querySelector('[data-offwrap="' + z + '"]');
      var canvas = wrap && wrap.closest('.pk-canvas');
      if (wrap && canvas) window.fgPlaceOffmap(wrap, canvas);
    });
  }

  function syncMap() {
    var sel = venueSel();
    document.querySelectorAll('.pk-marker, .pk-offmap').forEach(function (el) {
      var vi = el.dataset.venue;
      var kids = (el.dataset.kids || '').split(',').filter(Boolean);
      /* a KITERJESZTETT halmazból: „az egész helyszín" a termeit is jelöltté teszi */
      var on = !!sel[vi] || kids.some(function (k) { return !!sel[k]; });
      el.setAttribute('aria-pressed', on ? 'true' : 'false');
      var any = db(vi) > 0 || kids.some(function (k) { return db(k) > 0; });
      el.classList.toggle('is-off', !any && !on);
    });
    if (deck && !deck.hidden) renderDeck(deck.dataset.venue, deck.dataset.kids, null, true);
  }

  /** A HELYSZÍNLISTA a közös halmaz megjelenítése: kiválasztott állapot + a
      mostani szűrésre eső programszám (a szülő sorában a termekkel együtt). */
  function syncLegend() {
    var sorok = document.querySelectorAll('.pk-legrow[data-legend]');
    if (!sorok.length) return;
    sorok.forEach(function (row) {
      var id = row.dataset.legend;
      var n = db(id);
      var cimke = row.querySelector('.pk-legn');
      if (cimke) cimke.textContent = n;
      row.setAttribute('aria-pressed', venueOn(id) ? 'true' : 'false');
      row.classList.toggle('is-on', venueOn(id));
      row.classList.toggle('is-empty', n === 0);
    });
  }

  function renderDeck(vi, kidsStr, anchor, keepPos) {
    if (!deck) return;
    var kids = (kidsStr || '').split(',').filter(Boolean);
    deck.dataset.venue = vi; deck.dataset.kids = kidsStr || '';
    deck.innerHTML = '';
    var title = document.createElement('b');
    title.textContent = nevOf(vi);
    deck.appendChild(title);
    var sel = venueSel();
    [[vi, terkep.dataset.egesz || 'Az egész helyszín']].concat(kids.map(function (k) {
      return [k, nevOf(k)];
    })).forEach(function (pair) {
      var b = document.createElement('button');
      b.type = 'button';
      /* a terem akkor is jelöltnek látszik, ha „az egész helyszín" van kiválasztva */
      b.setAttribute('aria-pressed', sel[pair[0]] ? 'true' : 'false');
      b.textContent = pair[1];
      b.addEventListener('click', function () {
        /* Ha az EGÉSZ helyszín van kiválasztva, és most egy TERMET választ:
           szűkítés — a szülő kikerül, a terem bekerül. */
        if (pair[0] !== vi && venueOn(vi)) { setVenue(vi, false); setVenue(pair[0], true); }
        else toggleVenue(pair[0]);
        render();
      });
      deck.appendChild(b);
    });
    deck.hidden = false;
    tipHide();                                        /* buborék mellett ne legyen tooltip */
    if (!keepPos && anchor) {
      var st = anchor.closest('.pk-mapstage').getBoundingClientRect();
      var ar = anchor.getBoundingClientRect();
      deck.style.left = Math.max(6, Math.min(st.width - 200, ar.left - st.left + 18)) + 'px';
      deck.style.top = Math.max(6, ar.top - st.top + 18) + 'px';
    }
  }

  /* ---- HELYSZÍN-BUBORÉK a jelölő fölött (2026: tipShow) ---- */
  /** Hány program esik a helyszínre (a paklinál a termekkel együtt) a mostani szűrésben. */
  function tipCount(el) { return db(el.dataset.venue); }
  function tipHide() { if (tip) tip.classList.remove('is-on'); }
  function tipShow(el) {
    if (!tip || !el) return;
    /* nyitott pakli-buborék mellett ne legyen tooltip is (a makett szabálya) */
    if (deck && !deck.hidden) return;
    var stage = el.closest('.pk-mapstage');
    var canvas = el.closest('.pk-canvas');
    if (!stage || !canvas) return;
    var nev = nevOf(el.dataset.venue) || el.getAttribute('title') || '';
    tip.innerHTML = '<b></b>';
    tip.querySelector('b').textContent = nev;                 // a nevet NE HTML-ként
    tip.appendChild(document.createTextNode(' · ' + tipCount(el) + ' ' + (terkep.dataset.programszo || 'program')));
    var sr = stage.getBoundingClientRect();
    var cr = canvas.getBoundingClientRect();
    var r = el.getBoundingClientRect();
    tip.classList.remove('is-below');
    tip.classList.add('is-on');                               // mérni csak megjelenítve lehet
    var w = tip.offsetWidth, h = tip.offsetHeight;
    var kozep = r.left - sr.left + r.width / 2;
    var balHatar = cr.left - sr.left + w / 2 + 4;
    var jobbHatar = cr.right - sr.left - w / 2 - 4;
    if (jobbHatar > balHatar) kozep = Math.max(balHatar, Math.min(jobbHatar, kozep));
    tip.style.left = Math.round(kozep) + 'px';
    var fent = r.top - sr.top;
    if (r.top - h - 8 < cr.top) { tip.classList.add('is-below'); fent = r.bottom - sr.top; }
    tip.style.top = Math.round(fent) + 'px';
  }

  /* ---- 2026 mapPlace: asztalon a találatok melletti hasábba, keskeny nézetben a térképlapra ---- */
  function mapPlace() {
    var col = document.getElementById('pkMapCol');
    var sheet = document.getElementById('pkMapSheetIn');
    if (!col || !sheet) return;
    var cel = window.matchMedia('(min-width: 1041px)').matches ? col : sheet;
    if (terkep.parentNode !== cel) cel.appendChild(terkep);
  }

  /* ---- HELYSZÍNLISTA: kiemelés ÉS szűrés (2026-08-23) ---- */
  function legendTarget(vi) {
    return document.querySelector('.pk-marker[data-venue="' + vi + '"], .pk-offmap[data-venue="' + vi + '"]')
      || (function () {
        var jelolt = [].slice.call(document.querySelectorAll('.pk-marker[data-kids]'));
        for (var i = 0; i < jelolt.length; i++) {
          if ((jelolt[i].dataset.kids || '').split(',').indexOf(String(vi)) >= 0) return jelolt[i];
        }
        return null;
      })();
  }
  function legendZoneActive(row) {
    var zone = row.dataset.zone;
    if (!zone) return false;
    var tab = document.querySelector('.pk-maptab[data-mapzone="' + zone + '"]');
    return !!tab && tab.getAttribute('aria-selected') === 'true';
  }
  function legendHint(row) {
    if (!legendZoneActive(row)) return;             /* másik térkép: ne villogjon */
    var cel = legendTarget(row.dataset.legend);
    if (cel) pingTarget(cel);
  }
  function legendPick(row) {
    var zone = row.dataset.zone;
    var tab = zone && document.querySelector('.pk-maptab[data-mapzone="' + zone + '"]');
    if (tab && tab.getAttribute('aria-selected') !== 'true') tab.click();
    toggleVenue(row.dataset.legend);
    render();
    var cel = legendTarget(row.dataset.legend);
    if (cel) pingTarget(cel);
  }
  document.addEventListener('mouseover', function (e) {
    var row = e.target.closest && e.target.closest('.pk-legrow[data-legend]');
    if (row) legendHint(row);
  });
  document.addEventListener('focusin', function (e) {
    var row = e.target.closest && e.target.closest('.pk-legrow[data-legend]');
    if (row) legendHint(row);
  });
  document.addEventListener('click', function (e) {
    var row = e.target.closest && e.target.closest('.pk-legrow[data-legend]');
    if (row) { e.preventDefault(); legendPick(row); }
  });

  /* ---- „MUTASD A TÉRKÉPEN” kiemelés (2026: mapTargetOf, pingTarget) ---- */
  function mapTargetOf(vi) {
    if (!vi) return null;
    return document.querySelector('.pk-marker[data-venue="' + vi + '"], .pk-offmap[data-venue="' + vi + '"]')
      /* terem (gyerek-helyszín): a SZÜLŐ pakli-jelölője képviseli */
      || legendTarget(vi);
  }
  var aktivPing = { el: null, ring: null };
  function pingStop() {
    if (aktivPing.el) aktivPing.el.classList.remove('is-ping');
    if (aktivPing.ring && aktivPing.ring.parentNode) aktivPing.ring.parentNode.removeChild(aktivPing.ring);
    aktivPing = { el: null, ring: null };
  }
  function pingTarget(el) {
    if (!el) return;
    pingStop();                               /* az előző kiemelés leáll */
    el.classList.add('is-ping');
    aktivPing.el = el;
    if (reduceMotion) return;                 /* csökkentett mozgás: csak a kiemelt szín */
    var szulo = el.closest('.pk-marks') || el.closest('.pk-offwrap');
    if (!szulo) return;
    var ring = document.createElement('span');
    ring.className = 'pk-ping';
    ring.setAttribute('aria-hidden', 'true');
    if (el.classList.contains('pk-marker')) {
      ring.style.left = el.style.left; ring.style.top = el.style.top;
    } else {
      var r = el.getBoundingClientRect(), sr = szulo.getBoundingClientRect();
      ring.style.left = (r.left - sr.left + r.width / 2) + 'px';
      ring.style.top = (r.top - sr.top + r.height / 2) + 'px';
    }
    szulo.appendChild(ring);
    aktivPing.ring = ring;
  }

  /* A popup gombja: csak ha a helyszín SZEREPEL a térképen (2026: fgProgModal `terkep`). A popup htmx-szel jön. */
  function mmapAllapot() {
    var b = document.getElementById('pkMMap');
    if (!b) return;
    var van = !!mapTargetOf(b.dataset.venue);
    b.hidden = !van;
    b.disabled = !van;
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('#pkMMap');
    if (!b) return;
    var cel = mapTargetOf(b.dataset.venue);
    if (!cel) return;
    /* SORREND (2026-08-25): popup bezárása (a görgetés-zár azonnal feloldva) → fülváltás → a következő képkockában
       görgetés. A bezárás a popup saját X-ével megy (htmx: a cím is visszaáll). */
    var m = document.getElementById('pkModal');
    var x = m && m.querySelector('.pk-close');
    if (m) m.hidden = true;
    document.body.style.overflow = '';
    if (x) x.click();
    var view = cel.closest('.pk-mapview');
    var zone = view ? view.id.replace('pkMap-', '') : null;
    var tab = zone ? document.querySelector('.pk-maptab[data-mapzone="' + zone + '"]') : null;
    if (tab && tab.getAttribute('aria-selected') !== 'true') tab.click();
    var mobil = window.matchMedia('(max-width:720px)').matches;
    var utan = function () {
      if (mobil) { var sb = document.querySelector('[data-pk-sheet="map"]'); if (sb) sb.click(); return; }
      var stage = document.querySelector('.pk-mapstage') || terkep;
      stage.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
    };
    requestAnimationFrame(function () { requestAnimationFrame(utan); });
    setTimeout(function () { pingTarget(cel); }, mobil ? 520 : (tab ? 300 : 160));
  });

  /* a térképlap megnyitásakor a vászon csak ekkor mérhető (2026 openSheet) */
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-pk-sheet="map"]');
    if (!b || b.disabled) return;
    setTimeout(function () {
      Object.keys(MAPS).forEach(function (z) { if (MAPS[z]) MAPS[z].fit(false); });
      placeEdges();
    }, 160);
  });

  function fgMapInit() {
    /* jelölő / szélső jelvény → helyszín-szűrő */
    document.querySelectorAll('.pk-marker, .pk-offmap').forEach(function (el) {
      el.addEventListener('click', function () {
        /* pásztázás után NE váltson szűrőt (a húzás vége kattintást is küldhet) */
        if (window.fgMapDragged) return;
        var kids = (el.dataset.kids || '').split(',').filter(Boolean);
        if (kids.length) { renderDeck(el.dataset.venue, el.dataset.kids, el); return; }
        toggleVenue(el.dataset.venue);
        render();
      });
      if (el.classList.contains('pk-marker')) {
        if (vanHover) {
          el.addEventListener('mouseenter', function () { tipShow(el); });
          el.addEventListener('mouseleave', tipHide);
        }
        el.addEventListener('focus', function () {
          var kbd = true;
          try { kbd = el.matches(':focus-visible'); } catch (_) { }
          if (kbd) tipShow(el);
        });
        el.addEventListener('blur', tipHide);
        el.addEventListener('click', tipHide);
      }
    });
    ['castle', 'city'].forEach(function (z) {
      var cv = mapCanvas(z);
      if (cv) MAPS[z] = window.fgMakeMap(cv, { onmove: function () {
        if (deck && !deck.hidden && window.fgMapDragged) deck.hidden = true;
        tipHide();
      } });
    });
    document.querySelectorAll('.pk-maptab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        var z = tab.dataset.mapzone;
        document.querySelectorAll('.pk-maptab').forEach(function (x) { x.setAttribute('aria-selected', String(x === tab)); });
        document.querySelectorAll('.pk-mapview').forEach(function (v) { v.hidden = (v.id !== 'pkMap-' + z); });
        if (deck) deck.hidden = true;
        tipHide();
        setTimeout(function () { if (MAPS[z]) MAPS[z].fit(false); placeEdges(); }, 30);
      });
    });
    document.querySelectorAll('[data-mapzoom]').forEach(function (b) {
      b.addEventListener('click', function () {
        var view = b.closest('.pk-mapview');
        var m = MAPS[view.id.replace('pkMap-', '')];
        if (!m) return;
        var mode = b.dataset.mapzoom;
        if (mode === 'reset') m.fit(false);
        else m.setZoom(mode === 'in' ? m.z() * 1.5 : m.z() / 1.5);
      });
    });
    document.addEventListener('click', function (e) {
      if (!deck || deck.hidden) return;
      if (e.target.closest('.pk-deck') || e.target.closest('.pk-marker')) return;
      deck.hidden = true;
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { tipHide(); if (deck && !deck.hidden) deck.hidden = true; }
    });
    window.addEventListener('resize', function () {
      setTimeout(function () {
        mapPlace();
        Object.keys(MAPS).forEach(function (z) { if (MAPS[z]) MAPS[z].fit(false); });
        placeEdges();
      }, 60);
    });
    placeEdges();
    syncMap();
    syncLegend();
  }

  /* a szerver válasza után: új számok (#pkTerkepAdat), a pipák állapota, a popup gombja — a térkép NEM rajzolódik újra */
  document.addEventListener('htmx:afterSettle', function () {
    adatOlvas();
    syncMap();
    syncLegend();
    mmapAllapot();
  });
  /* a szűrőlista pipája is ugyanaz a halmaz: azonnal látszódjon a térképen */
  document.addEventListener('change', function (e) {
    if (e.target && e.target.name === 'helyszin') { syncMap(); syncLegend(); }
  });

  adatOlvas();
  mapPlace();
  fgMapInit();
  mmapAllapot();
  window.addEventListener('load', function () { placeEdges(); });

  /* „LAP” mód: a helyszínlap „Mutasd a térképen” linkje (?helyszin=<slug>) — a jelölő fülére vált, odagörget és kiemeli
     (ugyanaz a vízgyűrű, mint a programkereső „Mutasd a térképen” gombjánál). */
  if (LAP) {
    var kert = new URLSearchParams(location.search).get('helyszin');
    var cel = kert && legendTarget(kert);
    if (cel) {
      var nezet = cel.closest('.pk-mapview');
      var tab = nezet && document.querySelector('.pk-maptab[aria-controls="' + nezet.id + '"]');
      if (tab && tab.getAttribute('aria-selected') !== 'true') tab.click();
      window.addEventListener('load', function () {
        cel.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
        setTimeout(function () { pingTarget(cel); }, 350);
      });
    }
  }
})();
