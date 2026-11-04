/*
 * A MOZGÁS- ÉS DEKOR-RÉTEG — EGY fájl, minden publikus lapon (layouts/app). A 2026-os megvalósítás átirata:
 *  · felúszás (.reveal → .in) — forgatag-os/frontend/src/rolunk/script.js („szekció-reveal”),
 *  · kártyák automatikus felúszása — forgatag-os/frontend/src/layouts/Base.astro („Szekció-reveal”),
 *  · a lapon szórt firkák (#pageDecor) — forgatag-os/frontend/src/lib/decor.ts (pageDecorScript).
 *
 * A vezérlés a témából jön: a sebesség (--dur-2), az átlátszóság (--doodle-opacity), a sűrűség és a lebegés
 * kikapcsolása (ThemeCss, [data-decor] szabályok), a „Minimális” fokozat (<html data-motion="minimal">).
 * 🔴 A rendszer „kevesebb mozgás” kérése (prefers-reduced-motion) MINDENT felülír: nincs felúszás (minden azonnal
 * látszik), és a szórt firkák fel sem épülnek (2026-ban is így: a sodródás a lényegük).
 *
 * Teljesítmény (a programkereső több száz kártyája miatt): görgetés-figyelő NINCS — egyetlen IntersectionObserver,
 * ami csak a még nem látszó elemeket figyeli, és felúszás után elengedi őket. A firkák EGY írással kerülnek a rétegbe
 * (egy HTML-szöveg), és a motívumot nem másolják: a lap közös készletére hivatkoznak (<use href="#fgm-N">, Theme::motifSprite).
 */
