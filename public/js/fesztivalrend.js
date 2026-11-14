/* ============================================================================
   FESZTIVÁLREND — viselkedés. Nincs külső függőség.
   A 2026-os ../forgatag-os/frontend/src/fesztivalrend/script.js átirata (CLAUDE.md 2/e, PROMPT-17): lenyíló (accordion),
   a nyitókép két szélén lengő firkák, a záró szekció ismétlődő „tűzijátéka” — a számok, időzítések, méretek betűre.
   Eltérések (mind a 2027-es rétegből):
    · a felúszás (2026: a szkript első sora, .reveal → .in) a közös mozgas.js-é — itt nincs második példánya;
    · a firkák a téma 12 motívumából (a lap közös készlete: <use href="#fgm-N">, Theme::motifSprite) — 2026-ban ugyanez a
      12 rajz a szkriptbe volt égetve. A nyitókép rétege [data-decor="hero"] jelölést kapott, így a téma firka-kapcsolói hatnak;
    · a „Minimális” mozgás-fokozat (data-motion) a tűzijátékot is a „kevesebb mozgás” szerint kezeli (nem indul).
   ========================================================================= */
(function(){
  document.querySelectorAll('.fr-acc-head').forEach(function(b){
    b.addEventListener('click',function(){var a=b.closest('.fr-acc');var o=a.classList.toggle('open');b.setAttribute('aria-expanded',o?'true':'false');});
  });

  // 🔴 A keret viewBox-a „0 0 sz m” (NEM a motívum sajátja) — különben a <use> a rajzot a kereten kívülre teszi (mozgas.js).
  var SVGS = [].map.call(document.querySelectorAll('.fg-motivumok symbol'), function(s){
    var v = (s.getAttribute('viewBox') || '').trim().split(/[\s,]+/);
    return '<svg viewBox="0 0 ' + v[2] + ' ' + v[3] + '"><use href="#' + s.id + '"/></svg>';
  });
  if(!SVGS.length) return;
  function rnd(a,b){return a+Math.random()*(b-a);}
  function pick(){return SVGS[(Math.random()*SVGS.length)|0];}
  function makeHeroDecor(container){
    if(!container) return;
    var mobw=window.innerWidth<=720, slots=mobw?5:7;
    var sides=mobw?[[2,7],[93,98]]:[[3,10],[90,97]];
    var wMin=mobw?24:44, wMax=mobw?40:74, axMin=mobw?12:22, axMax=mobw?20:40;
    for(var s=0;s<sides.length;s++){for(var i=0;i<slots;i++){
      var el=document.createElement('div');el.className='decor hero-decor';el.innerHTML=pick();
      var w=rnd(wMin,wMax),x=rnd(sides[s][0],sides[s][1]),slotH=100/slots;
      var yy=slotH*i+slotH/2+rnd(-slotH*0.14,slotH*0.14),ax=rnd(axMin,axMax),ay=rnd(8,18);
      el.style.width=w+'px';el.style.left=x+'%';el.style.top=yy+'%';
      /* átlátszóság a --doodle-opacity tokenre skálázva (a gyári .72 az eredeti sávot adja) */
      el.style.opacity='calc(var(--doodle-opacity, .72) * '+(rnd(mobw?.5:.68,mobw?.72:.9)/0.72).toFixed(3)+')';
      el.style.setProperty('--ax',ax.toFixed(0)+'px');el.style.setProperty('--ay',ay.toFixed(0)+'px');
      el.style.setProperty('--dur',rnd(7,12).toFixed(1)+'s');el.style.setProperty('--dl',(-rnd(0,8)).toFixed(1)+'s');
      el.style.setProperty('--r0',rnd(-10,10).toFixed(1)+'deg');el.style.setProperty('--r1',rnd(-10,10).toFixed(1)+'deg');
      container.appendChild(el);
    }}
  }
  makeHeroDecor(document.getElementById('frHeroDecor'));

  // ISMÉTLŐDŐ tűzijáték: kirobban, elhalványul (opacity 0), majd új pont mögött robban
  var burstL=document.getElementById('frBurstL'), burstR=document.getElementById('frBurstR');
  var fx=document.querySelector('.fr-fx');
  var sec=document.querySelector('[data-section="fesztivalrend-thanks"]');
  var col=sec?sec.querySelector('.fr-closing'):null;
  var reduce=window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.hasAttribute('data-motion');
  var timer=null, timer2=null;
  function fillBurst(burst,cx,cy,rx,ry){
    burst.style.left=cx+'px'; burst.style.top=cy+'px'; burst.innerHTML='';
    var mobw=window.innerWidth<=720, N=mobw?9:16;
    for(var i=0;i<N;i++){
      var el=document.createElement('div');el.className='fr-spark';el.innerHTML=pick();
      var ang=(Math.PI*2)*(i/N)+rnd(-0.28,0.28), f=rnd(0.26,1), w=rnd(mobw?24:40,mobw?42:72);
      el.style.width=w+'px';
      el.style.setProperty('--tx',(Math.cos(ang)*rx*f).toFixed(0)+'px');
      el.style.setProperty('--ty',(Math.sin(ang)*ry*f).toFixed(0)+'px');
      el.style.setProperty('--d',rnd(0,0.34).toFixed(2)+'s');
      burst.appendChild(el);
    }
    requestAnimationFrame(function(){burst.querySelectorAll('.fr-spark').forEach(function(s){s.classList.add('go');});});
  }
  function fireOnce(){
    if(!fx||!burstL||!burstR) return;
    var W=fx.clientWidth, H=fx.clientHeight, cy=H/2;
    var mobw=window.innerWidth<=720, half=(mobw?42:72)/2;
    var Cw=col?col.offsetWidth:Math.min(760,W*0.7);
    var side=Math.max(0,(W-Cw)/2);
    if(side<120){ burstR.innerHTML=''; fillBurst(burstL, W*0.5, H*0.24, W*0.42-half-6, H*0.2-half-6); return; }
    var rx=side/2-half-8, ry=H/2-half-14;
    if(rx<22)rx=22; if(ry<60)ry=60;
    var Lx=side/2, Rx=W-side/2, leftFirst=Math.random()<0.5;
    fillBurst(leftFirst?burstL:burstR, leftFirst?Lx:Rx, cy, rx, ry);   // one side pops first
    clearTimeout(timer2);
    timer2=setTimeout(function(){ fillBurst(leftFirst?burstR:burstL, leftFirst?Rx:Lx, cy, rx, ry); }, rnd(240,900)); // the other a beat later
  }
  function loop(){ fireOnce(); timer=setTimeout(loop, rnd(1700,2600)); }
  if(!reduce){
    var bio=new IntersectionObserver(function(es){es.forEach(function(e){
      if(e.isIntersecting){ if(!timer) loop(); }
      else { clearTimeout(timer); clearTimeout(timer2); timer=null; if(burstL)burstL.innerHTML=''; if(burstR)burstR.innerHTML=''; }
    });},{threshold:.30});
    if(sec) bio.observe(sec);
  }
})();
