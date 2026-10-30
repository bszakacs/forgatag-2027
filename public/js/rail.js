/*
 * KÖZÖS vízszintes csúszka (a 2026-os lib/rail → railScript, betűhűen átvéve): natív görgetés, egy-kártyás léptetés,
 * nyilak, húzható gomb, billentyűk, átméretezésre újramérve. Minden [data-rail] elemre; htmx-csere után (a csere
 * célterületén belüli csúszkára) újraindul. A jelölés: resources/views/partials/rail.blade.php.
 */
(function () {
  var RM = matchMedia('(prefers-reduced-motion:reduce)').matches;
  function indit(rail) {
    if (rail.__rail) return;
    rail.__rail = true;
    var key = rail.getAttribute('data-rail');
    var nav = document.querySelector('[data-railnav="' + key + '"]');
    var line = nav && nav.querySelector('.railline'), knob = nav && nav.querySelector('.railknob');
    var prev = nav && nav.querySelector('.railprev'), next = nav && nav.querySelector('.railnext');
    var maxS = function () { return Math.max(0, rail.scrollWidth - rail.clientWidth); };
    // egy lépés = az első kártya ÉLŐ szélessége + a gap (resize-ra újramérve)
    var stepPx = function () {
      var c = rail.children[0]; if (!c) return rail.clientWidth * 0.8;
      var w = c.getBoundingClientRect().width;
      var cs = getComputedStyle(rail);
      var gap = parseFloat(cs.columnGap || cs.gap || '0') || 0;
      return Math.max(80, w + gap);
    };
    function sync() {
      var m = maxS();
      if (nav) nav.hidden = (m <= 1);
      if (m <= 1) return;
      var r = rail.scrollLeft / m;
      if (knob) { knob.style.left = (r * 100) + '%'; knob.setAttribute('aria-valuenow', Math.round(r * 100)); }
      var sl = rail.scrollLeft;
      if (prev) prev.disabled = sl <= 4;
      if (next) next.disabled = sl >= m - 4;
    }
    function page(dir) { rail.scrollBy({ left: dir * stepPx(), behavior: RM ? 'auto' : 'smooth' }); }
    rail.addEventListener('scroll', sync, { passive: true });
    var rt; window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(sync, 150); });
    if (prev) prev.addEventListener('click', function () { page(-1); });
    if (next) next.addEventListener('click', function () { page(1); });
    if (knob && line) {
      var dragging = false, grabDx = 0;
      var drag = function (e) { var rr = line.getBoundingClientRect(); var x = (e.clientX - grabDx - rr.left) / rr.width; rail.scrollLeft = Math.max(0, Math.min(1, x)) * maxS(); };
      knob.addEventListener('pointerdown', function (e) { dragging = true; var kr = knob.getBoundingClientRect(); grabDx = e.clientX - (kr.left + kr.width / 2); try { knob.setPointerCapture(e.pointerId); } catch (_) {} e.preventDefault(); });
      knob.addEventListener('pointermove', function (e) { if (dragging) drag(e); });
      knob.addEventListener('pointerup', function (e) { dragging = false; try { knob.releasePointerCapture(e.pointerId); } catch (_) {} });
      knob.addEventListener('keydown', function (e) { var m = maxS(); if (e.key === 'ArrowLeft') { page(-1); e.preventDefault(); } else if (e.key === 'ArrowRight') { page(1); e.preventDefault(); } else if (e.key === 'Home') { rail.scrollLeft = 0; e.preventDefault(); } else if (e.key === 'End') { rail.scrollLeft = m; e.preventDefault(); } });
    }
    // Kezdéskor a csúszka MINDIG az elejéről induljon → a „vissza” nyíl rögtön tiltott.
    rail.scrollLeft = 0; sync();
    requestAnimationFrame(function () { rail.scrollLeft = 0; sync(); });
    setTimeout(sync, 300);
  }
  function mind(gyoker) { (gyoker || document).querySelectorAll('[data-rail]').forEach(indit); }
  document.addEventListener('DOMContentLoaded', function () { mind(); });
  window.addEventListener('load', function () { mind(); });
  document.addEventListener('htmx:afterSettle', function () { mind(); });
})();
