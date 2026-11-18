/* ============================================================
   A Családi Olimpia jelentkező űrlapja (PROMPT-20) — a 2026-os olympics-api/public/index.html szkriptjének átirata.
   Az űrlapot (tagsorok, színek, kapacitás) a szerver rajzolja; itt: a kijelölés, a 2026-os böngészőoldali ellenőrzés
   (ugyanazok a szövegek), a beküldés, és ÜTKÖZÉSKOR a friss színlista — a BEÍRT ADATOK MARADNAK, csak színt kell váltani.
   A döntés a szerveré (az adatbázis egyedi kulcsa); ez itt csak kényelem.
   ============================================================ */
(function () {
  'use strict';
  var $ = function (s) { return document.querySelector(s); };
  var el = function (t, c, txt) { var e = document.createElement(t); if (c) e.className = c; if (txt != null) e.textContent = txt; return e; };
  var form = $('#form');
  if (!form) return;

  // Csapatszín-kijelölés: delegálva, mert ütközéskor a lista kicserélődik.
  $('#colors').addEventListener('change', function (ev) {
    var inp = ev.target;
    if (!inp || inp.name !== 'color') return;
    $('#colors').querySelectorAll('.color').forEach(function (x) { x.classList.remove('sel'); });
    if (inp.checked) inp.closest('.color').classList.add('sel');
  });
  $('#declaration').addEventListener('change', function () { $('#declChk').classList.toggle('sel', $('#declaration').checked); });

  function selectedColor() { var r = document.querySelector('input[name=color]:checked'); return r ? r.value : ''; }

  function showErrors(errs) {
    var m = $('#msg'); m.innerHTML = '';
    var box = el('div', 'msg msg-err'); box.appendChild(el('strong', null, 'Kérlek javítsd ezeket:'));
    var ul = el('ul'); errs.forEach(function (e) { ul.appendChild(el('li', null, e)); }); box.appendChild(ul);
    m.appendChild(box); window.scrollTo(0, 0);
  }

  function ellenoriz() {
    var errs = [];
    if ($('#team_name').value.trim().length < 2) errs.push('Add meg a csapat nevét.');
    for (var i = 1; i <= 7; i++) {
      var n = $('#member' + i + '_name').value.trim(), a = $('#member' + i + '_age').value.trim();
      if (i <= 5) {
        if (n.length < 2) errs.push(i + '. csapattag: add meg a nevet.');
        if (!a || !(Number(a) > 0 && Number(a) <= 120)) errs.push(i + '. csapattag: add meg az életkort.');
      } else if (n.length > 0 || a.length > 0) {
        if (n.length < 2) errs.push(i + '. csapattag: ha megadod az életkort, a nevet is add meg.');
        if (!a || !(Number(a) > 0 && Number(a) <= 120)) errs.push(i + '. csapattag: ha megadod a nevet, az életkort is add meg.');
      }
    }
    if (!/^[+()\d][\d\s()/.-]{5,}$/.test($('#captain_phone').value.trim())) errs.push('Add meg a csapatkapitány telefonszámát.');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($('#captain_email').value.trim())) errs.push('Adj meg érvényes e-mail címet.');
    if (!selectedColor()) errs.push('Válassz csapatszínt.');
    if (!$('#declaration').checked) errs.push('A nyilatkozat elfogadása kötelező.');
    return errs;
  }

  var btn = $('#submitBtn');
  function gombVissza() { btn.disabled = false; btn.textContent = 'Jelentkezés elküldése'; }

  form.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    $('#msg').innerHTML = '';
    var errs = ellenoriz();
    if (errs.length) { showErrors(errs); return; }

    btn.disabled = true; btn.textContent = 'Küldés…';
    var r, j = null;
    try {
      r = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      try { j = await r.json(); } catch (e) { j = null; }
    } catch (e) { showErrors(['Hálózati hiba. Próbáld újra.']); gombVissza(); return; }

    if (!r.ok || !j || !j.ok) {
      // Ha közben elkelt a szín (409) vagy bármi frissült: a színeket és a kapacitást újrarajzoljuk,
      // de a BEÍRT ADATOK maradnak — csak új színt kell választani.
      if (j && typeof j.colorsHtml === 'string') $('#colors').innerHTML = j.colorsHtml;
      if (j && typeof j.capacityHtml === 'string') $('#capacity').innerHTML = j.capacityHtml;
      var uzenet = (j && j.errors) ? j.errors
        : r.status === 419 ? ['A lap túl régóta volt nyitva, ezért a biztonsági kulcsa lejárt. Töltsd újra a lapot (a beírt adatokat előbb érdemes kimásolni).']
        : ['A beküldés nem sikerült.'];
      showErrors(uzenet);
      gombVissza();
      if (j && j.colorTaken) { var c = $('#colors'); if (c) c.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
      return;
    }
    form.classList.add('hidden');
    $('#doneIcon').textContent = j.waitlist ? '⏳' : '🏅';
    $('#doneTitle').textContent = j.waitlist ? 'Felvettük a várólistára' : 'Megvan, köszönjük!';
    $('#doneText').textContent = j.message || '';
    $('#done').classList.remove('hidden');
    window.scrollTo(0, 0);
  });
})();
