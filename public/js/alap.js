/*
 * A 2026-os Base.astro lap-szkriptjének átirata (CLAUDE.md 2/e, PROMPT-15) — a helyszín-, kategória-, napi- és programlapon.
 * Ami itt van, betűre: a „MA” jelölés a kártyán (a mai dátumot tartalmazó, nem lemondott/változott program: .pc-ma +
 * a rejtett „MA” jelvény), a mai nap fülén a „MA” jelvény, és a [data-print] gomb (nyomtatás).
 * Ami NINCS itt, és miért: a kártyák felúszása a közös mozgás-rétegé (mozgas.js — ugyanaz a 2026-os lista);
 * a kedvenc-szív a kedvencek körében kapcsol be (addig tiltott, mint a programkeresőben).
 */
(function () {
  var d = new Date();
  var today = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  var ma = document.documentElement.lang === 'ro' ? 'AZI' : 'MA';

  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-print]')) window.print();
  });

  document.querySelectorAll('[data-occ-dates]').forEach(function (el) {
    var dates = (el.dataset.occDates || '').split(',');
    if (dates.indexOf(today) < 0) return;
    el.classList.add('pc-ma', 'pc-has-status');
    var badge = el.querySelector('[data-ma-badge]');
    if (badge) badge.hidden = false;
  });

  var tab = document.querySelector('.daytabs a[data-day="' + today + '"]');
  if (tab && !tab.querySelector('.today-badge')) {
    var b = document.createElement('span');
    b.className = 'today-badge';
    b.textContent = ma;
    tab.appendChild(b);
    tab.classList.add('is-today');
  }
})();
