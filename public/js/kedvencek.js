/*
 * KEDVENCEK — a szívek és a lista kezelője, EGY forrás minden publikus lapon (PROMPT-21; 2026: Base.astro + programkereso
 * script.js + kedvenceim/script.js tárolója). `window.fgKedvenc` a lapoknak (a „Kedvenc programjaim” lap ebből dolgozik).
 *
 * 🔴 A LAP SEMLEGES SZÍVEKKEL ÉRKEZIK (a szerver soha nem írja bele, mi van megjelölve): a lap gyorsítótárazható, és egy
 *    gyorsítótárazott lap különben valaki MÁS szíveit mutatná. A jelölést itt tesszük rá — betöltéskor és MINDEN htmx-csere
 *    után (a szűrés a találati listát cseréli: a szívnek túl kell élnie). A kattintás a dokumentumon ül, ezért a cserélt
 *    elemeken is működik. Jelölő: bármely `[data-save="<program-id>"]` (kártya, felugró ablak „Mentés”, program lapja).
 *
 * TÁROLÁS: a böngésző tárolója (fg-kedvencek: azonosítók, fg-kedvenc-lista: a lista kulcsa) ÉS a szerver (a kulcs a
 *    megosztható link része — belépés és személyes adat nincs). A szerver felé MŰVELETEK mennek („+12”, „−12”), nem a teljes
 *    lista: két eszköz ugyanazon a listán így nem írja felül egymást. Ami nem jutott fel (nincs net), a sorban vár
 *    (fg-kedvenc-ops), és a következő alkalommal felmegy — a változás SOHA nem vész el csendben, és a hibát kiírjuk.
 * Ha a böngésző nem engedi a tárolót (privát ablak), minden a memóriában is megvan, és ezt kimondjuk.
 */
