/* ============================================================
   Az Arculat-szerkesztő (/admin/tema) — böngészőoldali felület.
   · Az ÉLŐ ELŐNÉZET szerver nélkül fut: minden változásnál a tema-css.js (a PHP-generátor betűhű
     párja) számolja a CSS-t, és beírja az előnézeti iframe-be (requestAnimationFrame-enként egyszer).
   · Szerverhez CSAK mentés, publikálás, feltöltés, gyári- és verzió-betöltés megy (Admin\TemaController).
   · A piszkozat és a publikált téma KÜLÖN él; a szerkesztő mindig kiírja, melyiket mutatja.
   · A felület leírása (szekciók, feliratok, tartományok, kontraszt-szabályok) a szervertől jön
     (App\Support\ThemeEditorSpec) — itt nincs második példánya.
   ============================================================ */
(function () {
  'use strict';

  var CFG = JSON.parse(document.getElementById('te-config').textContent);
  var SPEC = CFG.spec;
  var get = window.FgThemeCss.get;
  var gen = function (d) { return window.FgThemeCss.generate(d, SPEC); };
  var clone = function (o) { return JSON.parse(JSON.stringify(o)); };
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };

  function set(o, path, v) {
    var k = path.split('.'), x = o;
    for (var i = 0; i < k.length - 1; i++) { if (x[k[i]] == null || typeof x[k[i]] !== 'object') x[k[i]] = {}; x = x[k[i]]; }
    x[k[k.length - 1]] = v;
  }
  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function same(a, b) { return JSON.stringify(a) === JSON.stringify(b); }

  var published = CFG.published;            // {id, data, css} — a kint lévő téma
  var draftMeta = CFG.draft;                // {data, base_version_id, saved_by, updated_at} | null
  var history = CFG.history || [];
  var doc = clone(draftMeta ? draftMeta.data : published.data);
  var dirty = false;
  var mode = 'dark';
  var openId = null;
  var secEls = {};
  var svgCache = {};

  var CONTROLS = [];
  SPEC.sections.forEach(function (s) { s.controls.forEach(function (c) { CONTROLS.push(c); }); });

  // ---------------------------------------------------------------- üzenetek
  function toast(t) {
    var d = el('div', 'te-toast', esc(t)); document.body.appendChild(d);
    setTimeout(function () { d.remove(); }, 3800);
  }
  function alertBox(kind, html, withClose) {
    var a = $('te-alert'); a.className = 'te-alert ' + kind; a.hidden = false;
    a.innerHTML = html + (withClose !== false ? ' <button type="button" class="te-btn te-btn-ghost" data-close>Bezár</button>' : '');
    var b = a.querySelector('[data-close]'); if (b) b.onclick = function () { a.hidden = true; };
  }

  // ---------------------------------------------------------------- szerver
  function api(method, url, body) {
    var h = { 'X-CSRF-TOKEN': CFG.csrf, 'Accept': 'application/json' };
    var opt = { method: method, headers: h, credentials: 'same-origin' };
    if (body instanceof FormData) opt.body = body;
    else if (body !== undefined) { h['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
    return fetch(url, opt).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'A szerver nem várt választ adott (HTTP ' + r.status + ').' }; })
        .then(function (j) { if (!r.ok && j.ok !== false) j = { ok: false, error: (j && j.message) || ('HTTP ' + r.status) }; return j; });
    }).catch(function () { return { ok: false, error: 'Nem érem el a szervert — a módosításaid a szerkesztőben megmaradtak, próbáld újra.' }; });
  }

  // ---------------------------------------------------------------- kontraszt (= App\Support\ThemeContrast)
  function parseColor(s) {
    s = String(s || '').trim(); var m;
    if ((m = s.match(/^#([0-9a-f]{3})$/i))) { var h = m[1]; return [parseInt(h[0] + h[0], 16), parseInt(h[1] + h[1], 16), parseInt(h[2] + h[2], 16), 1]; }
    if ((m = s.match(/^#([0-9a-f]{6})([0-9a-f]{2})?$/i))) { var x = m[1]; return [parseInt(x.slice(0, 2), 16), parseInt(x.slice(2, 4), 16), parseInt(x.slice(4, 6), 16), m[2] ? parseInt(m[2], 16) / 255 : 1]; }
    if ((m = s.match(/^rgba?\(([\d.]+)[ ,]+([\d.]+)[ ,]+([\d.]+)(?:[ ,/]+([\d.]+))?\)$/i))) return [+m[1], +m[2], +m[3], m[4] != null && m[4] !== '' ? +m[4] : 1];
    return null;
  }
  function composite(t, b) {
    var a = t[3] + b[3] * (1 - t[3]); if (a === 0) return [0, 0, 0, 0];
    var ch = function (i) { return (t[i] * t[3] + b[i] * b[3] * (1 - t[3])) / a; };
    return [ch(0), ch(1), ch(2), a];
  }
  function lum(c) {
    var f = function (v) { var x = v / 255; return x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]);
  }
  function ratio(fg, bg, behind) {
    var f = parseColor(fg), b = parseColor(bg); if (!f || !b) return null;
    if (b[3] < 1 && behind) { var bh = parseColor(behind); if (bh) b = composite(b, bh); }
    if (f[3] < 1) f = composite(f, b);
    var l1 = lum(f), l2 = lum(b);
    return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
  }
  function resolve(d, path, m) { var v = get(d, path + '.' + m); if (v == null) v = get(d, path + '.dark'); return typeof v === 'string' ? v : null; }
  function contrastChecks(d) {
    var out = [];
    CONTROLS.forEach(function (c) {
      (c.contrast || []).forEach(function (r) {
        var modes = ['dark'];
        if (get(d, c.path + '.light') != null || get(d, r.against + '.light') != null) modes.push('light');
        modes.forEach(function (m) {
          var fg = resolve(d, c.path, m), bg = resolve(d, r.against, m);
          var behind = r.behind ? resolve(d, r.behind, m) : resolve(d, 'colors.bg', m);
          var x = fg && bg ? ratio(fg, bg, behind) : null;
          out.push({ path: c.path, label: r.label, mode: m, ratio: x, min: r.min, ok: x !== null && x + 1e-9 >= r.min });
        });
      });
    });
    return out;
  }
  var lastFails = [];
  function runGuards() {
    var all = contrastChecks(doc);
    lastFails = all.filter(function (x) { return !x.ok; });
    document.querySelectorAll('[data-ctr]').forEach(function (s) {
      var k = s.getAttribute('data-ctr').split('|');
      var hit = all.filter(function (x) { return x.path === k[0] && x.mode === k[1]; });
      s.innerHTML = hit.map(function (x) {
        return '<span class="te-ctr ' + (x.ok ? 'ok' : 'bad') + '">' + esc(x.label) + ': ' + (x.ratio === null ? '?' : x.ratio.toFixed(2).replace('.', ',')) + ':1' + (x.ok ? ' ✓' : ' ✗ (kell ' + String(x.min).replace('.', ',') + ')') + '</span>';
      }).join(' · ');
    });
    var g = $('te-guard');
    if (lastFails.length) {
      g.hidden = false;
      g.innerHTML = '<strong>Kontraszt-őr:</strong> ' + lastFails.length + ' szöveg/háttér pár olvashatatlanul halvány — így a publikálás csak írásos felülbírálással mehet ki.<ul>'
        + lastFails.map(function (x) { return '<li>' + esc(x.label) + ' (' + (x.mode === 'dark' ? 'sötét' : 'világos') + '): ' + (x.ratio === null ? 'nem számolható' : x.ratio.toFixed(2).replace('.', ',') + ':1') + ', kell ' + String(x.min).replace('.', ',') + ':1</li>'; }).join('') + '</ul>';
    } else { g.hidden = true; }
  }

  // ---------------------------------------------------------------- állapotsáv
  function updateState() {
    var s = $('te-state');
    if (draftMeta) {
      s.className = 'te-state draft';
      s.textContent = 'VÁZLAT' + (draftMeta.updated_at ? ' · mentve ' + String(draftMeta.updated_at).slice(0, 16) : '') + (draftMeta.saved_by ? ' (' + draftMeta.saved_by + ')' : '') + ' · a látogatók még a #' + published.id + '-et látják';
    } else if (dirty) {
      s.className = 'te-state draft';
      s.textContent = 'MÓDOSÍTOD a kint lévő témát (#' + published.id + ')';
    } else {
      s.className = 'te-state';
      s.textContent = 'A KINT LÉVŐ TÉMA (#' + published.id + ')';
    }
    if (dirty) s.classList.add('dirty');
    $('te-save').disabled = !dirty;
    $('te-discard').hidden = !(draftMeta || dirty);
  }
  function changed(rebuild) {
    dirty = true; updateState(); markChanged(); schedule();
    if (rebuild && openId) openSection(openId, true);
  }

  // ---------------------------------------------------------------- előnézet
  var frame = $('te-frame'), frameReady = false, raf = 0;
  frame.addEventListener('load', function () { frameReady = true; applyPreview(); });
  function schedule() { if (raf) return; raf = requestAnimationFrame(function () { raf = 0; applyPreview(); }); }
  function applyPreview() {
    runGuards();
    if (!frameReady) return;
    var d = frame.contentDocument; if (!d) return;
    var css;
    try { css = gen(doc); $('te-frame').style.opacity = ''; genError(null); }
    catch (e) { genError(e.message); return; }
    var st = d.getElementById('tema-vazlat'); if (st && st.textContent !== css) st.textContent = css;
    d.documentElement.setAttribute('data-theme', mode);
    d.querySelectorAll('img[data-asset]').forEach(function (img) {
      var p = get(doc, img.getAttribute('data-asset')); var u = CFG.urls.arculat + p;
      if (p && img.getAttribute('src') !== u) img.setAttribute('src', u);
    });
    d.querySelectorAll('[data-motif]').forEach(function (box) {
      var p = (get(doc, 'doodles.motifs') || [])[+box.getAttribute('data-motif')];
      if (!p || box.getAttribute('data-path') === p) return;
      box.setAttribute('data-path', p);
      motifSvg(p).then(function (svg) { if (box.getAttribute('data-path') === p) box.innerHTML = svg; });
    });
  }
  function motifSvg(p) {
    if (svgCache[p] != null) return Promise.resolve(svgCache[p]);
    return fetch(CFG.urls.arculat + p, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.text() : ''; })
      .then(function (t) { var m = t.match(/<svg[\s\S]*<\/svg>/i); svgCache[p] = m ? m[0] : ''; return svgCache[p]; })
      .catch(function () { return ''; });
  }
  var genErrEl = null;
  function genError(msg) {
    if (!msg) { if (genErrEl) { genErrEl.remove(); genErrEl = null; } return; }
    if (!genErrEl) { genErrEl = el('div', 'te-alert bad'); $('te-guard').before(genErrEl); }
    genErrEl.innerHTML = '<strong>Az előnézet nem frissíthető:</strong> ' + esc(msg);
  }
  document.querySelectorAll('.te-seg [data-mode]').forEach(function (b) {
    b.onclick = function () {
      mode = b.getAttribute('data-mode');
      document.querySelectorAll('.te-seg [data-mode]').forEach(function (x) { x.classList.toggle('on', x === b); });
      applyPreview();
    };
  });

  // ---------------------------------------------------------------- szekciók
  function buildSections() {
    var box = $('te-sections'); box.innerHTML = ''; secEls = {};
    SPEC.sections.forEach(function (s) {
      var sec = el('div', 'te-sec');
      var h = el('button', 'te-sec-h', '<span class="te-dot"></span>' + esc(s.title));
      h.type = 'button'; h.setAttribute('aria-expanded', 'false');
      var b = el('div', 'te-sec-b'); b.hidden = true;
      h.onclick = function () { if (openId === s.id) closeSection(); else openSection(s.id); };
      sec.appendChild(h); sec.appendChild(b); box.appendChild(sec);
      secEls[s.id] = { h: h, b: b, s: s };
    });
    if (openId) openSection(openId, true);
    markChanged();
  }
  function closeSection() {
    if (!openId) return;
    var x = secEls[openId]; x.b.hidden = true; x.b.innerHTML = ''; x.h.setAttribute('aria-expanded', 'false'); openId = null;
  }
  function openSection(id, keepScroll) {
    if (openId && openId !== id) closeSection();
    var x = secEls[id]; openId = id;
    x.b.innerHTML = '';
    if (x.s.note) x.b.appendChild(el('p', 'te-sec-note', esc(x.s.note)));
    x.s.controls.forEach(function (c) { x.b.appendChild(control(c)); });
    x.b.hidden = false; x.h.setAttribute('aria-expanded', 'true');
    if (!keepScroll) x.h.scrollIntoView({ block: 'nearest' });
    markChanged(); runGuards();
  }
  function isChanged(path) { return !same(get(doc, path), get(published.data, path)); }
  function markChanged() {
    Object.keys(secEls).forEach(function (id) {
      var x = secEls[id];
      x.h.classList.toggle('changed', x.s.controls.some(function (c) { return isChanged(c.path); }));
    });
    document.querySelectorAll('.te-ctl[data-path]').forEach(function (e) { e.classList.toggle('changed', isChanged(e.getAttribute('data-path'))); });
  }

  // ---------------------------------------------------------------- vezérlők
  function wrap(c) {
    var w = el('div', 'te-ctl'); w.setAttribute('data-path', c.path);
    var h = el('div', 'te-ctl-h', '<span class="te-lab">' + esc(c.label) + '</span>');
    w.appendChild(h);
    return { w: w, h: h, hint: function (t) { if (t) w.appendChild(el('p', 'te-hint', esc(t))); } };
  }
  function fmt(v, c) { return (typeof v === 'number' ? String(v).replace('.', ',') : v) + (c.unit ? ' ' + c.unit : ''); }

  function colorField(value, onSet) {
    var box = el('div', 'te-color');
    var pick = document.createElement('input'); pick.type = 'color';
    var txt = document.createElement('input'); txt.type = 'text'; txt.value = value || ''; txt.spellcheck = false;
    function sync() { var m = /^#[0-9a-f]{6}$/i.test(txt.value.trim()); pick.disabled = !m; if (m) pick.value = txt.value.trim(); }
    sync();
    pick.oninput = function () { txt.value = pick.value.toUpperCase(); onSet(txt.value); };
    txt.oninput = function () {
      var ok = parseColor(txt.value) !== null; txt.style.borderColor = ok ? '' : 'var(--te-bad)';
      if (ok) { sync(); onSet(txt.value.trim()); }
    };
    box.appendChild(pick); box.appendChild(txt);
    return box;
  }
  function slider(c, path, min, max, disabled) {
    var row = el('div', 'te-row');
    var r = document.createElement('input'); r.type = 'range'; r.min = min; r.max = max; r.step = c.step; r.disabled = !!disabled;
    var v = get(doc, path); r.value = v;
    var out = el('span', 'te-val', esc(fmt(v, c)));
    r.oninput = function () { var n = Number(r.value); set(doc, path, n); out.textContent = fmt(n, c); changed(); };
    row.appendChild(r); row.appendChild(out);
    return row;
  }
  function modeLabel(m) { return el('span', 'te-mode', m === 'dark' ? 'Sötét' : 'Világos'); }

  function control(c) {
    var x = wrap(c), w = x.w;
    switch (c.kind) {
      case 'slider': {
        var min = c.min, max = c.max, dis = false, note = c.hint;
        if (c.font) {
          var f = SPEC.fonts[get(doc, 'type.head')] || {};
          if (c.font === 'wght' && f.wght) { min = f.wght[0]; max = f.wght[1]; if (min === max) { dis = true; note = 'A(z) „' + get(doc, 'type.head') + '” csak ' + min + '-as vastagságban létezik — más vastagság Androidon körvonalas lenne.'; } }
          if (c.font === 'wdth' && !f.wdth) { dis = true; note = 'A(z) „' + get(doc, 'type.head') + '” betűnek nincs szélesség-tengelye — ez csak az Archivónál hat.'; }
        }
        w.appendChild(slider(c, c.path, min, max, dis)); x.hint(note); break;
      }
      case 'sliderPair':
        ['dark', 'light'].forEach(function (m) { var row = el('div', 'te-row'); row.appendChild(modeLabel(m)); row.appendChild(slider(c, c.path + '.' + m, c.min, c.max)); row.lastChild.style.flex = '1'; w.appendChild(row); });
        x.hint(c.hint); break;
      case 'toggle': {
        var lab = el('label', 'te-row'); var cb = document.createElement('input'); cb.type = 'checkbox'; cb.checked = get(doc, c.path) === true;
        cb.onchange = function () { set(doc, c.path, cb.checked); changed(); };
        lab.appendChild(cb); lab.appendChild(document.createTextNode(cb.checked ? ' bekapcsolva' : ' kikapcsolva'));
        cb.addEventListener('change', function () { lab.lastChild.textContent = cb.checked ? ' bekapcsolva' : ' kikapcsolva'; });
        w.appendChild(lab); x.hint(c.hint); break;
      }
      case 'radio': {
        var seg = el('div', 'te-seg');
        c.options.forEach(function (o) {
          var b = el('button', same(get(doc, c.path), o.value) ? 'on' : '', esc(o.label)); b.type = 'button';
          b.onclick = function () { set(doc, c.path, o.value); seg.querySelectorAll('button').forEach(function (y) { y.classList.toggle('on', y === b); }); changed(); };
          seg.appendChild(b);
        });
        w.appendChild(seg); x.hint(c.hint); break;
      }
      case 'select': {
        var sel = document.createElement('select');
        c.options.forEach(function (o) { var op = document.createElement('option'); op.value = o.value; op.textContent = o.label; sel.appendChild(op); });
        sel.value = get(doc, c.path);
        sel.onchange = function () {
          set(doc, c.path, sel.value);
          if (c.path === 'type.head') {
            var f = SPEC.fonts[sel.value], wg = get(doc, 'type.headWght');
            var nw = Math.min(f.wght[1], Math.max(f.wght[0], wg));
            if (nw !== wg) { set(doc, 'type.headWght', nw); toast('A címsor-vastagság ' + nw + '-ra igazítva — a(z) ' + sel.value + ' csak ' + f.wght[0] + '–' + f.wght[1] + ' vastagságban létezik.'); }
            changed(true); return;
          }
          changed();
        };
        w.appendChild(sel); x.hint(c.hint); break;
      }
      case 'text': {
        var t = document.createElement('input'); t.type = 'text'; t.value = get(doc, c.path) || '';
        var re = new RegExp(c.pattern);
        t.oninput = function () { set(doc, c.path, t.value.trim()); t.style.borderColor = re.test(t.value.trim()) ? '' : 'var(--te-bad)'; changed(); };
        w.appendChild(t); x.hint(c.hint); break;
      }
      case 'textBi':
        ['hu', 'ro'].forEach(function (l) {
          var row = el('div', 'te-row'); row.appendChild(el('span', 'te-mode', l.toUpperCase()));
          var t = document.createElement('input'); t.type = 'text'; t.maxLength = c.max; t.value = get(doc, c.path + '.' + l) || ''; t.style.flex = '1';
          t.oninput = function () { set(doc, c.path + '.' + l, t.value); changed(); };
          row.appendChild(t); w.appendChild(row);
        });
        x.hint(c.hint); break;
      case 'color':
        w.appendChild(colorField(get(doc, c.path), function (v) { set(doc, c.path, v); changed(); })); x.hint(c.hint); break;
      case 'colorPair':
        (c.light ? ['dark', 'light'] : ['dark']).forEach(function (m) {
          var row = el('div', 'te-row');
          if (c.light) row.appendChild(modeLabel(m));
          row.appendChild(colorField(get(doc, c.path + '.' + m), function (v) { set(doc, c.path + '.' + m, v); changed(); }));
          w.appendChild(row);
          if (c.contrast) { var s = el('div', 'te-hint'); s.setAttribute('data-ctr', c.path + '|' + m); w.appendChild(s); }
        });
        x.hint(c.hint); break;
      case 'accentTrio':
        // Mód-felirat, mellette (ha nem fér: alatta) a három szín egy sorban, egyforma kis dobozokban.
        ['dark', 'light'].forEach(function (m) {
          var row = el('div', 'te-trio-row'); row.appendChild(modeLabel(m));
          var trio = el('div', 'te-trio');
          ['a', 'b', 'c'].forEach(function (betu, i) {
            var cell = el('div', 'te-trio-cell'); cell.appendChild(el('span', 'te-trio-lab', betu));
            cell.appendChild(colorField((get(doc, c.path + '.' + m) || [])[i], function (v) {
              var a = (get(doc, c.path + '.' + m) || []).slice(); a[i] = v; set(doc, c.path + '.' + m, a); changed();
            }));
            trio.appendChild(cell);
          });
          row.appendChild(trio); w.appendChild(row);
        });
        var pr = el('div', 'te-presets');
        SPEC.presets.forEach(function (p) {
          var b = el('button', 'te-btn te-btn-ghost', esc(p.name)); b.type = 'button';
          b.onclick = function () { set(doc, c.path + '.dark', p.dark.slice()); changed(true); };
          pr.appendChild(b);
        });
        w.appendChild(el('p', 'te-hint', 'Gyors választás (a sötét hármasra):')); w.appendChild(pr);
        break;
      case 'media': w.appendChild(mediaRow(c.path, false)); x.hint(c.hint); break;
      case 'motifs': {
        var grid = el('div', 'te-motifs');
        for (var i = 0; i < c.slots; i++) { var cell = el('div', 'te-motif'); cell.appendChild(el('strong', '', (i + 1) + '. hely')); cell.appendChild(mediaRow(c.path + '.' + i, true)); grid.appendChild(cell); }
        w.appendChild(grid); x.hint(c.hint); break;
      }
    }
    return w;
  }

  function mediaRow(path, svg) {
    var row = el('div', 'te-media');
    var p = get(doc, path);
    var thumb;
    if (svg) { thumb = el('div', 'te-svg'); motifSvg(p).then(function (s) { thumb.innerHTML = s; }); }
    else { thumb = document.createElement('img'); thumb.alt = ''; thumb.src = CFG.urls.arculat + p; }
    row.appendChild(thumb);
    row.appendChild(el('span', 'te-file', esc(String(p).replace(/^fajlok\//, ''))));
    var inp = document.createElement('input'); inp.type = 'file'; inp.hidden = true;
    var ext = svg ? ['svg'] : (SPEC.media[path] || {}).ext || [];
    inp.accept = ext.map(function (e) { return '.' + e; }).join(',');
    var up = el('button', 'te-btn', 'Csere…'); up.type = 'button'; up.onclick = function () { inp.click(); };
    inp.onchange = function () { if (inp.files[0]) upload(path, inp.files[0]); };
    var fac = el('button', 'te-btn te-btn-ghost', 'Gyári'); fac.type = 'button';
    fac.onclick = function () {
      api('GET', CFG.urls.gyari).then(function (r) {
        if (!r.ok) return alertBox('bad', '<strong>A gyári fájl nem tölthető be:</strong> ' + esc(r.error));
        set(doc, path, get(r.data, path)); changed(true); toast('Gyári fájl visszaállítva (publikáláskor kerül ki).');
      });
    };
    row.appendChild(up); row.appendChild(fac); row.appendChild(inp);
    return row;
  }
  function upload(path, file) {
    var fd = new FormData(); fd.append('path', path); fd.append('file', file);
    toast('Feltöltés…');
    api('POST', CFG.urls.feltoltes, fd).then(function (r) {
      if (!r.ok) return alertBox('bad', '<strong>A feltöltés nem sikerült:</strong> ' + esc(r.error));
      if (r.svg != null) svgCache[r.path] = r.svg;
      set(doc, path, r.path); changed(true); toast('Feltöltve — a publikálásig csak az előnézetben látszik.');
    });
  }

  // ---------------------------------------------------------------- mentés / eldobás
  function saveDraft(quiet) {
    var base = draftMeta && draftMeta.base_version_id ? draftMeta.base_version_id : published.id;
    $('te-save').disabled = true;
    return api('POST', CFG.urls.vazlat, { data: doc, base_version_id: base }).then(function (r) {
      if (!r.ok) { $('te-save').disabled = !dirty; if (!quiet) alertBox('bad', '<strong>A vázlat NEM mentődött:</strong> ' + esc(r.error)); return r; }
      draftMeta = { data: clone(doc), base_version_id: base, saved_by: r.draft && r.draft.saved_by, updated_at: r.draft && r.draft.updated_at };
      dirty = false; updateState(); if (!quiet) toast('Vázlat mentve. A látogatók továbbra is a kint lévő témát látják.');
      return r;
    });
  }
  $('te-save').onclick = function () { saveDraft(false); };
  $('te-discard').onclick = function () {
    if (!confirm('Eldobod a vázlatot? A szerkesztő visszaáll a kint lévő témára (#' + published.id + ').')) return;
    api('DELETE', CFG.urls.vazlat).then(function (r) {
      if (!r.ok) return alertBox('bad', '<strong>Nem sikerült eldobni:</strong> ' + esc(r.error));
      draftMeta = null; dirty = false; doc = clone(published.data); $('te-alert').hidden = true;
      buildSections(); updateState(); schedule(); toast('Vázlat eldobva.');
    });
  };
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  // ---------------------------------------------------------------- különbség
  function flatten(o, pre, out) {
    out = out || {};
    Object.keys(o || {}).forEach(function (k) {
      var v = o[k], p = pre ? pre + '.' + k : k;
      if (v && typeof v === 'object' && !Array.isArray(v)) flatten(v, p, out); else out[p] = v;
    });
    return out;
  }
  function labelFor(p) {
    var best = null;
    CONTROLS.forEach(function (c) { if ((p === c.path || p.indexOf(c.path + '.') === 0) && (!best || c.path.length > best.path.length)) best = c; });
    if (!best) return p === 'name' ? 'A téma neve' : p;
    var rest = p.slice(best.path.length + 1);
    var nice = { dark: 'sötét', light: 'világos', hu: 'HU', ro: 'RO' };
    return best.label + (rest ? ' (' + rest.split('.').map(function (r) { return nice[r] || (/^\d$/.test(r) ? (+r + 1) + '.' : r); }).join(', ') + ')' : '');
  }
  function showVal(v) {
    var s = Array.isArray(v) ? v.join(', ') : String(v);
    var sw = typeof v === 'string' && parseColor(v) ? '<i class="te-sw" style="background:' + esc(v) + '"></i>' : '';
    return sw + '<code>' + esc(s.replace(/^fajlok\//, '')) + '</code>';
  }
  function diffRows(a, b) {
    var fa = flatten(a), fb = flatten(b), keys = {};
    Object.keys(fa).concat(Object.keys(fb)).forEach(function (k) { keys[k] = 1; });
    return Object.keys(keys).filter(function (k) { return !same(fa[k], fb[k]); }).map(function (k) { return { label: labelFor(k), from: fa[k], to: fb[k] }; });
  }

  // ---------------------------------------------------------------- párbeszédablak
  var dlg = $('te-dlg');
  function dialog(title, bodyHtml, buttons) {
    $('te-dlg-title').textContent = title; $('te-dlg-body').innerHTML = bodyHtml;
    var foot = $('te-dlg-foot'); foot.innerHTML = '';
    buttons.forEach(function (b) { var x = el('button', 'te-btn ' + (b.cls || ''), esc(b.label)); x.type = b.close ? 'submit' : 'button'; if (b.id) x.id = b.id; if (b.onclick) x.onclick = b.onclick; foot.appendChild(x); });
    if (!dlg.open) dlg.showModal();
  }

  $('te-publish').onclick = function () {
    var rows = diffRows(published.data, doc);
    var body = '';
    if (draftMeta && draftMeta.base_version_id && draftMeta.base_version_id !== published.id) {
      body += '<div class="te-alert warn">Ez a vázlat a #' + draftMeta.base_version_id + ' verzióra épül, de közben a #' + published.id + ' került ki. Nézd át a különbséget: amit más azóta publikált, azt most visszaírnád.</div>';
    }
    body += '<p><strong>Mi fog változni?</strong> (a kint lévő #' + published.id + ' → a szerkesztőben lévő)</p>';
    body += rows.length
      ? '<div class="te-diff">' + rows.map(function (r) { return '<div><span>' + esc(r.label) + '</span><span>' + showVal(r.from) + ' → ' + showVal(r.to) + '</span></div>'; }).join('') + '</div>'
      : '<div class="te-alert info">Nincs változás a kint lévő témához képest.</div>';
    body += '<label>Megjegyzés (mi és miért) <input type="text" id="te-comment" maxlength="200" placeholder="pl. őszi színek a beküldő űrlapra"></label>';
    if (lastFails.length) {
      body += '<div class="te-alert bad"><strong>A kontraszt-őr ' + lastFails.length + ' hibát talált</strong> — ' + lastFails.map(function (x) { return esc(x.label) + ' (' + (x.mode === 'dark' ? 'sötét' : 'világos') + ')'; }).join(', ') + '.<br>'
        + 'Publikálni csak írásos indoklással lehet (legalább 10 karakter, a verzión megmarad):'
        + '<textarea id="te-override" rows="2" placeholder="Miért publikálható így?"></textarea></div>';
    }
    body += '<div id="te-pub-err"></div>';
    dialog('Publikálás', body, [
      { label: 'Mégse', cls: 'te-btn-ghost', close: true },
      { label: 'Publikálás most', cls: 'te-btn-primary', id: 'te-pub-go', onclick: doPublish },
    ]);
    $('te-pub-go').disabled = rows.length === 0;
  };
  function doPublish() {
    var ov = $('te-override') ? $('te-override').value.trim() : '';
    if (lastFails.length && ov.length < 10) { $('te-pub-err').innerHTML = '<div class="te-alert bad">Írd le, miért publikálható így (legalább 10 karakter).</div>'; return; }
    var go = $('te-pub-go'); go.disabled = true; go.textContent = 'Publikálás…';
    api('POST', CFG.urls.publikalas, { data: doc, comment: $('te-comment').value, override: ov || null }).then(function (r) {
      if (!r.ok) {
        go.disabled = false; go.textContent = 'Publikálás most';
        $('te-pub-err').innerHTML = '<div class="te-alert bad"><strong>Nem ment ki:</strong> ' + esc(r.error) + '</div>';
        return;
      }
      published = { id: r.version.id, data: clone(doc), css: gen(doc) };
      history.forEach(function (h) { h.current = false; }); history.unshift(r.version); history = history.slice(0, 10);
      draftMeta = null; dirty = false; dlg.close(); updateState(); markChanged();
      alertBox('ok', '<strong>Kint van</strong> (#' + r.version.id + '). Az új téma-CSS: <code>' + esc(r.css_url.split('/').pop()) + '</code> — a böngészők a fájlnév változása miatt magától az újat töltik.');
    });
  }

  // ---------------------------------------------------------------- előzmény
  $('te-history').onclick = function () {
    $('te-history').closest('details').open = false;
    var body = '<p>Az utolsó ' + history.length + ' publikálás. A „Betöltés” a verziót a szerkesztőbe teszi — kikerülni csak a Publikálással kerül (előtte látod a különbséget).</p><div class="te-diff">'
      + history.map(function (h) {
        return '<div class="te-ver"><strong>#' + h.id + '</strong> <span>' + esc(h.at) + '</span> ' + (h.current ? '<span class="te-tag">MOST KINT</span>' : '')
          + ' <span class="te-sp"></span><button type="button" class="te-btn" data-ver="' + h.id + '">Betöltés</button>'
          + '<span class="te-muted">' + esc(h.by || '—') + (h.comment ? ' · ' + esc(h.comment) : '') + (h.override ? ' · ⚠ kontraszt-felülbírálás: ' + esc(h.override) : '') + '</span></div>';
      }).join('') + '</div>';
    dialog('Előzmény', body, [{ label: 'Bezár', cls: 'te-btn-ghost', close: true }]);
    $('te-dlg-body').querySelectorAll('[data-ver]').forEach(function (b) {
      b.onclick = function () {
        b.disabled = true;
        api('GET', CFG.urls.verzio.replace('__ID__', b.getAttribute('data-ver'))).then(function (r) {
          if (!r.ok) { b.disabled = false; return alertBox('bad', '<strong>A verzió nem tölthető be:</strong> ' + esc(r.error)); }
          doc = clone(r.data); dlg.close(); buildSections(); changed();
          alertBox('info', 'A <strong>#' + r.version.id + '</strong> verzió (' + esc(r.version.at) + ') a szerkesztőben van. Ha ezt akarod kint látni: <strong>Publikálás…</strong> (előtte megmutatja a különbséget).');
        });
      };
    });
  };

  // ---------------------------------------------------------------- export / import / gyári
  $('te-export').onclick = function () {
    $('te-export').closest('details').open = false;
    var blob = new Blob([JSON.stringify(doc, null, 2)], { type: 'application/json' });
    var a = document.createElement('a'); a.href = URL.createObjectURL(blob);
    a.download = 'forgatag-tema-' + new Date().toISOString().slice(0, 10) + '.json'; a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
  };
  $('te-import-file').onchange = function (e) {
    $('te-export').closest('details').open = false;
    var f = e.target.files[0]; e.target.value = ''; if (!f) return;
    f.text().then(function (t) {
      var d; try { d = JSON.parse(t); } catch (x) { return alertBox('bad', '<strong>Az import nem sikerült:</strong> a fájl nem érvényes JSON.'); }
      Object.keys(d).forEach(function (k) { if (k.charAt(0) === '_') delete d[k]; });
      var before = doc; doc = d;
      // Az importot rögtön a szerver is ellenőrzi (vázlatként menti) — a hiba most derüljön ki, ne publikáláskor.
      var wasDirty = dirty; dirty = true;
      saveDraft(true).then(function (r) {
        if (!r.ok) { doc = before; dirty = wasDirty; updateState(); alertBox('bad', '<strong>Az importált téma nem fogadható el</strong> — a szerkesztő a régin maradt. A szerver ezt találta: ' + esc(r.error)); return; }
        buildSections(); schedule(); toast('Importálva és vázlatként mentve.');
      });
    });
  };
  $('te-factory').onclick = function () {
    $('te-factory').closest('details').open = false;
    if (!confirm('Visszaállítod a gyári (a 2026-ból átvett) témát a szerkesztőben? Kikerülni csak publikálással kerül.')) return;
    api('GET', CFG.urls.gyari).then(function (r) {
      if (!r.ok) return alertBox('bad', '<strong>A gyári téma nem tölthető be:</strong> ' + esc(r.error));
      doc = clone(r.data); buildSections(); changed(); toast('Gyári téma a szerkesztőben — publikálás előtt látod a különbséget.');
    });
  };

  // ---------------------------------------------------------------- indulás
  (function selfCheck() {
    var mine = null;
    try { mine = gen(published.data); } catch (e) { mine = 'HIBA: ' + e.message; }
    if (mine !== published.css) {
      alertBox('bad', '<strong>Figyelem:</strong> a böngészős előnézet-generátor NEM ugyanazt a CSS-t adja, mint a szerver (public/js/tema-css.js ≠ App\\Support\\ThemeCss). Az előnézet eltérhet attól, ami kikerül — szólj a fejlesztőnek. A publikálás ettől még a szerver CSS-ét teszi ki.', false);
    } else if (draftMeta && draftMeta.base_version_id && draftMeta.base_version_id !== published.id) {
      alertBox('warn', '<strong>Ez a vázlat egy régebbi témára épül</strong> (#' + draftMeta.base_version_id + '), közben a #' + published.id + ' került ki. Publikálás előtt nézd át a különbséget, vagy dobd el a vázlatot.');
    }
  })();
  buildSections(); updateState(); runGuards();
})();
