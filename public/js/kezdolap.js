/* ============================================================================
   FŐOLDAL — viselkedés. Nincs külső függőség, nincs CDN.
   A 2026-os ../forgatag-os/frontend/src/home-v7/script.js átirata (CLAUDE.md 2/e, PROMPT-14), részenként:
   hero-slider · visszaszámláló (a 2026-08-26 előtti változat, git 122d8b3^) · kép-tartalék · firka-dekor · szám-számláló.
   Eltérések (mind a kiszolgálásból vagy a 2027-es rétegből):
    · a felúszás (2026: 2. BEÚSZÁS) a közös mozgas.js-é — itt nincs második példánya;
    · a nyitókép 2–5. diája a lap betöltése UTÁN töltődik (telefonon a kijelzőhöz vágott kisebb kép) — 2026-ban mind az öt
      azonnal, az első kép elől véve a sávszélességet;
    · a firkák a téma 12 motívumából (a lap közös készlete: <use href="#fgm-N">, Theme::motifSprite) — 2026-ban a 12 motívum
      a szkriptbe volt égetve (az első 6 cserélhető). A rétegek [data-decor] jelölést kaptak, így a téma sűrűség-, lebegés-
      és „Mozgás-mennyiség” beállítása itt is hat (2026-ban a kezdőlapon nem hatott);
    · a szervezők firkái csak SZÉLESSÉG-változásra épülnek újra (2026: minden átméretezésre — telefonon a címsáv
      be-/kicsúszására is, ettől ugráltak; mint a mozgas.js);
    · a „Minimális” mozgás-fokozat (data-motion) a számlálót is a „kevesebb mozgás” szerint kezeli (rögtön a végérték).
   ========================================================================= */
