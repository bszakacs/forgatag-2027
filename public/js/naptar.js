/*
 * NAPTÁRBA — a 2026-os lib/calendar-menu.ts (fgCal) átirata (PROMPT-21). EGY forrás, minden „Naptárba” gombnak: a gomb
 * `data-naptar` adatával (App\Support\Naptar::gombAdat) választólistát nyit: Google · Outlook.com · Microsoft 365 · Yahoo
 * webes hivatkozásként (a szolgáltató saját „új esemény” címe, letöltés nélkül), és Apple Naptár / egyéb a VALÓDI .ics
 * címre — az iOS a blob:/data: címet nem adja át a Naptárnak, egy https-címet `text/calendar` fejléccel igen.
 * Egy webes hivatkozásba EGY esemény fér: több időpontnál az elsőt adja, és ezt a lista ki is írja (nem hallgatjuk el).
 * Az időpontok a szerverről jönnek (UTC, a fesztiválnap hajnali szabályával) — itt nincs időzóna-számolás.
 * A sorrend iOS-en Apple-lel kezdődik (2026: tulajdonosi kérés), máshol Google-lel.
 * A gomb eseménykezelője a dokumentumon ül: a htmx-szel cserélt felugró ablak gombja is működik.
 */
(function () {
  'use strict';
  function cfg() {
    try { return (JSON.parse(document.getElementById('fgKedvencCfg').textContent) || {}).t || {}; } catch (_) { return {}; }
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
  }
  function isIos() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  }
  function iso(s) { return s.slice(0, 4) + '-' + s.slice(4, 6) + '-' + s.slice(6, 11) + ':' + s.slice(11, 13) + ':00Z'; }

  function linkek(ev) {
    var q = encodeURIComponent, cim = ev.cim || '', hely = ev.hely || '', d = ev.leiras || '';
    var ms = 'path=/calendar/action/compose&rru=addevent&subject=' + q(cim)
      + '&startdt=' + q(iso(ev.s)) + '&enddt=' + q(iso(ev.e)) + '&location=' + q(hely) + '&body=' + q(d);
    return {
      google: 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' + q(cim)
        + '&dates=' + ev.s + '/' + ev.e + '&location=' + q(hely) + '&details=' + q(d),
      outlook: 'https://outlook.live.com/calendar/0/deeplink/compose?' + ms,
      m365: 'https://outlook.office.com/calendar/0/deeplink/compose?' + ms,
      yahoo: 'https://calendar.yahoo.com/?v=60&title=' + q(cim) + '&st=' + ev.s + '&et=' + ev.e
        + '&in_loc=' + q(hely) + '&desc=' + q(d)
    };
  }

  var vege = null;
  function zar() {
    var m = document.querySelector('.fgcal-menu');
    if (m && m.parentNode) m.parentNode.removeChild(m);
    var g = document.querySelector('[data-fgcal-open]');
    if (g) { g.removeAttribute('data-fgcal-open'); g.setAttribute('aria-expanded', 'false'); }
    if (vege) { vege(); vege = null; }
  }

  function nyit(gomb, adat) {
    zar();
    if (!adat || !adat.ev) return;
    var L = cfg();
    var l = linkek(adat.ev);
    var box = document.createElement('div');
    box.className = 'fgcal-menu';
    box.setAttribute('role', 'menu');
    box.setAttribute('aria-label', L.cal_valassz || '');
    var sor = function (cim, href, uj, extra) {
      return '<a role="menuitem" class="fgcal-i' + extra + '" href="' + esc(href) + '"'
        + (uj ? ' target="_blank" rel="noopener"' : '') + '>' + esc(cim) + '</a>';
    };
    /* Az Apple-sor VALÓDI címre megy, download attribútum NÉLKÜL: iOS-en a letöltés kényszerítése épp azt akadályozná meg,
       hogy a Naptár nyíljon meg. Asztalon ugyanez a cím letöltésként viselkedik, és a naptár alkalmazás nyitja meg. */
    var apple = sor(L.cal_apple, adat.ics, false, ' fgcal-apple');
    var webes = sor(L.cal_google, l.google, true, '') + sor(L.cal_outlook, l.outlook, true, '')
      + sor(L.cal_m365, l.m365, true, '') + sor(L.cal_yahoo, l.yahoo, true, '');
    box.innerHTML = '<p class="fgcal-h">' + esc(L.cal_valassz) + '</p>'
      + (isIos() ? apple + webes : webes + apple)
      + (adat.tobb ? '<p class="fgcal-note">' + esc(adat.tobb) + '</p>' : '');
    document.body.appendChild(box);

    /* ELHELYEZÉS: a gomb alá, a képernyőn belülre húzva. Keskeny kijelzőn alsó lap (a CSS teszi teljes szélességűvé). */
    if (window.innerWidth > 560) {
      var r = gomb.getBoundingClientRect();
      var sz = box.offsetWidth || 260;
      var x = r.left;
      if (x + sz > window.innerWidth - 12) x = window.innerWidth - sz - 12;
      if (x < 12) x = 12;
      box.style.left = Math.round(x) + 'px';
      var alul = r.bottom + 8, magas = box.offsetHeight || 240;
      box.style.top = (alul + magas > window.innerHeight - 12 && r.top - magas - 8 > 12)
        ? Math.round(r.top - magas - 8) + 'px' : Math.round(alul) + 'px';
    }
    gomb.setAttribute('data-fgcal-open', '1');
    gomb.setAttribute('aria-expanded', 'true');
    var elso = box.querySelector('.fgcal-i');
    if (elso) elso.focus({ preventScroll: true });

    function kivul(e) {
      if (box.contains(e.target) || gomb.contains(e.target)) return;
      zar();
    }
    function nyomas(e) {
      if (e.key === 'Escape') { e.stopPropagation(); zar(); gomb.focus({ preventScroll: true }); }
    }
    setTimeout(function () {
      document.addEventListener('mousedown', kivul, true);
      document.addEventListener('touchstart', kivul, true);
      document.addEventListener('keydown', nyomas, true);
      window.addEventListener('resize', zar, true);
    }, 0);
    vege = function () {
      document.removeEventListener('mousedown', kivul, true);
      document.removeEventListener('touchstart', kivul, true);
      document.removeEventListener('keydown', nyomas, true);
      window.removeEventListener('resize', zar, true);
    };
    box.addEventListener('click', function (e) {
      if (e.target.classList.contains('fgcal-i')) setTimeout(zar, 0);
    });
  }

  document.addEventListener('click', function (e) {
    var g = e.target.closest && e.target.closest('[data-naptar]');
    if (!g) return;
    e.preventDefault();
    if (g.hasAttribute('data-fgcal-open')) { zar(); return; }
    var adat = null;
    try { adat = JSON.parse(g.getAttribute('data-naptar')); } catch (_) { adat = null; }
    nyit(g, adat);
  });
  /* A felugró ablak cseréje / bezárása után a lista ne maradjon nyitva (2026: program-modal.js zar → fgCal.close). */
  document.addEventListener('htmx:beforeSwap', zar);
  window.fgCal = { zar: zar };
})();
