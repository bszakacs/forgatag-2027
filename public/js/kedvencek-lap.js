/*
 * „KEDVENC PROGRAMJAIM” (/kedvencek) — a 2026-os kedvenceim/script.js átirata (PROMPT-21). A lista a böngészőé
 * (window.fgKedvenc, public/js/kedvencek.js); a kártyákat a szerver rajzolja a látogató listájából (/kedvencek/kartyak?p=…),
 * a programkereső sablonjával. A lap forrása semleges — ezért gyorsítótárazható.
 *
 * Két állapot (2026): ÜRES (kvEmpty: buzdítás + ajánlott programok) és FELTÖLTÖTT (összegző sor, napok, kezelősor).
 * A LISTA LINKJE (a 2026-os e-mail-cím helyén): amint van lista, a lista FÖLÖTT, feltűnően — ez az egyetlen út vissza hozzá.
 * Megosztott link (`?lista=<kulcs>`): a link listája lesz ennek az eszköznek a listája, ami itt volt, beleolvad.
 * Levétel: 6 mp-es visszavonás (2026). Elrejtés / törlés: két lépésben, a gombon belül (2026).
 * A kártyáról a programkereső felugró ablaka nyílik helyben (a címsor marad /kedvencek — 2026: url: null).
 */
(function () {
  'use strict';
  var K = window.fgKedvenc;
  var lap = document.querySelector('[data-kedvencek-lap]');
  if (!K || !lap) return;
  var reduceM = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var list = document.getElementById('kvList');
  var empty = document.getElementById('kvEmpty');
  var tools = document.getElementById('kvTools');
  var note = document.getElementById('kvToolNote');
  var sum = document.getElementById('kvSum');
  var linkBox = document.getElementById('kvLink');
  var linkIn = document.getElementById('kvLinkInput');
  var linkCopy = document.getElementById('kvLinkCopy');
  var linkShare = document.getElementById('kvLinkShare');
  var ics = document.getElementById('kvIcs');
  var undo = document.getElementById('kvUndo');
  var undoNm = document.getElementById('kvUndoName');
  var undoBt = document.getElementById('kvUndoBtn');
  var KARTYAK = lap.getAttribute('data-kartyak');
  var NAPTAR = lap.getAttribute('data-naptar-url');

  function t(k) { return note ? (note.getAttribute('data-t-' + k) || '') : ''; }
  function jegyzet(szoveg, hiba) {
    if (!note) return;
    note.textContent = szoveg || '';
    note.classList.toggle('is-err', !!hiba);
  }

  /* ---- a link-panel ---- */
  function linkFrissit() {
    var van = K.lista().length > 0;
    var l = K.link();
    linkBox.hidden = !van;
    linkIn.value = l;
    linkCopy.disabled = !l;
    if (linkShare) linkShare.hidden = !(l && navigator.share);
  }
  linkCopy.addEventListener('click', function () { if (K.link()) K.masol(K.link(), linkCopy, linkIn); });
  linkIn.addEventListener('focus', function () { linkIn.select(); });
  if (linkShare) linkShare.addEventListener('click', function () {
    var l = K.link();
    if (l && navigator.share) navigator.share({ title: document.title, url: l }).catch(function () { });
  });

  /* ---- rajzolás ---- */
  var keresSzam = 0;
  function rajzol() {
    var ids = K.lista();
    linkFrissit();
    var van = ids.length > 0;
    tools.hidden = !van;
    if (ics) ics.href = NAPTAR + '?p=' + ids.join(',');
    if (!van) {
      list.hidden = true; list.innerHTML = ''; sum.innerHTML = '';
      empty.hidden = false;
      K.jelol();
      return;
    }
    var sajat = ++keresSzam;
    fetch(KARTYAK + '?p=' + ids.join(','), { headers: { 'Accept': 'text/html' }, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
      .then(function (html) {
        if (sajat !== keresSzam) return;          // közben újabb kérés indult
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var ujLista = doc.getElementById('kvListUj'), ujSum = doc.getElementById('kvSumUj');
        list.innerHTML = ujLista ? ujLista.innerHTML : '';
        sum.innerHTML = ujSum ? ujSum.innerHTML : '';
        /* A mentett programok egyike sem nyilvános már (vagy nincs fesztiválnapi időpontja): üres állapot. */
        var kartyaVan = !!list.querySelector('.pk-card');
        list.hidden = !kartyaVan;
        empty.hidden = kartyaVan;
        K.jelol();
        if (note.classList.contains('is-err') && note.textContent === t('betoltes-hiba')) jegyzet('');
      })
      .catch(function (e) {
        if (sajat !== keresSzam) return;
        jegyzet(t('betoltes-hiba'), true);
        if (window.console) console.warn('[kedvencek] a lista nem töltődött be:', e && e.message);
      });
  }
  var rajzTimer = null;
  function rajzolKesobb() { clearTimeout(rajzTimer); rajzTimer = setTimeout(rajzol, 120); }

  /* ---- LEVÉTEL + VISSZAVONÁS (2026: 6 másodperc) ---- */
  var fuggo = null, fuggoTimer = null;
  function undoRejt() { undo.hidden = true; fuggo = null; }
  undoBt.addEventListener('click', function () {
    if (!fuggo) return;
    var id = fuggo;
    clearTimeout(fuggoTimer); undoRejt();
    K.valt(id, true);
  });

  document.addEventListener('fg:kedvencek', function (e) {
    var d = e.detail || {};
    if (d.forras === 'kattintas' && d.on === false) {
      var b = list.querySelector('[data-save="' + d.id + '"]');
      var cim = b ? b.closest('.pk-card').querySelector('.pk-cardlink') : null;
      if (cim) {
        fuggo = d.id;
        undoNm.textContent = cim.textContent;
        undo.hidden = false;
        clearTimeout(fuggoTimer);
        fuggoTimer = setTimeout(undoRejt, 6000);
      }
    }
    if (d.forras !== 'kattintas' || !modal || modal.hidden) rajzolKesobb();
    else zarvaRajzol = true;                   // nyitott ablak alatt ne cseréljük a listát; bezáráskor
  });
  document.addEventListener('fg:kedvenc-lista', linkFrissit);

  /* ---- ÁTFEDÉSHEZ UGRÁS (2026: ugrasUtkozesre) ---- */
  sum.addEventListener('click', function (e) {
    if (!e.target.closest('.kv-jump')) return;
    var el = list.querySelector('[data-first-clash]');
    if (!el) return;
    el.scrollIntoView({ behavior: reduceM ? 'auto' : 'smooth', block: 'center' });
    el.classList.remove('is-ping'); void el.offsetWidth; el.classList.add('is-ping');
    setTimeout(function () { el.classList.remove('is-ping'); }, 2600);
  });

  /* ---- ELREJTÉS / TÖRLÉS: két lépés a gombon belül (2026) ---- */
  function ketLepes(gomb, vegrehajt) {
    var elesitve = false, timer = null, eredeti = gomb.textContent;
    gomb.addEventListener('click', function () {
      if (!elesitve) {
        elesitve = true;
        gomb.classList.add('is-armed');
        gomb.textContent = gomb.getAttribute('data-megerosites');
        clearTimeout(timer);
        timer = setTimeout(function () { elesitve = false; gomb.classList.remove('is-armed'); gomb.textContent = eredeti; }, 6000);
        return;
      }
      clearTimeout(timer);
      elesitve = false; gomb.classList.remove('is-armed'); gomb.textContent = eredeti;
      vegrehajt();
    });
  }
  ketLepes(document.getElementById('kvHide'), function () { K.elrejt(); jegyzet(t('elrejtve')); });
  ketLepes(document.getElementById('kvWipe'), function () {
    K.torol().then(function () { jegyzet(t('torolve')); }, function (e) {
      jegyzet(t('torles-hiba'), true);
      if (window.console) console.warn('[kedvencek] a törlés nem sikerült:', e && e.message);
    });
  });

  /* ---- A FELUGRÓ ABLAK (a programkeresőé, helyben) ---- */
  var modal = document.getElementById('pkModal');
  var lastFocus = null, aktUrl = '', zarvaRajzol = false;
  function zar() {
    if (window.fgCal) window.fgCal.zar();
    modal.hidden = true;
    modal.innerHTML = '';
    document.body.style.overflow = '';
    if (lastFocus && document.contains(lastFocus)) { try { lastFocus.focus({ preventScroll: true }); } catch (_) { } }
    lastFocus = null;
    if (zarvaRajzol) { zarvaRajzol = false; rajzol(); }
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[data-kv-open]');
    if (a) {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
      e.preventDefault();
      lastFocus = a;
      aktUrl = a.href;
      fetch(a.href, { headers: { 'HX-Request': 'true', 'HX-Target': 'pkModal', 'X-Kedvencek': '1' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
        .then(function (html) {
          var uj = new DOMParser().parseFromString(html, 'text/html').getElementById('pkModal');
          if (!uj) throw new Error('nincs ablak');
          modal.innerHTML = uj.innerHTML;
          modal.hidden = false;
          document.body.style.overflow = 'hidden';
          K.jelol();
          var x = modal.querySelector('.pk-close');
          if (x) x.focus();
        })
        .catch(function () { window.location.href = a.href; });   // ha az ablak nem jön, a keresőben nyílik meg
      return;
    }
    if (e.target.closest && e.target.closest('#pkModal [data-pk-close]')) { e.preventDefault(); zar(); return; }
    var sh = e.target.closest && e.target.closest('#pkModal #pkMShare');
    if (sh) {
      /* A megosztott link a KERESŐBEN nyíló ablakra mutat (2026 ugyanígy). */
      var cim = sh.getAttribute('data-cim') || document.title;
      if (navigator.share) { navigator.share({ title: cim, url: aktUrl }).catch(function () { }); return; }
      if (navigator.clipboard) navigator.clipboard.writeText(aktUrl).then(function () {
        var s = sh.querySelector('span'), regi = s.textContent; s.textContent = sh.getAttribute('data-masolva');
        setTimeout(function () { s.textContent = regi; }, 1600);
      });
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.hidden) zar();
  });

  /* ---- indulás ---- */
  if (!K.taroloOk()) jegyzet(t('nincs-tarolo'), true);
  rajzol();
  var p = new URLSearchParams(location.search).get('lista');
  if (p) {
    K.megnyit(p).then(function () {
      jegyzet(t('link-betoltve'));
      try { history.replaceState(null, '', location.pathname); } catch (_) { }
    }, function (e) {
      jegyzet(e && e.allapot === 404 ? t('link-ervenytelen') : t('link-hiba'), true);
    });
  } else {
    K.frissit(true).catch(function (e) {
      if (window.console) console.warn('[kedvencek] a szerver most nem érhető el:', e && e.message);
    });
  }
})();
