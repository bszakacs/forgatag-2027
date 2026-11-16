/*
 * A TÉRKÉP-SZERKESZTŐ (PROMPT-18) — a 2026-os MapEditorView.astro szkriptjének átirata.
 *
 * Ami UGYANAZ: térképfülek, minden helyszín a listában (szülő → termei; a teendő kiemelve, az elhelyezett halvány, a szélső
 * „szélen ↗” jelvénnyel), húzás a listából a térképre / a térképen belül / vissza a listába (= levétel), kiválasztott jelölő:
 * sorszám, rövid név, „a térképen” ⇄ „a szélén” + 8 irány, nagyítás, felvillanás, a mentetlen munka helyi vázlata.
 *
 * Ami MÁS:
 *  · NINCS belépés-kezelés (token, megújítás, 401-újrapróbálás): a lap a panel része, a Mentés a panel munkamenetén megy
 *    ($wire.mentes). A 2026-os 401 oka (más címen futó lap írt egy másikra) itt nem létezik.
 *  · A koordináta-átváltás a getScreenCTM().inverse()-szel (a rajzon ülő 0–100-as SVG-rétegen) — nagyítva, görgetve is pontos.
 *  · A húzás Pointer Events (egér + érintés egyben), fogd-és-vidd bővítmény NÉLKÜL. A listában érintésnél a FÜGGŐLEGES
 *    mozdulat a lista görgetése, a vízszintesen induló a húzás (touch-action: pan-y).
 *  · A SZEGÉLYRE ejtve a helyszín a térkép SZÉLÉRE kerül, az ejtés irányával (a 8 irány közül a legközelebbi) — a 2026-os
 *    rádiógomb + iránytábla is megvan, az ejtés csak gyorsabb út ugyanahhoz.
 *  · A mentés EGY kérés, minden-vagy-semmi (App\Support\TerkepMentes); utána a szerver adatával rajzol tovább.
 */
