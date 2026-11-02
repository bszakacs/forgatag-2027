/*
 * Rólunk — a történet „kötődő-fonala”: a görgetésre kirajzolódó kanyargós vonal a képek között, a rajta haladó
 * jelölőkarikával. BETŰRE a 2026-os forgatag-os/frontend/src/rolunk/script.js „STORY” blokkja. Eltérések: a görgetés-
 * kezelő képkockánként legfeljebb egyszer számol (requestAnimationFrame) — a látvány ugyanaz, telefonon kíméletesebb;
 * és a karika INDULÓ helye (lásd „eloreny”): ha a fonal eleje a képernyő közepe fölött van (mobil), a karika a fonal
 * elejéről indul, nem a képernyő közepéről — különben induláskor az első kép közepére, a kép MÖGÉ esett, és nem látszott
 * (tulajdonosi jelzés, 2026-11-02). Asztali gépen (a fonal eleje a képernyő közepe alatt) ez 0: a 2026-os viselkedés.
 * (A szekciók felúszása a közös rétegben: public/js/mozgas.js. A Borkert-ablakot az Alpine nyitja.)
 */
(function () {
  var story = document.querySelector('.about-page .story'); if (!story) return;
  var wrap = story.querySelector('.wrap');
  var track = document.getElementById('thread-track'), prog = document.getElementById('thread-prog'),
      marker = document.getElementById('thread-marker'), svg = document.getElementById('thread');
  if (!wrap || !track || !prog || !svg) return;
  var samps = [], secH = 0, eloreny = 0;
  function smooth(p) {
    var d = 'M' + p[0][0] + ',' + p[0][1];
    for (var i = 0; i < p.length - 1; i++) {
      var p0 = p[i - 1] || p[i], p1 = p[i], p2 = p[i + 1], p3 = p[i + 2] || p2;
      var c1x = p1[0] + (p2[0] - p0[0]) / 6, c1y = p1[1] + (p2[1] - p0[1]) / 6,
          c2x = p2[0] - (p3[0] - p1[0]) / 6, c2y = p2[1] - (p3[1] - p1[1]) / 6;
      d += ' C' + c1x + ',' + c1y + ' ' + c2x + ',' + c2y + ' ' + p2[0] + ',' + p2[1];
    }
    return d;
  }
  function build() {
    var W = story.clientWidth, H = story.clientHeight, sr = story.getBoundingClientRect();
    svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
    var figEls = [].slice.call(wrap.querySelectorAll('.sfig-img'));
    var frects = figEls.map(function (f) { var r = f.getBoundingClientRect(); return { cx: r.left - sr.left + r.width / 2, cy: r.top - sr.top + r.height / 2 }; });
    var anch = [[W / 2, 0]]; frects.forEach(function (r) { anch.push([r.cx, r.cy]); }); anch.push([W / 2, H]);
    var amp = Math.min(150, W * 0.16), pts = [], dir = 1;
    for (var i = 0; i < anch.length - 1; i++) { pts.push(anch[i]); pts.push([(anch[i][0] + anch[i + 1][0]) / 2 + amp * dir, (anch[i][1] + anch[i + 1][1]) / 2]); dir *= -1; }
    pts.push(anch[anch.length - 1]);
    var d = smooth(pts); track.setAttribute('d', d); prog.setAttribute('d', d);
    var len = track.getTotalLength(); prog.style.strokeDasharray = len; prog.dataset.len = len;
    // Mennyivel van a fonal eleje (a lap TETEJÉRE görgetve) a képernyő közepe fölött — ennyivel késleltetjük a karikát.
    eloreny = Math.max(0, window.innerHeight / 2 - (sr.top + window.scrollY));
    secH = H; samps = []; var SN = 160;
    for (var sp = 0; sp <= SN; sp++) { var pp = track.getPointAtLength(len * sp / SN); samps.push({ l: len * sp / SN, x: pp.x, y: pp.y }); }
    onScroll();
  }
  function onScroll() {
    var sTop = story.getBoundingClientRect().top, vh = window.innerHeight, len = +prog.dataset.len || 0;
    if (!len || !samps.length) return;
    // Induláskor a karika TELJESEN látsszon: a középpontja a sugarával (15 + a gyűrű) a szakasz széle alatt.
    var localY = vh / 2 - sTop - eloreny; localY = Math.max(eloreny > 0 ? 18 : 0, Math.min(secH, localY));
    var i0 = 0, i1 = 0;
    if (localY <= samps[0].y) { i0 = i1 = 0; }
    else if (localY >= samps[samps.length - 1].y) { i0 = i1 = samps.length - 1; }
    else { for (var s = 0; s < samps.length - 1; s++) { if (samps[s].y <= localY && samps[s + 1].y >= localY) { i0 = s; i1 = s + 1; break; } } }
    var a = samps[i0], b = samps[i1] || a, f = (b.y === a.y) ? 0 : (localY - a.y) / (b.y - a.y); f = Math.max(0, Math.min(1, f));
    var mx = a.x + (b.x - a.x) * f, ml = a.l + (b.l - a.l) * f;
    prog.style.strokeDashoffset = len - ml;
    if (marker) {
      if (sTop < vh - 40 && sTop + secH > 40) { marker.setAttribute('transform', 'translate(' + mx + ',' + localY + ')'); marker.style.display = ''; }
      else marker.style.display = 'none';
    }
  }
  var kocka = 0;
  function gorget() { if (!kocka) kocka = requestAnimationFrame(function () { kocka = 0; onScroll(); }); }
  window.addEventListener('load', build); window.addEventListener('resize', build);
  window.addEventListener('scroll', gorget, { passive: true });
  build(); setTimeout(build, 400);
})();
