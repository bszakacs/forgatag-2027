/* ============================================================
   Az önkéntes jelentkező űrlap (PROMPT-20) — a 2026-os volunteer-api/public/index.html szkriptjének átirata.
   A napokat, területeket, étrendet a szerver rajzolja (a Beállításokból). Itt: a kijelölések, a „volt már önkéntes?”
   és az „Egyéb” mező, a korhatár alatti dokumentum-kártya, a 2026-os böngészőoldali ellenőrzés (ugyanazok a szövegek),
   és a beküldés. A dokumentumok (2026: base64 a JSON-ban) most rendes fájlfeltöltésként mennek — a szerver a TARTALMUK
   alapján ellenőrzi őket, és a zárt tárba teszi.
   ============================================================ */
(function () {
  'use strict';
  var $ = function (s) { return document.querySelector(s); };
  var el = function (t, c, txt) { var e = document.createElement(t); if (c) e.className = c; if (txt != null) e.textContent = txt; return e; };
  var form = $('#form');
  if (!form) return;
  var KORHATAR = Number(form.dataset.korhatar) || 16;
  var MAX_MB = Number(form.dataset.maxMb) || 10;
  var TIPUSOK = ['application/pdf', 'image/jpeg', 'image/png'];
  var FILES = { parental: null, motivation: null };

  // jelölőnégyzet-kártyák
  document.querySelectorAll('.checks .chk input').forEach(function (inp) {
    inp.addEventListener('change', function () { inp.closest('.chk').classList.toggle('sel', inp.checked); });
  });
  function selected(kind) { return Array.prototype.map.call(document.querySelectorAll('.cb-' + kind + ':checked'), function (c) { return c.value; }); }

  // rádió-pill kijelölés
  document.querySelectorAll('.radio-group').forEach(function (g) {
    var upd = function () { g.querySelectorAll('.radio').forEach(function (r) { r.classList.toggle('sel', r.querySelector('input').checked); }); };
    g.addEventListener('change', upd); upd();
  });
  // volt-e önkéntes → előzmény mező
  $('#wasVolGroup').addEventListener('change', function () {
    $('#prevField').classList.toggle('hidden', document.querySelector('input[name=was_volunteer]:checked').value !== 'yes');
  });
  // korhatár alatti fájl-logika
  function isMinor() { var a = Number($('#age').value); return Number.isFinite(a) && a > 0 && a < KORHATAR; }
  function syncMinor() { $('#minorCard').classList.toggle('hidden', !isMinor()); }
  $('#age').addEventListener('input', syncMinor);
  syncMinor();
  // „Egyéb" étkezési érzékenység → szabadszöveges mező
  $('#dietary_other_cb').addEventListener('change', function () {
    var on = $('#dietary_other_cb').checked;
    $('#dietaryOtherChk').classList.toggle('sel', on);
    $('#dietary_other').classList.toggle('hidden', !on);
    if (!on) $('#dietary_other').value = '';
  });

  function wireFile(kind) {
    var box = $('#' + kind + 'Box'), input = $('#' + kind + '_file');
    box.onclick = function () { input.click(); };
    box.onkeydown = function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } };
    box.ondragover = function (e) { e.preventDefault(); box.classList.add('drag'); };
    box.ondragleave = function () { box.classList.remove('drag'); };
    box.ondrop = function (e) { e.preventDefault(); box.classList.remove('drag'); if (e.dataTransfer.files[0]) processFile(kind, e.dataTransfer.files[0]); };
    input.onchange = function () { if (input.files[0]) processFile(kind, input.files[0]); };
  }
  function processFile(kind, f) {
    var box = $('#' + kind + 'Box'), text = $('#' + kind + 'Text');
    box.classList.remove('bad', 'ok');
    if (TIPUSOK.indexOf(f.type) < 0) { box.classList.add('bad'); text.textContent = 'Csak PDF, JPG vagy PNG lehet.'; FILES[kind] = null; return; }
    if (f.size > MAX_MB * 1024 * 1024) { box.classList.add('bad'); text.textContent = 'Legfeljebb ' + MAX_MB + ' MB lehet.'; FILES[kind] = null; return; }
    FILES[kind] = f;
    box.classList.add('ok');
    text.textContent = '✓ ' + f.name + ' — kattints vagy húzz ide másikat a cseréhez';
  }
  wireFile('parental');
  wireFile('motivation');

  function showErrors(errs) {
    var m = $('#msg'); m.innerHTML = '';
    var box = el('div', 'msg msg-err'); box.appendChild(el('strong', null, 'Kérlek javítsd ezeket:'));
    var ul = el('ul'); errs.forEach(function (e) { ul.appendChild(el('li', null, e)); }); box.appendChild(ul);
    m.appendChild(box); window.scrollTo(0, 0);
  }

  var btn = $('#submitBtn');
  function gombVissza() { btn.disabled = false; btn.textContent = 'Jelentkezés elküldése'; }

  form.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    var errs = []; $('#msg').innerHTML = '';
    var age = Number($('#age').value);
    if ($('#full_name').value.trim().length < 2) errs.push('Add meg a teljes neved.');
    if (!Number.isFinite(age) || age <= 0 || age > 120) errs.push('Add meg az életkorod (szám).');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($('#email').value.trim())) errs.push('Adj meg érvényes e-mail címet.');
    if ($('#motivation').value.trim().length < 5) errs.push('Írd le röviden, miért szeretnél önkéntes lenni.');
    if (selected('days').length === 0) errs.push('Válassz legalább egy napot.');
    if (selected('areas').length === 0) errs.push('Válassz legalább egy területet.');
    if ($('#dietary_other_cb').checked && !$('#dietary_other').value.trim()) errs.push('Írd le az „Egyéb" étkezési érzékenységet, vagy vedd ki a pipát.');
    if ($('#health_notes').value.trim().length === 0) errs.push('Írd be az egészségügyi tudnivalókat (ha nincs, írd: nincs).');
    if ($('#emergency_name').value.trim().length === 0) errs.push('Add meg a vészhelyzeti kontakt nevét.');
    if ($('#emergency_phone').value.trim().length === 0) errs.push('Add meg a vészhelyzeti kontakt telefonszámát.');
    if (!$('#privacy_consent').checked) errs.push('Fogadd el az adatkezelési hozzájárulást.');
    if (isMinor()) {
      if (!FILES.parental) errs.push(KORHATAR + ' alatt a szülői beleegyezés feltöltése kötelező.');
      if (!FILES.motivation) errs.push(KORHATAR + ' alatt a motivációs levél feltöltése kötelező.');
    }
    if (errs.length) { showErrors(errs); return; }

    var fd = new FormData(form);
    // A dokumentumok csak a korhatár alatt mennek (2026-ban is); a fájlmezőknek nincs nevük, ezért kézzel kerülnek bele.
    if (isMinor()) {
      fd.append('parental_file', FILES.parental);
      fd.append('motivation_file', FILES.motivation);
    }
    btn.disabled = true; btn.textContent = 'Küldés…';
    var r, j = null;
    try {
      r = await fetch(form.action, { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      try { j = await r.json(); } catch (e) { j = null; }
    } catch (e) { showErrors(['Hálózati hiba. Próbáld újra.']); gombVissza(); return; }

    if (!r.ok || !j || !j.ok) {
      var uzenet = (j && j.errors) ? j.errors
        : r.status === 413 ? ['A feltöltött dokumentumok együtt túl nagyok. Próbáld kisebb fájllal (pl. a fotót kisebb felbontásban).']
        : r.status === 419 ? ['A lap túl régóta volt nyitva, ezért a biztonsági kulcsa lejárt. Töltsd újra a lapot (a beírt adatokat előbb érdemes kimásolni).']
        : ['A beküldés nem sikerült.'];
      showErrors(uzenet); gombVissza(); return;
    }
    form.classList.add('hidden'); $('#done').classList.remove('hidden'); window.scrollTo(0, 0);
  });
})();
