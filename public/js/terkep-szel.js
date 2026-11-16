/*
 * A TÉRKÉP SZÉLSŐ JELVÉNYEINEK ELHELYEZÉSE — a 2026-os lib/offmap.ts placeOffmapBadges + bearingSide BETŰRE.
 * KÜLÖN fájlban, mert KÉT fogyasztója van (mint 2026-ban a lib/offmap.ts-nek): a publikus térkép (terkep.js) és a
 * térkép-szerkesztő (terkep-szerkeszto.js, PROMPT-18) — így a szerkesztő RÖGTÖN ott mutatja a jelvényt, ahol élesben lesz.
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