(function () {
  'use strict';
  var CFG = {};
  try { CFG = JSON.parse(document.getElementById('fgKedvencCfg').textContent) || {}; } catch (_) { CFG = {}; }
  var T = CFG.t || {};
  var API = CFG.api || '/kedvencek-api/lista';
  var K_IDS = 'fg-kedvencek', K_KULCS = 'fg-kedvenc-lista', K_OPS = 'fg-kedvenc-ops', K_IDO = 'fg-kedvenc-ido';
  var FRISSITES_MS = 2 * 60 * 1000;   // más lapon ennyi időnként nézünk rá a szerverre (másik eszköz változásai)

  /* ---- tároló (memória-tartalékkal) ---- */
  var MEM = {};
  var TAROLO_OK = true;
  try { localStorage.setItem('fg-proba', '1'); localStorage.removeItem('fg-proba'); } catch (_) { TAROLO_OK = false; }
  function olvas(k, alap) {
    if (Object.prototype.hasOwnProperty.call(MEM, k)) return MEM[k];
    var v = alap;
    try { var s = localStorage.getItem(k); if (s != null) v = JSON.parse(s); } catch (_) { v = alap; }
    MEM[k] = v;
    return v;
  }
  function ir(k, v) {
    MEM[k] = v;
    try { if (v == null) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v)); } catch (_) { TAROLO_OK = false; }
  }
  function tiszta(a) {
    var ki = [];
    (Array.isArray(a) ? a : []).forEach(function (x) {
      var n = typeof x === 'number' ? x : parseInt(x, 10);
      if (n > 0 && String(n) === String(x) && ki.indexOf(n) < 0) ki.push(n);
    });
    return ki.slice(0, 300);
  }
  function lista() { return tiszta(olvas(K_IDS, [])); }
  function kulcs() { var k = olvas(K_KULCS, null); return (typeof k === 'string' && /^[0-9a-f]{32}$/.test(k)) ? k : null; }
  function muveletek() { var o = olvas(K_OPS, []); return Array.isArray(o) ? o : []; }

  /* ---- a lap jelölése ---- */
  function jelol() {
    var ids = lista();
    document.querySelectorAll('[data-save]').forEach(function (b) {
      var on = ids.indexOf(parseInt(b.getAttribute('data-save'), 10)) >= 0;
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      b.classList.toggle('is-fav', on);           // a program lapjának .fav-btn-je ezzel vált ♡ → ♥ (2026: Base.astro)
    });
    var dot = document.getElementById('favDot');
    if (dot) dot.hidden = !ids.length;
  }
  function jelez(reszlet) {
    jelol();
    document.dispatchEvent(new CustomEvent('fg:kedvencek', { detail: reszlet || {} }));
  }

  /* ---- értesítés (a lap alján; a link felkínálása és a hibák) ---- */
  var toastEl = null, toastTimer = null;
  function toastZar() { clearTimeout(toastTimer); if (toastEl && toastEl.parentNode) toastEl.parentNode.removeChild(toastEl); toastEl = null; }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function toast(szoveg, opc) {
    opc = opc || {};
    toastZar();
    var el = document.createElement('div');
    el.className = 'fg-kvtoast' + (opc.hiba ? ' is-hiba' : '');
    el.setAttribute('role', opc.hiba ? 'alert' : 'status');
    var html = '<button type="button" class="fg-kvtoast-x" aria-label="' + esc(T.bezaras) + '">✕</button>'
      + '<p class="fg-kvtoast-txt">' + esc(szoveg) + '</p>';
    if (opc.link) {
      html += '<div class="fg-kvtoast-link"><input type="text" readonly value="' + esc(opc.link) + '" aria-label="' + esc(T.kv_lista_megnyitasa) + '">'
        + '<button type="button" class="fg-kvtoast-btn" data-kv-masol>' + esc(T.kv_link_masolasa) + '</button></div>'
        + '<div class="fg-kvtoast-sor"><a class="fg-kvtoast-btn is-ghost" href="' + esc(CFG.lap) + '">' + esc(T.kv_lista_megnyitasa) + ' →</a></div>';
    }
    el.innerHTML = html;
    document.body.appendChild(el);
    toastEl = el;
    el.querySelector('.fg-kvtoast-x').addEventListener('click', toastZar);
    var m = el.querySelector('[data-kv-masol]');
    if (m) m.addEventListener('click', function () { masol(opc.link, m, el.querySelector('input')); });
    if (opc.ido) toastTimer = setTimeout(toastZar, opc.ido);
  }

  /** Link a vágólapra; ha nem megy, kijelöljük, és megmondjuk, hogy kézzel kell (néma hiba nincs). */
  function masol(szoveg, gomb, input) {
    var kesz = function () { var r = gomb.textContent; gomb.textContent = '✓ ' + (T.kv_masolva || ''); setTimeout(function () { gomb.textContent = r; }, 1800); };
    var kezzel = function () {
      if (input) { input.focus(); input.select(); }
      try { if (document.execCommand && document.execCommand('copy')) { kesz(); return; } } catch (_) { }
      toast(T.kv_masolas_kezzel || '', { hiba: true, link: szoveg });
    };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(szoveg).then(kesz, kezzel);
    else kezzel();
  }

  function link() { var k = kulcs(); return k ? location.origin + (CFG.lap || '/kedvencek') + '?lista=' + k : ''; }
  function kedvencLapon() { return !!document.querySelector('[data-kedvencek-lap]'); }

  /* ---- szerver ---- */
  function hiba(kod, allapot) { var e = new Error(kod || 'halozat'); e.allapot = allapot || 0; return e; }
  function keres(modszer, ut, test) {
    var o = { method: modszer, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' };
    if (test) { o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(test); }
    return fetch(API + ut, o).catch(function () { throw hiba('halozat'); }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok || !j.ok) throw hiba(j && j.hiba, r.status);
        return j;
      });
    });
  }
  function hibaUzenet(e) {
    if (e && e.allapot === 429) toast(T.kv_sok || '', { hiba: true });
    else toast(T.kv_szinkron_hiba || '', { hiba: true });
    if (window.console) console.warn('[kedvencek]', e && (e.message + ' ' + (e.allapot || '')));
  }

  var fut = false, ujra = false;
  /** A várakozó műveletek felküldése; ha még nincs lista, létrehozza (és feltűnően felkínálja a linkjét). */
  function felkuld() {
    if (fut) { ujra = true; return Promise.resolve(); }
    var k = kulcs();
    var ops = muveletek();
    if (!k) {
      var ids = lista();
      if (!ids.length) { ir(K_OPS, []); return Promise.resolve(); }
      fut = true;
      return keres('POST', '', { ids: ids }).then(function (j) {
        ir(K_KULCS, j.kulcs);
        var kesobbi = muveletek().slice(ops.length);   // a kérés közben jött kattintások
        ir(K_OPS, kesobbi);
        ir(K_IDO, Date.now());
        document.dispatchEvent(new CustomEvent('fg:kedvenc-lista', { detail: { uj: true } }));
        if (!kedvencLapon()) toast(T.kv_uj_lista || '', { link: link() });
        if (kesobbi.length) ujra = true;
      }, hibaUzenet).then(vege);
    }
    if (!ops.length) return Promise.resolve();
    fut = true;
    return keres('POST', '/' + k, { ops: ops }).then(function (j) {
      var kesobbi = muveletek().slice(ops.length);
      ir(K_OPS, kesobbi);
      ir(K_IDS, alkalmaz(tiszta(j.ids), kesobbi));
      ir(K_IDO, Date.now());
      jelez({ forras: 'szerver' });
      if (kesobbi.length) ujra = true;
    }, function (e) {
      if (e.allapot === 404) {
        /* A listát máshol törölték: a helyi lista megmarad, és új listaként (új linkkel) felmegy — semmi nem vész el. */
        ir(K_KULCS, null); ir(K_OPS, []); ujra = true;
        return;
      }
      hibaUzenet(e);
    }).then(vege);
  }
  function vege() { fut = false; if (ujra) { ujra = false; return felkuld(); } }
  function alkalmaz(ids, ops) {
    ops.forEach(function (m) {
      ids = ids.filter(function (x) { return x !== m[0]; });
      if (m[1]) ids.push(m[0]);
    });
    return ids;
  }

  var kuldTimer = null;
  function utemez() { clearTimeout(kuldTimer); kuldTimer = setTimeout(felkuld, 500); }

  /** A szerver állapota (másik eszközön mentett/levett programok). Várakozó saját művelet esetén előbb az megy fel. */
  function frissit(azonnal) {
    var k = kulcs();
    if (!k) return Promise.resolve();
    if (muveletek().length) return felkuld();
    if (!azonnal && Date.now() - (olvas(K_IDO, 0) || 0) < FRISSITES_MS) return Promise.resolve();
    return keres('GET', '/' + k).then(function (j) {
      ir(K_IDO, Date.now());
      if (muveletek().length) return;        // közben kattintottak: a saját változás a fontosabb, az megy fel
      var regi = lista().join(','), uj = tiszta(j.ids);
      ir(K_IDS, uj);
      if (regi !== uj.join(',')) jelez({ forras: 'szerver' });
    }, function (e) {
      if (e.allapot === 404) { ir(K_KULCS, null); if (lista().length) return felkuld(); return; }
      throw e;
    });
  }

  /** Megosztott link megnyitása: a link listája lesz ennek az eszköznek a listája; ami itt volt, beleolvad (nem vész el). */
  function megnyit(ujKulcs) {
    if (!/^[0-9a-f]{32}$/.test(ujKulcs || '')) return Promise.reject(hiba('nincs', 404));
    if (ujKulcs === kulcs()) return frissit(true);
    return keres('GET', '/' + ujKulcs).then(function (j) {
      var szerver = tiszta(j.ids);
      var hianyzo = lista().filter(function (x) { return szerver.indexOf(x) < 0; });
      ir(K_KULCS, ujKulcs);
      ir(K_IDS, szerver.concat(hianyzo));
      ir(K_OPS, hianyzo.map(function (x) { return [x, true]; }));
      ir(K_IDO, Date.now());
      jelez({ forras: 'link' });
      document.dispatchEvent(new CustomEvent('fg:kedvenc-lista', { detail: {} }));
      if (hianyzo.length) return felkuld();
    });
  }

  /** Egy program be/ki. @return {boolean} az új állapot */
  function valt(id, on) {
    id = parseInt(id, 10);
    if (!(id > 0)) return false;
    var ids = lista();
    var volt = ids.indexOf(id) >= 0;
    if (on === undefined) on = !volt;
    if (on === volt) return on;
    ids = ids.filter(function (x) { return x !== id; });
    if (on) ids.push(id);
    ir(K_IDS, ids);
    var ops = muveletek().slice();
    ops.push([id, on]);
    ir(K_OPS, ops);
    jelez({ id: id, on: on, forras: 'kattintas' });
    if (!TAROLO_OK) toast(T.kv_nincs_tarolo || '', { hiba: true });
    utemez();
    return on;
  }

  /** A lista törlése a szerverről ÉS erről az eszközről. */
  function torol() {
    var k = kulcs();
    var helyi = function () { ir(K_IDS, []); ir(K_KULCS, null); ir(K_OPS, []); jelez({ forras: 'torles' }); };
    if (!k) { helyi(); return Promise.resolve(); }
    return keres('DELETE', '/' + k).then(helyi);
  }
  /** Csak erről az eszközről tűnik el (a szerveren a link mögött megmarad). */
  function elrejt() { ir(K_IDS, []); ir(K_KULCS, null); ir(K_OPS, []); jelez({ forras: 'elrejtes' }); }

  /* ---- események ---- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-save]');
    if (!b) return;
    e.preventDefault();
    e.stopPropagation();
    valt(b.getAttribute('data-save'));
  });
  /* A htmx-szel cserélt részeken (szűrés, felugró ablak) a szív újra megkapja a jelölést. */
  document.addEventListener('htmx:afterSettle', jelol);
  document.addEventListener('htmx:load', jelol);
  /* Másik fülön történt változás (a tároló a fülek közös tárolója). */
  window.addEventListener('storage', function (e) {
    if (!e || [K_IDS, K_KULCS, K_OPS].indexOf(e.key) < 0) return;
    MEM = {};
    jelez({ forras: 'ful' });
  });
  window.addEventListener('online', function () { if (muveletek().length) felkuld(); });

  window.fgKedvenc = {
    lista: lista, kulcs: kulcs, link: link, valt: valt, jelol: jelol, frissit: frissit, megnyit: megnyit,
    torol: torol, elrejt: elrejt, taroloOk: function () { return TAROLO_OK; }, masol: masol
  };

  function indul() {
    jelol();
    if (kedvencLapon()) return;          // a lap maga dönt (megosztott link, azonnali frissítés)
    frissit(false).catch(function (e) { if (window.console) console.warn('[kedvencek]', e && e.message); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', indul);
  else indul();
})();