window.fgTerkepSzerkeszto = function (root, wire) {
  'use strict';
  var CFG = JSON.parse(document.getElementById('me-config').textContent);
  var MAPS = CFG.maps;
  var IRANY = CFG.iranyok; // fok → felirat (Terkep::IRANYOK)
  // a nyilak után U+FE0E: szöveges (nem emoji) nyíl — különben a ↖ ↘ színes emojiként jelenik meg
  var NYIL = { 0: '↑', 45: '↗︎', 90: '→', 135: '↘︎', 180: '↓', 225: '↙︎', 270: '←', 315: '↖︎' };
  var ZNEV = { castle: 'vártérkép', city: 'várostérkép' };

  var $ = function (id) { return document.getElementById(id); };
  var q = function (sel, el) { return (el || root).querySelector(sel); };
  var qa = function (sel, el) { return Array.prototype.slice.call((el || root).querySelectorAll(sel)); };

  function status(msg, kind) {
    var el = $('meStatus');
    el.textContent = msg || '';
    el.className = 'me-status' + (kind ? ' is-' + kind : '');
  }
  function alertBox(title, text, btnLabel, btnFn, kind) {
    var box = $('meAlert'), b = $('meAlertBtn');
    if (!title) { box.hidden = true; return; }
    box.hidden = false;
    box.className = 'me-alert' + (kind === 'info' ? ' is-info' : '');
    $('meAlertTitle').textContent = title;
    $('meAlertText').textContent = text || '';
    b.hidden = !btnLabel;
    if (btnLabel) { b.textContent = btnLabel; b.onclick = btnFn || null; }
  }

  /* ---- állapot ---- */
  var V = [];
  var zone = MAPS[0].zone;
  var sel = null;
  var zoomPct = 100;
  var dirty = new Set();

  var byId = function (id) { id = Number(id); return V.find(function (v) { return v.id === id; }); };
  var isEdge = function (v) { return !!v.map_edge && v.map_zone && v.map_zone !== 'none'; };
  var onMap = function (z) { return V.filter(function (v) { return v.map_zone === z && !isEdge(v) && v.map_x != null && v.map_y != null; }); };
  var onEdge = function (z) { return V.filter(function (v) { return v.map_zone === z && isEdge(v); }); };
  var placed = function (v) { return isEdge(v) || (v.map_zone && v.map_zone !== 'none' && v.map_x != null); };
  var unplaced = function () { return V.filter(function (v) { return !placed(v); }); };
  var nextNo = function () { return Math.max.apply(null, [0].concat(V.map(function (v) { return Number(v.map_no) || 0; }))) + 1; };
  var irany = function (deg) { var d = ((Number(deg) || 0) % 360 + 360) % 360; return IRANY[d] || d + '°'; };
  var nyil = function (deg) { var d = ((Number(deg) || 0) % 360 + 360) % 360; return NYIL[d] || '↑'; };

  function syncSaveBtn() {
    var b = $('meSave');
    b.disabled = dirty.size === 0;
    b.textContent = dirty.size ? 'Mentés (' + dirty.size + ')' : 'Mentés';
  }
  function markDirty(v) { dirty.add(v.id); syncSaveBtn(); saveDraft(); }

  /* ---- HELYI VÁZLAT (2026-08-19 óta): a mentetlen pontok a böngészőben is megmaradnak (véletlen újratöltés, bezárt fül,
     lejárt munkamenet ellen). NEM helyettesíti a mentést. ---- */
  var DRAFT_KEY = 'fg-terkep-vazlat-v1';
  var MEZOK = ['map_zone', 'map_x', 'map_y', 'map_no', 'short_name', 'map_edge', 'map_bearing'];
  function sorOf(v) { var r = { id: v.id }; MEZOK.forEach(function (k) { r[k] = v[k]; }); return r; }
  function saveDraft() {
    try {
      if (!dirty.size) { localStorage.removeItem(DRAFT_KEY); return; }
      var rows = Array.from(dirty).map(byId).filter(Boolean).map(function (v) { var r = sorOf(v); r.name = v.name; return r; });
      localStorage.setItem(DRAFT_KEY, JSON.stringify({ at: new Date().toISOString(), rows: rows }));
    } catch (_) { /* tele a tároló / privát mód */ }
  }
  function readDraft() {
    try {
      var d = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null');
      return d && Array.isArray(d.rows) && d.rows.length ? d : null;
    } catch (_) { return null; }
  }
  function clearDraft() { try { localStorage.removeItem(DRAFT_KEY); } catch (_) { } }
  function applyDraft(d) {
    var n = 0;
    d.rows.forEach(function (r) {
      var v = byId(r.id);
      if (!v) return;
      MEZOK.forEach(function (k) { v[k] = r[k]; });
      dirty.add(v.id); n++;
    });
    syncSaveBtn(); render();
    return n;
  }

  /* ---- lista: MINDEN helyszín, ábécé szerint, a termek a szülőjük alatt ---- */
  function orderedVenues() {
    var byName = function (a, b) { return a.name.localeCompare(b.name, 'hu'); };
    var ids = {}; V.forEach(function (v) { ids[v.id] = true; });
    var out = [];
    V.filter(function (v) { return !v.parent || !ids[v.parent]; }).sort(byName).forEach(function (t) {
      out.push({ v: t, child: false });
      V.filter(function (c) { return c.parent === t.id; }).sort(byName).forEach(function (c) { out.push({ v: c, child: true }); });
    });
    return out;
  }

  function renderSide() {
    var list = $('meUnplaced');
    list.innerHTML = '';
    $('meUnplacedN').textContent = (V.length - unplaced().length) + ' / ' + V.length + ' elhelyezve';
    MAPS.forEach(function (m) {
      var el = q('[data-zonecount="' + m.zone + '"]');
      if (el) el.textContent = String(onMap(m.zone).length + onEdge(m.zone).length);
    });
    var s = $('meSearch').value.trim().toLowerCase();
    var shown = 0;
    orderedVenues().forEach(function (row) {
      var v = row.v;
      if (s && v.name.toLowerCase().indexOf(s) < 0) return;
      shown++;
      var edge = isEdge(v), isPl = placed(v);
      var li = document.createElement('li');
      li.className = 'me-chip ' + (edge ? 'is-edge' : isPl ? 'is-placed' : 'is-todo') + (row.child ? ' is-child' : '') + (v.id === sel ? ' is-sel' : '');
      li.dataset.id = v.id;
      li.title = edge
        ? v.name + ' — a ' + ZNEV[v.map_zone] + ' SZÉLÉN, irányjelzővel (' + irany(v.map_bearing) + '). Kattints: felvillan a jelvény.'
        : isPl ? v.name + ' — elhelyezve (' + ZNEV[v.map_zone] + '). Kattints: felvillan a jelölő.'
          : v.name + ' — még nincs a térképen. Húzd rá a térképre.';
      var nm = document.createElement('span');
      nm.className = 'me-cname'; nm.textContent = v.name;
      li.appendChild(nm);
      if (edge) {
        var eb = document.createElement('span');
        eb.className = 'me-cedge'; eb.textContent = 'szélen ' + nyil(v.map_bearing);
        li.appendChild(eb);
      }
      if (isPl && v.map_no != null) {
        var no = document.createElement('span');
        no.className = 'me-cno'; no.textContent = String(v.map_no);
        li.appendChild(no);
      }
      if (dirty.has(v.id)) {
        var d = document.createElement('span');
        d.className = 'me-cdirty'; d.title = 'Mentetlen változás'; d.textContent = '●';
        li.appendChild(d);
      }
      list.appendChild(li);
    });
    if (!shown) {
      var li = document.createElement('li');
      li.className = 'me-msg';
      li.textContent = s ? 'Nincs „' + s + '” nevű helyszín.' : 'Nincs egyetlen helyszín sem a rendszerben.';
      list.appendChild(li);
    }
  }

  function renderMarkers() {
    MAPS.forEach(function (m) {
      var layer = q('[data-layer="' + m.zone + '"]');
      qa('.me-mk', layer).forEach(function (e) { e.remove(); });
      onMap(m.zone).forEach(function (v) {
        var b = document.createElement('div');
        b.className = 'me-mk' + (v.id === sel ? ' is-sel' : '');
        b.dataset.id = v.id;
        b.style.left = v.map_x + '%';
        b.style.top = v.map_y + '%';
        b.setAttribute('role', 'button');
        b.setAttribute('tabindex', '0');
        b.setAttribute('aria-label', v.name + ' — ' + (v.map_no || '?') + '. jelölő');
        b.textContent = v.map_no != null ? String(v.map_no) : '';
        var tip = document.createElement('span');
        tip.className = 'me-mktip'; tip.textContent = v.short_name || v.name;
        b.appendChild(tip);
        layer.appendChild(b);
      });
    });
  }

  /* szélső jelvények — a publikus térkép jelölése (programok/_terkep) és a KÖZÖS elhelyező (terkep-szel.js) */
  var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"/></svg>';
  function renderEdgeBadges() {
    MAPS.forEach(function (m) {
      var wrap = q('[data-offwrap="' + m.zone + '"]');
      var list = onEdge(m.zone).slice().sort(function (a, b) { return (Number(a.map_no) || 0) - (Number(b.map_no) || 0); });
      wrap.innerHTML = list.map(function (v) {
        var b = Number(v.map_bearing) || 0, num = v.map_no != null ? v.map_no : '?';
        return '<button type="button" class="pk-offmap" data-venue="' + v.id + '" aria-pressed="' + (v.id === sel) + '"'
          + ' style="--pk-rot:' + b + 'deg" data-bearing="' + b + '" data-ord="' + (Number(v.map_no) || 0) + '"'
          + ' title="' + esc(v.name) + '" aria-label="' + esc(v.name) + ' — ' + esc(num) + '. jelölő, ' + esc(irany(b)) + '">'
          + '<span class="pk-offnum">' + esc(num) + '</span><span class="pk-offtxt"><b>' + esc(v.short_name || v.name) + '</b></span>' + ARROW + '</button>';
      }).join('');
      var layer = wrap.closest('.me-layer');
      if (layer && window.fgPlaceOffmap) window.fgPlaceOffmap(wrap, layer);
    });
  }

  function renderBearingPicker(v) {
    var box = $('meBearing');
    box.innerHTML = '';
    [315, 0, 45, 270, null, 90, 225, 180, 135].forEach(function (deg) {
      if (deg === null) {
        var c = document.createElement('span');
        c.className = 'me-bcenter'; c.textContent = 'itt';
        box.appendChild(c);
        return;
      }
      var b = document.createElement('button');
      b.type = 'button';
      b.setAttribute('role', 'radio');
      b.setAttribute('aria-checked', String(v.map_bearing != null && Number(v.map_bearing) === deg));
      b.title = irany(deg);
      b.setAttribute('aria-label', irany(deg));
      b.textContent = nyil(deg);
      b.addEventListener('click', function () {
        v.map_bearing = deg;
        markDirty(v); render();
        status('„' + v.name + '” iránya: ' + irany(deg) + '. Ne felejtsd a Mentést.');
      });
      box.appendChild(b);
    });
  }

  function renderInspector() {
    var v = sel ? byId(sel) : null;
    $('meInspBlock').hidden = !v;
    if (!v) return;
    $('meSelName').textContent = v.name;
    $('meSelNo').value = v.map_no != null ? v.map_no : '';
    $('meSelShort').value = v.short_name || '';
    var edge = isEdge(v);
    $('mePlaceMap').checked = !edge && placed(v);
    $('mePlaceEdge').checked = edge;
    $('meEdgeHint').hidden = !edge;
    $('meBearingBox').hidden = !edge;
    if (edge) renderBearingPicker(v);
    $('meSelRemove').textContent = edge ? 'Levétel a szélről' : 'Levétel a térképről';
    $('meSelRemove').hidden = !placed(v);
  }

  function render() { renderSide(); renderMarkers(); renderEdgeBadges(); renderInspector(); }

  /* Elhelyezett helyszínre kattintva a jelölője FELVILLAN (ha a másik térképen van, oda vált). */
  function flashOnMap(id) {
    var v = byId(id);
    sel = v ? v.id : null;
    if (!v || !v.map_zone || v.map_zone === 'none') { render(); return; }
    if (v.map_zone !== zone) switchZone(v.map_zone);
    render();
    var mk = q('[data-layer="' + v.map_zone + '"] .me-mk[data-id="' + v.id + '"]')
      || q('[data-layer="' + v.map_zone + '"] .pk-offmap[data-venue="' + v.id + '"]');
    if (!mk) return;
    mk.scrollIntoView({ block: 'center', inline: 'center', behavior: 'smooth' });
    mk.classList.remove('is-flash'); void mk.offsetWidth; mk.classList.add('is-flash');
    setTimeout(function () { mk.classList.remove('is-flash'); }, 1400);
  }

  /* ---- KOORDINÁTA: képernyő-pont → a rajz saját 0–100-as koordinátája (getScreenCTM().inverse()) ---- */
  function mapPoint(e) {
    var svg = q('[data-layer="' + zone + '"] .me-hit');
    var ctm = svg && svg.getScreenCTM();
    if (!ctm) return null;
    var pt = svg.createSVGPoint();
    pt.x = e.clientX; pt.y = e.clientY;
    return pt.matrixTransform(ctm.inverse());
  }
  /* Hová esett az ejtés: 'map' (a rajzon), 'edge' (a szegélyen), 'side' (a listán), vagy semmi. A görgethető vászon
     LÁTHATÓ részét is nézzük: nagyítva a rajz kilóg a vászonból, a kilógó (nem látható) részre ejteni nem lehet. */
  var inRect = function (e, el) { var r = el.getBoundingClientRect(); return e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom; };
  function dropTarget(e) {
    var canvas = q('[data-canvas="' + zone + '"]');
    if (canvas && inRect(e, canvas)) {
      var p = mapPoint(e);
      if (p && p.x >= 0 && p.x <= 100 && p.y >= 0 && p.y <= 100) return { kind: 'map', p: p };
      if (p && inRect(e, q('[data-frame="' + zone + '"]'))) return { kind: 'edge', p: p };
      return null;
    }
    if (inRect(e, q('.me-side'))) return { kind: 'side' };
    return null;
  }
  /* A szegélyre ejtés iránya: a rajz közepétől az ejtés pontjáig (a rajz valódi arányával), a 8 irány legközelebbike. */
  function bearingFrom(p) {
    var m = MAPS.find(function (x) { return x.zone === zone; });
    var dx = (p.x - 50) * m.w / 100, dy = (p.y - 50) * m.h / 100;
    var deg = Math.atan2(dx, -dy) * 180 / Math.PI;
    return ((Math.round(deg / 45) * 45) % 360 + 360) % 360;
  }

  /* ---- HÚZÁS (Pointer Events) ---- */
  var drag = null; // {id, fromMap, srcEl, ghost, pid, x0, y0, live}
  function arm(e, id, fromMap, srcEl) {
    if (e.button != null && e.button !== 0) return;
    drag = { id: Number(id), fromMap: fromMap, srcEl: srcEl, pid: e.pointerId, x0: e.clientX, y0: e.clientY, live: false, touch: e.pointerType === 'touch' };
    // a jelölőn azonnal (nincs alatta görgetendő lista); a listában csak elmozdulás után indul (a kattintás kattintás marad)
    if (fromMap) { e.preventDefault(); begin(e); }
  }
  function begin(e) {
    var v = byId(drag.id); if (!v) { drag = null; return; }
    drag.live = true;
    var g = document.createElement('div');
    g.className = 'me-ghost';
    g.textContent = (v.map_no != null ? v.map_no + '. ' : '') + (v.short_name || v.name);
    document.body.appendChild(g);
    drag.ghost = g;
    drag.srcEl.classList.add('is-drag');
    try { drag.srcEl.setPointerCapture(drag.pid); } catch (_) { }
    move(e);
  }
  function move(e) {
    if (!drag || e.pointerId !== drag.pid) return;
    if (!drag.live) {
      var dx = Math.abs(e.clientX - drag.x0), dy = Math.abs(e.clientY - drag.y0);
      if (dx < 6 && dy < 6) return;
      // érintésnél a függőleges mozdulat a lista görgetése (a böngésző viszi, pointercancel jön) — csak a vízszintes húz
      if (drag.touch && dy > dx) { drag = null; return; }
      begin(e);
      return;
    }
    e.preventDefault();
    drag.ghost.style.left = e.clientX + 'px';
    drag.ghost.style.top = e.clientY + 'px';
    var t = dropTarget(e);
    q('.me-side').classList.toggle('is-drop', drag.fromMap && !!t && t.kind === 'side');
    qa('.me-frame').forEach(function (f) { f.classList.toggle('is-drop', !!t && t.kind === 'edge' && f.dataset.frame === zone); });
  }
  function end(e) {
    if (!drag || e.pointerId !== drag.pid) return;
    var d = drag; drag = null;
    if (!d.live) return; // sima kattintás — a click-kezelő viszi
    d.ghost.remove();
    d.srcEl.classList.remove('is-drag');
    q('.me-side').classList.remove('is-drop');
    qa('.me-frame').forEach(function (f) { f.classList.remove('is-drop'); });
    suppressClick = true; setTimeout(function () { suppressClick = false; }, 0);
    var v = byId(d.id);
    if (!v) { render(); return; }
    var t = e.type === 'pointercancel' ? null : dropTarget(e);
    if (d.fromMap && t && t.kind === 'side') { removeFromMap(v); render(); return; }
    if (!placeAt(v, t)) status('A húzás megszakadt — a jelölő a helyén maradt.');
    render();
  }
  /* Elhelyezés a célpont szerint (húzás VAGY koppintás): a rajzra → pont, a szegélyre → szélső jelvény. */
  function placeAt(v, t) {
    disarm();
    if (t && t.kind === 'map') {
      v.map_zone = zone;
      v.map_edge = false; v.map_bearing = null;
      v.map_x = Math.round(t.p.x * 100) / 100;
      v.map_y = Math.round(t.p.y * 100) / 100;
      if (v.map_no == null) v.map_no = nextNo();
      sel = v.id;
      markDirty(v);
      status('„' + v.name + '” elhelyezve (' + v.map_x + '% / ' + v.map_y + '%). Ne felejtsd a Mentést.');
    } else if (t && t.kind === 'edge') {
      v.map_zone = zone;
      v.map_edge = true; v.map_x = null; v.map_y = null;
      v.map_bearing = bearingFrom(t.p);
      if (v.map_no == null) v.map_no = nextNo();
      sel = v.id;
      markDirty(v);
      status('„' + v.name + '” a ' + ZNEV[zone] + ' szélére került (' + irany(v.map_bearing) + '). Ne felejtsd a Mentést.');
    } else {
      return false;
    }
    return true;
  }

  /* KOPPINTÁSOS ELHELYEZÉS (telefonon a lista és a térkép egymás alatt van — oda ujjal nem lehet áthúzni): a listában
     a helyszínre koppintva „élesedik”, a következő koppintás a térképen (vagy a szegélyen) oda teszi. */
  var armed = null;
  function arm2(v) {
    armed = v.id;
    var b = $('meArmed');
    b.hidden = false;
    $('meArmedText').textContent = '„' + v.name + '”: koppints a térképre, ahová kerüljön — a szegélyre koppintva a szélére kerül.';
    if (window.matchMedia('(max-width: 900px)').matches) q('.me-map').scrollIntoView({ block: 'start', behavior: 'smooth' });
  }
  function disarm() { armed = null; var b = $('meArmed'); if (b) b.hidden = true; }
  var suppressClick = false;

  function removeFromMap(v) {
    v.map_zone = 'none'; v.map_x = null; v.map_y = null; v.map_no = null;
    v.map_edge = false; v.map_bearing = null;
    if (sel === v.id) sel = null;
    markDirty(v);
    status('„' + v.name + '” lekerült a térképről. Ne felejtsd a Mentést.');
  }

  $('meUnplaced').addEventListener('pointerdown', function (e) {
    var li = e.target.closest('.me-chip'); if (!li) return;
    arm(e, li.dataset.id, false, li);
  });
  $('meUnplaced').addEventListener('click', function (e) {
    if (suppressClick) return;
    var li = e.target.closest('.me-chip'); if (!li) return;
    flashOnMap(li.dataset.id);
    var v = byId(li.dataset.id);
    if (v) arm2(v);
  });
  $('meArmedNo').addEventListener('click', function () { disarm(); status('Elhelyezés megszakítva.'); });
  var mapSec = q('.me-map');
  mapSec.addEventListener('click', function (e) {
    if (suppressClick) return;
    var badge = e.target.closest('.pk-offmap');
    if (badge) { sel = Number(badge.dataset.venue); disarm(); render(); return; }
    if (armed == null || e.target.closest('.me-mk')) return;
    var t = dropTarget(e);
    var v = byId(armed);
    if (v && t && (t.kind === 'map' || t.kind === 'edge')) { placeAt(v, t); render(); }
  });
  mapSec.addEventListener('pointerdown', function (e) {
    var mk = e.target.closest('.me-mk'); if (!mk) return;
    disarm();
    sel = Number(mk.dataset.id); renderInspector(); renderSide();
    qa('.me-mk').forEach(function (x) { x.classList.toggle('is-sel', x === mk); });
    arm(e, mk.dataset.id, true, mk);
  });
  mapSec.addEventListener('keydown', function (e) {
    var mk = e.target.closest('.me-mk');
    if (mk && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); sel = Number(mk.dataset.id); render(); }
  });
  document.addEventListener('pointermove', move, { passive: false });
  document.addEventListener('pointerup', end);
  document.addEventListener('pointercancel', function (e) {
    if (drag && !drag.live && e.pointerId === drag.pid) { drag = null; return; }
    end(e);
  });

  /* ---- a kiválasztott jelölő ---- */
  $('meSelNo').addEventListener('change', function () {
    var v = sel && byId(sel); if (!v) return;
    var n = parseInt(this.value, 10);
    v.map_no = isFinite(n) && n > 0 ? n : null;
    var clash = V.filter(function (x) { return x.id !== v.id && v.map_no != null && Number(x.map_no) === v.map_no; });
    markDirty(v); render();
    status(clash.length
      ? 'FIGYELEM: a ' + v.map_no + '. sorszám már a(z) „' + clash[0].name + '” helyszínnél is szerepel.'
      : 'Sorszám: ' + (v.map_no != null ? v.map_no : '—'), clash.length ? 'err' : null);
  });
  $('meSelShort').addEventListener('change', function () {
    var v = sel && byId(sel); if (!v) return;
    v.short_name = this.value.trim() || null;
    markDirty(v); render(); status('Rövid név beírva.');
  });
  /* SZÉLRE: a pozíció törlődik, az irány alapból „fel”. VISSZA A TÉRKÉPRE: a pontot újra be kell húzni — nem tippelünk. */
  $('mePlaceEdge').addEventListener('change', function () {
    var v = sel && byId(sel); if (!v) return;
    if (!v.map_zone || v.map_zone === 'none') v.map_zone = zone;
    v.map_edge = true; v.map_x = null; v.map_y = null;
    if (v.map_bearing == null) v.map_bearing = 0;
    if (v.map_no == null) v.map_no = nextNo();
    markDirty(v); render();
    status('„' + v.name + '” a ' + ZNEV[v.map_zone] + ' szélére került (' + irany(v.map_bearing) + '). Ne felejtsd a Mentést.');
  });
  $('mePlaceMap').addEventListener('change', function () {
    var v = sel && byId(sel); if (!v) return;
    if (isEdge(v)) { removeFromMap(v); sel = v.id; }
    render();
    status('„' + v.name + '” mostantól a térképen jelenik meg — húzd rá a pontot a helyére.');
  });
  $('meSelRemove').addEventListener('click', function () {
    var v = sel && byId(sel); if (!v) return;
    removeFromMap(v); render();
  });
  $('meSearch').addEventListener('input', renderSide);

  /* ---- térképfülek + nagyítás ---- */
  function switchZone(z) {
    zone = z;
    qa('.me-maptab').forEach(function (x) { x.setAttribute('aria-selected', String(x.dataset.zone === z)); });
    qa('[data-canvas]').forEach(function (c) { c.hidden = c.dataset.canvas !== z; });
    setTimeout(renderEdgeBadges, 30); // a most láthatóvá vált vászon csak ekkor mérhető
  }
  qa('.me-maptab').forEach(function (b) {
    b.addEventListener('click', function () { if (armed == null) sel = null; switchZone(b.dataset.zone); render(); });
  });
  function applyZoom() {
    $('meZoomVal').textContent = zoomPct + '%';
    qa('.me-frame').forEach(function (l) { l.style.width = zoomPct + '%'; });
    setTimeout(renderEdgeBadges, 30);
  }
  window.addEventListener('resize', function () { setTimeout(renderEdgeBadges, 60); });
  $('meZoomIn').addEventListener('click', function () { zoomPct = Math.min(400, zoomPct + 50); applyZoom(); });
  $('meZoomOut').addEventListener('click', function () { zoomPct = Math.max(100, zoomPct - 50); applyZoom(); });
  qa('.me-layer img').forEach(function (img) { img.addEventListener('load', renderEdgeBadges); });

  /* ---- MENTÉS: egy kérés, a panel munkamenetén ---- */
  $('meSave').addEventListener('click', function () {
    if (!dirty.size) return;
    var rows = Array.from(dirty).map(byId).filter(Boolean).map(sorOf);
    $('meSave').disabled = true;
    status('Mentés… (' + rows.length + ' helyszín)');
    Promise.resolve(wire.mentes(rows)).then(function (r) {
      if (!r || !r.ok) {
        var msg = (r && r.hiba) || 'ismeretlen hiba';
        status('A mentés NEM történt meg: ' + msg, 'err');
        alertBox('A mentés nem történt meg', msg + ' — Semmi nem íródott ki; a húzásaid itt maradtak (a böngésző is megőrizte őket).');
        syncSaveBtn();
        return;
      }
      V = r.venues; dirty.clear(); clearDraft(); syncSaveBtn(); alertBox(null); render();
      status(r.n + ' helyszín mentve. A publikus térképen már így látszik.', 'ok');
    }, function (err) {
      status('A mentés NEM történt meg: a szerver nem válaszolt (' + (err && err.message ? err.message : 'hálózati hiba') + ').', 'err');
      alertBox('A mentés nem történt meg', 'A szerver nem válaszolt, vagy a munkamenet lejárt. A húzásaid nem vesztek el: a böngésző megőrizte őket — töltsd újra a lapot, lépj be, ha kell, és a „Visszatöltés” után mentsd újra.');
      syncSaveBtn();
    });
  });

  window.addEventListener('beforeunload', function (e) {
    if (dirty.size) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ---- indulás ---- */
  V = CFG.venues;
  var d = readDraft();
  if (d) {
    alertBox('Mentetlen pontok vannak a böngészőben',
      d.rows.length + ' pont maradt mentés nélkül (' + new Date(d.at).toLocaleString('hu-HU') + '). Visszatöltöm őket a térképre, '
      + 'utána a Mentéssel véglegesítheted.',
      'Visszatöltés', function () {
        var n = applyDraft(d);
        alertBox(null);
        status(n + ' mentetlen pont visszatöltve — most nyomd meg a Mentést.', 'ok');
      }, 'info');
  }
  status(V.length + ' helyszín betöltve.', 'ok');
  render(); applyZoom();
};