(function () {
  var root = document.documentElement;
  var RM = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  // A <head> csak akkor teszi ki a „mozgas” osztályt, ha szabad mozogni (nincs „kevesebb mozgás”, nem „Minimális”).
  var mozoghat = root.classList.contains('mozgas');

  /* ---------- FELÚSZÁS ---------- */
  // 2026: a Base-es lapok kártyái hatosával lépcsőzve (0,06 mp-enként) úsztak be. A 2026-os lista; ami ma még nincs a
  // 2027-es lapokon (helyszín-/kategórialap, támogatók, kereső), az a saját körében ugyanígy megkapja.
  // A „.pk-napi .pk-card”: a 2026-os napi nézet sorai (.rail-row) úsztak be — a 2027-es napi nézet addig a kereső
  // kártyáit használja, amíg a 12/b kör a 2026-os DailyProgramView-ra nem cseréli.
  var KARTYAK = '.pc, .taxon-card, .sponsor-card, .rail-row, .prog-card, .pk-napi .pk-card';

  var io = null;
  if (mozoghat && 'IntersectionObserver' in window) {
    io = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (!e.isIntersecting) return;
        var el = e.target;
        if (el.classList.contains('reveal')) {
          el.classList.add('in');
        } else {
          el.style.transitionDelay = (parseFloat(el.dataset.reveal || '0') || 0) + 's';
          el.classList.add('reveal-in');
          // A beúszás végén a kártya visszakapja a saját átmenetét (egérrel fölé víve a „Mikrointerakció-sebesség”).
          el.addEventListener('transitionend', function vege(ev) {
            if (ev.target !== el || ev.propertyName !== 'opacity') return;
            el.removeEventListener('transitionend', vege);
            el.classList.remove('reveal-init', 'reveal-in');
            el.style.transitionDelay = '';
          });
        }
        io.unobserve(el);
      });
    }, { threshold: 0.1 });
  }

  function felusztat(hatokor) {
    if (!io) {
      // Nincs mozgás: ami rejtve indult (.reveal), az azonnal látszik.
      [].forEach.call(hatokor.querySelectorAll('.reveal'), function (el) { el.classList.add('in'); });
      return;
    }
    [].forEach.call(hatokor.querySelectorAll('.reveal:not(.in)'), function (el) { io.observe(el); });
    [].forEach.call(hatokor.querySelectorAll(KARTYAK), function (el) {
      if (el.dataset.reveal !== undefined || (el.parentElement && el.parentElement.closest('[data-reveal]'))) return;
      var p = el.parentElement;
      var idx = p ? Array.prototype.indexOf.call(p.children, el) : 0;
      el.dataset.reveal = ((idx % 6) * 0.06).toFixed(2);
      if (el.getBoundingClientRect().top < window.innerHeight * 0.9) return; // már látszik → nem rejtjük el
      el.classList.add('reveal-init');
      io.observe(el);
    });
  }

  /* ---------- SZÓRT FIRKÁK (#pageDecor) ---------- */
  function rnd(a, b) { return a + Math.random() * (b - a); }
  // 🔴 A firka keretének viewBox-a „0 0 szélesség magasság” — NEM a motívum saját viewBox-a: a <use> a <symbol>-t a keret
  // 0,0 pontjára teszi, és ha a keret a motívum koordinátáit kapja (pl. „181.65 284.47 39.15 38.06”), a rajz a kereten kívül
  // esik — a firka üres doboz lesz (2026-11-04-ig így volt: a firkák a lapon voltak, de egy sem látszott).
  var MOTIVUMOK = [].map.call(document.querySelectorAll('.fg-motivumok symbol'), function (s) {
    var v = (s.getAttribute('viewBox') || '').trim().split(/[\s,]+/);
    return { id: s.id, vb: '0 0 ' + v[2] + ' ' + v[3] };
  });

  function szor(reteg) {
    // Rejtett réteg (firka-sűrűség „Nincs”) → nincs mit építeni.
    if (RM || !MOTIVUMOK.length || reteg.offsetParent === null) { reteg.innerHTML = ''; return; }
    var hgt = reteg.offsetHeight || window.innerHeight;
    var count = Math.max(10, Math.min(52, Math.round(hgt / 95)));
    var html = '';
    for (var i = 0; i < count; i++) {
      var m = MOTIVUMOK[(Math.random() * MOTIVUMOK.length) | 0];
      /* A JOBB oldali motívumok a right tulajdonsággal állnak (2026-08-23): left:70–100%-kal a doboz a lapon kívülre
         lógott, és a lap vízszintesen görgethetővé vált. */
      var hely = Math.random() < 0.5 ? 'left:' + rnd(0, 26).toFixed(1) + '%' : 'right:' + rnd(0, 26).toFixed(1) + '%';
      // 2026-ban a szkript --dx/--dy/--dur/--dl/--r0/--r1 változókat is írt, de a programkereső CSS-e ezeket nem
      // olvasta (az --fgl-* neveket várja) — hatástalanok voltak, ezért itt nincsenek; a látvány ugyanaz.
      html += '<div class="decor" style="width:' + rnd(40, 108).toFixed(0) + 'px;' + hely + ';top:' + rnd(0, 100).toFixed(1) +
        '%;opacity:calc(var(--doodle-opacity) * ' + (rnd(0.14, 0.26) / 0.72).toFixed(3) + ')">' +
        '<svg viewBox="' + m.vb + '"><use href="#' + m.id + '"/></svg></div>';
    }
    reteg.innerHTML = html; // EGY írás a teljes rétegre
  }

  function szorMind() { [].forEach.call(document.querySelectorAll('[data-szoras="oldal"]'), szor); }

  /* ---------- INDULÁS ---------- */
  felusztat(document);
  szorMind();
  // 2026-ban minden átméretezéskor újraszórt — telefonon a címsor be-/kicsúszása is átméretezés, ettől görgetés közben
  // ugráltak a firkák. Most csak a SZÉLESSÉG változására (forgatás, ablakméret).
  var szel = window.innerWidth, rt;
  window.addEventListener('resize', function () {
    if (window.innerWidth === szel) return;
    szel = window.innerWidth;
    clearTimeout(rt); rt = setTimeout(szorMind, 220);
  });
  // htmx-csere után (lapozás, szűrés) az új elemek is megkapják a felúszást.
  document.addEventListener('htmx:afterSettle', function (e) { felusztat(e.target && e.target.querySelectorAll ? e.target : document); });
})();