(function(){
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.hasAttribute('data-motion');
  var rnd = function(a,b){ return a + Math.random()*(b-a); };

  /* ---- 4. KÉP-TARTALÉK (2026: helyőrző, ha nincs kép) — a lapon <img class="kep-kitolt"> áll a háttérkép helyén ---- */
  function tartalek(im){
    im.addEventListener('error', function(){
      im.remove();
      im.parentElement && (im.parentElement.style.backgroundImage = 'linear-gradient(140deg, var(--panel), var(--doodle-purple))');
    });
  }
  [].forEach.call(document.querySelectorAll('img.kep-kitolt'), function(im){
    if (im.complete && im.naturalWidth === 0 && im.currentSrc) { var p = im.parentElement; im.remove(); if (p) p.style.backgroundImage = 'linear-gradient(140deg, var(--panel), var(--doodle-purple))'; }
    else tartalek(im);
  });

  /* ---- 1. HERO SLIDER -------------------------------------------------- */
  var slidesWrap = document.getElementById('slides');
  var dotsWrap   = document.getElementById('dots');
  if(slidesWrap && dotsWrap){
    var slides = [].slice.call(slidesWrap.querySelectorAll('.slide'));
    var idx = 0;
    // A 2–5. dia képe a lap betöltése után (a kijelzőhöz illő változat).
    var tolt = function(){
      var mobil = window.matchMedia('(max-width:720px)').matches;
      slides.forEach(function(s){
        var src = s.getAttribute(mobil ? 'data-kep-mobil' : 'data-kep');
        if(!src || s.querySelector('img')) return;
        var im = document.createElement('img');
        im.className = 'kep-kitolt'; im.alt = ''; im.decoding = 'async'; tartalek(im); im.src = src;
        s.appendChild(im);
      });
    };
    if(document.readyState === 'complete') tolt(); else window.addEventListener('load', tolt);
    slides.forEach(function(_,i){
      var b = document.createElement('b');
      b.setAttribute('role','button');
      b.setAttribute('tabindex','0');
      b.setAttribute('aria-label', (i+1) + '. kép');
      if(i===0) b.className = 'on';
      b.addEventListener('click', function(){ go(i); });
      b.addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); go(i); } });
      dotsWrap.appendChild(b);
    });
    var dots = [].slice.call(dotsWrap.children);
    function go(n){
      tolt();
      slides[idx].classList.remove('on'); dots[idx].classList.remove('on');
      idx = n;
      slides[idx].classList.add('on');    dots[idx].classList.add('on');
    }
    if(slides.length > 1) setInterval(function(){ go((idx+1) % slides.length); }, 6000);
  }

  /* ---- 3. VISSZASZÁMLÁLÓ (a dátum a data-start attribútumból) ---------- */
  var cd = document.querySelector('[data-comp="countdown"]');
  if(cd){
    var target = new Date(cd.getAttribute('data-start'));
    var out = {
      days:  cd.querySelector('[data-cd="days"]'),
      hours: cd.querySelector('[data-cd="hours"]'),
      mins:  cd.querySelector('[data-cd="mins"]')
    };
    function tickCountdown(){
      var diff = target - new Date();
      if(diff <= 0){ out.days.textContent='0'; out.hours.textContent='0'; out.mins.textContent='0'; return; }
      out.days.textContent  = Math.floor(diff/86400000);
      out.hours.textContent = Math.floor(diff % 86400000 / 3600000);
      out.mins.textContent  = Math.floor(diff % 3600000 / 60000);
    }
    tickCountdown();
    setInterval(tickCountdown, 30000);
  }

  /* ---- 5. FIRKA-DEKOR -------------------------------------------------- */
  // 🔴 A keret viewBox-a „0 0 sz m” (NEM a motívum sajátja) — különben a <use> a rajzot a kereten kívülre teszi (mozgas.js).
  var MOTIVUMOK = [].map.call(document.querySelectorAll('.fg-motivumok symbol'), function(s){
    var v = (s.getAttribute('viewBox') || '').trim().split(/[\s,]+/);
    return '<svg viewBox="0 0 ' + v[2] + ' ' + v[3] + '"><use href="#' + s.id + '"/></svg>';
  });
  function pick(){ return MOTIVUMOK[(Math.random()*MOTIVUMOK.length)|0]; }
  /* a szórt átlátszóság a --doodle-opacity tokenre van skálázva:
     a token alapértékén (.72) pontosan az eredeti .42–.96 sávot adja vissza */
  function setOpacity(el, v){ el.style.opacity = 'calc(var(--doodle-opacity, .72) * ' + (v/0.72).toFixed(3) + ')'; }

  function makeDecor(container, count, o){
    for(var i=0;i<count;i++){
      var el = document.createElement('div');
      el.className = 'decor';
      el.innerHTML = pick();
      var side = Math.random() < .5 ? 0 : 1;
      el.style.width = rnd(o.min,o.max) + 'px';
      el.style.left  = (side ? rnd(o.rightFrom,o.rightTo) : rnd(o.leftFrom,o.leftTo)) + '%';
      el.style.top   = rnd(0,100) + '%';
      setOpacity(el, rnd(o.opMin,o.opMax));
      el.style.animationDuration = rnd(o.durMin,o.durMax).toFixed(1) + 's';
      el.style.animationDelay    = (-rnd(0,20)).toFixed(1) + 's';
      el.style.setProperty('--fgl-dx', rnd(-8,8).toFixed(1)+'px');
      el.style.setProperty('--fgl-dy', rnd(o.dyMin,o.dyMax).toFixed(0)+'px');
      el.style.setProperty('--fgl-r0', rnd(-8,8).toFixed(1)+'deg');
      el.style.setProperty('--fgl-r1', rnd(-8,8).toFixed(1)+'deg');
      container.appendChild(el);
    }
  }

  /* hero: kimért rács a széleken, HELYBEN nagy amplitúdójú lengés (nem fedik egymást) */
  function makeHeroDecor(container){
    if(!container) return;
    var mob = window.innerWidth <= 720;
    var slots = mob ? 4 : 5;
    var sides = mob ? [[1,4],[96,99]] : [[3,9],[87,95]];
    var wMin = mob?28:52, wMax = mob?44:84, axMin = mob?12:26, axMax = mob?20:46;
    for(var s=0;s<sides.length;s++){
      for(var i=0;i<slots;i++){
        var el = document.createElement('div');
        el.className = 'decor hero-decor';
        el.innerHTML = pick();
        var slotH = 100/slots;
        el.style.width = rnd(wMin,wMax) + 'px';
        el.style.left  = rnd(sides[s][0],sides[s][1]) + '%';
        el.style.top   = (slotH*i + slotH/2 + rnd(-slotH*.14, slotH*.14)) + '%';
        setOpacity(el, rnd(mob?.6:.78, mob?.8:.96));
        el.style.animationDuration = rnd(7,12).toFixed(1) + 's';
        el.style.animationDelay    = (-rnd(0,8)).toFixed(1) + 's';
        el.style.setProperty('--fgl-ax', rnd(axMin,axMax).toFixed(0)+'px');
        el.style.setProperty('--fgl-ay', rnd(8,18).toFixed(0)+'px');
        el.style.setProperty('--fgl-r0', rnd(-10,10).toFixed(1)+'deg');
        el.style.setProperty('--fgl-r1', rnd(-10,10).toFixed(1)+'deg');
        container.appendChild(el);
      }
    }
  }

  var page = document.getElementById('pageDecor');
  if(MOTIVUMOK.length){
    makeHeroDecor(document.getElementById('heroDecor'));

    if(page) makeDecor(page, 52, {min:40,max:108,leftFrom:0,leftTo:30,rightFrom:70,rightTo:100,
                                  opMin:.42,opMax:.72,dyMin:92,dyMax:140,durMin:12,durMax:17});

    /* az átlátszatlan hátterű szekciók saját háttér-dekorja (a tartalom alatt) */
    var bandDecor = document.getElementById('bandDecor');
    if(bandDecor) makeDecor(bandDecor, 12, {min:36,max:88,leftFrom:0,leftTo:26,rightFrom:74,rightTo:100,
                                            opMin:.34,opMax:.58,dyMin:70,dyMax:110,durMin:13,durMax:18});
    var mediaDecor = document.getElementById('mediaDecor');
    if(mediaDecor) makeDecor(mediaDecor, 16, {min:38,max:96,leftFrom:0,leftTo:28,rightFrom:72,rightTo:100,
                                            opMin:.4,opMax:.68,dyMin:80,dyMax:120,durMin:12,durMax:17});
  }

  /* a globális dekor pontosan a hero alja és a lábléc között ül */
  function sizePageDecor(){
    if(!page) return;
    var hero = document.querySelector('[data-section="hero"]');
    var foot = document.querySelector('footer');
    var top = hero ? hero.offsetHeight : 0;
    var bottom = foot ? foot.offsetTop : document.body.scrollHeight;
    page.style.top = top + 'px';
    page.style.height = Math.max(0, bottom - top) + 'px';
  }
  sizePageDecor();
  window.addEventListener('load', sizePageDecor);
  window.addEventListener('resize', sizePageDecor);

  /* Szervezők (fehér) szekció: MOBILON semmi; desktopon CSAK a tartalomsávon kívül,
     a valódi oldalmargókba — sosem kártya/szöveg alá */
  var orgDecor = document.getElementById('orgDecor');
  var orgSection = document.querySelector('[data-section="organizers"]');
  function buildOrgDecor(){
    if(!orgDecor || !orgSection || !MOTIVUMOK.length) return;
    orgDecor.innerHTML = '';
    if(window.innerWidth <= 720) return;
    var wr = orgSection.querySelector('.wrap').getBoundingClientRect();
    var vw = document.documentElement.clientWidth;
    var pad = 10, perSide = 7;
    function placeSide(x0, gutter){
      if(gutter < 44) return;
      var maxW = Math.min(gutter - 2*pad, 74);
      if(maxW < 22) return;
      for(var i=0;i<perSide;i++){
        var w = rnd(Math.max(20, maxW*.5), maxW);
        var el = document.createElement('div');
        el.className = 'decor';
        el.innerHTML = pick();
        el.style.width = w + 'px';
        el.style.left  = (x0 + rnd(pad, Math.max(pad, gutter - w - pad))) + 'px';
        el.style.top   = rnd(2,98) + '%';
        setOpacity(el, rnd(.5,.8));
        el.style.animationDuration = rnd(13,18).toFixed(1) + 's';
        el.style.animationDelay    = (-rnd(0,14)).toFixed(1) + 's';
        el.style.setProperty('--fgl-dx', rnd(-6,6).toFixed(1)+'px');
        el.style.setProperty('--fgl-dy', rnd(70,120).toFixed(0)+'px');
        el.style.setProperty('--fgl-r0', rnd(-8,8).toFixed(1)+'deg');
        el.style.setProperty('--fgl-r1', rnd(-8,8).toFixed(1)+'deg');
        orgDecor.appendChild(el);
      }
    }
    placeSide(0, wr.left);
    placeSide(wr.right, vw - wr.right);
  }
  buildOrgDecor();
  var szel = window.innerWidth;
  window.addEventListener('load', buildOrgDecor);
  window.addEventListener('resize', function(){ if(window.innerWidth === szel) return; szel = window.innerWidth; buildOrgDecor(); });

  /* ---- 6. „FORGATAG SZÁMOKBAN" — count-up, amikor láthatóvá válik ------ */
  var numbers = document.getElementById('numbers');
  if(numbers && !reduce && 'IntersectionObserver' in window){
    var fmt = function(n){
      var s = String(n), out = '', c = 0;
      for(var i=s.length-1;i>=0;i--){ out = s[i] + out; if(++c % 3 === 0 && i > 0) out = ' ' + out; }
      return out;
    };
    // A szerver a végértéket írja ki (JS nélkül az látszik); a számlálás — mint 2026-ban — 0-ról indul.
    [].forEach.call(numbers.querySelectorAll('b[data-target]'), function(b){ b.textContent = '0'; });
    var nio = new IntersectionObserver(function(es){
      es.forEach(function(e){
        if(!e.isIntersecting) return;
        [].slice.call(numbers.querySelectorAll('b[data-target]')).forEach(function(b){
          var t = parseInt(b.getAttribute('data-target'),10);
          var suffix = b.getAttribute('data-suffix') || '';
          var dur = 1500, t0 = null;
          function step(ts){
            if(t0 === null) t0 = ts;
            var p = Math.min(1,(ts-t0)/dur);
            b.textContent = fmt(Math.round(t * (1 - Math.pow(1-p,3)))) + suffix;
            if(p < 1) requestAnimationFrame(step);
          }
          requestAnimationFrame(step);
        });
        nio.disconnect();
      });
    },{threshold:.35});
    nio.observe(numbers);
  }
})();
