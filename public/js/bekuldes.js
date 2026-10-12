/* ============================================================
   A programbeküldő űrlap böngészőoldali viselkedése — a 2026-os
   submit-api/public/index.html szkriptjének átvétele, változatlan hibaüzenetekkel.
   A szabályok (napok, fotó, szöveghossz, a fesztiválnap záró órája) a szervertől
   jönnek (#cfg) — nincs beégetett érték, így a két oldal nem csúszhat el.
   A végső döntés mindig a szerveré; ez csak a gyors visszajelzés.
   ============================================================ */
(function () {
  const CFG = JSON.parse(document.getElementById('cfg').textContent);
  const $ = (s) => document.querySelector(s);
  const el = (t, c, txt) => { const e = document.createElement(t); if (c) e.className = c; if (txt != null) e.textContent = txt; return e; };
  const TR = CFG.textRules, PR = CFG.photoRules;

  // ---- szöveg-normálizálás (a szerverrel egyező: App\Support\TextCleaner) ----
  const EMOJI = /[\p{Extended_Pictographic}\u{1F1E6}-\u{1F1FF}️]/gu;
  function fixAllCaps(s) {
    const letters = (s.match(/\p{L}/gu) || []).length; if (letters < 4) return s;
    const up = (s.match(/\p{Lu}/gu) || []).length, lo = (s.match(/\p{Ll}/gu) || []).length;
    if (up >= 4 && lo === 0) { const low = s.toLocaleLowerCase('hu'); return low.replace(/(^\s*\p{L})|([.!?]\s+\p{L})/gu, (m) => m.toLocaleUpperCase('hu')); }
    return s;
  }
  function normText(s, multi) {
    let o = (s || '').replace(EMOJI, '');
    o = multi ? o.replace(/\r\n/g, '\n') : o.replace(/[\r\n]+/g, ' ');
    o = o.replace(/[ \t]+/g, ' ').replace(/\s*\n\s*\n\s*/g, '\n\n').replace(/\n{3,}/g, '\n\n').trim();
    return fixAllCaps(o);
  }
  function attachClean(e, multi) { e.addEventListener('blur', () => { e.value = normText(e.value, multi); e.dispatchEvent(new Event('input')); }); }

  // ---- időpont: óra + perc legördülők ----
  function timeVal(h, m) { const hv = $(h).value, mv = $(m).value; return (hv !== '' && mv !== '') ? (hv + ':' + mv) : ''; }
  function timePartial(h, m) { return ($(h).value !== '') !== ($(m).value !== ''); }

  function selectedDays() { return [...document.querySelectorAll('.day-cb:checked')].map((c) => c.value); }
  function updateDaysCount() {
    const n = selectedDays().length;
    $('#daysCount').textContent = n ? (n + ' nap → ' + n + ' időpont jön létre.') : 'Válassz legalább egy napot.';
  }

  // ---- pill-csoportok (napok, belépés-típus) ----
  document.querySelectorAll('.day-cb').forEach((inp) => inp.addEventListener('change', () => {
    inp.closest('.radio').classList.toggle('sel', inp.checked); updateDaysCount();
  }));
  updateDaysCount();
  $('#entryGroup').addEventListener('change', () => {
    document.querySelectorAll('#entryGroup .radio').forEach((r) => r.classList.toggle('sel', r.querySelector('input').checked));
    $('#ticketField').classList.toggle('hidden', $('input[name=entry_type]:checked').value !== 'ticketed');
  });

  // ---- feltételesen kötelező extra mezők (pl. könyvbemutató) ----
  function syncRequiredIf() {
    document.querySelectorAll('[data-required-if]').forEach((w) => {
      const sw = $('#x_' + w.dataset.requiredIf); const on = !!(sw && sw.checked);
      const lab = w.querySelector('label'); if (lab) lab.classList.toggle('req', on);
    });
  }
  document.querySelectorAll('#extras input[type=checkbox]').forEach((c) => c.addEventListener('change', syncRequiredIf));
  syncRequiredIf();

  // ---- szövegtisztítás elhagyáskor ----
  ['#title', '#organizer_names', '#schedule_note', '#ticket_info', '#photographer_credit'].forEach((s) => attachClean($(s), false));
  document.querySelectorAll('[data-clean]').forEach((e) => attachClean(e, e.dataset.clean === 'multi'));
  const d = $('#description'), c = $('#descCount');
  const upd = () => { const n = d.value.length; c.textContent = n + ' / ' + TR.descriptionMin + '–' + TR.descriptionMax; c.classList.toggle('bad', n < TR.descriptionMin || n > TR.descriptionMax); };
  d.addEventListener('input', upd); attachClean(d, true); upd();

  // ---- fotó ----
  let PHOTO = null; // a kiválasztott, előellenőrzött File
  const drop = $('#photoDrop'), input = $('#photo');
  drop.onclick = () => input.click();
  drop.onkeydown = (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } };
  drop.ondragover = (e) => { e.preventDefault(); };
  drop.ondrop = (e) => { e.preventDefault(); if (e.dataTransfer.files[0]) handlePhoto(e.dataTransfer.files[0]); };
  input.onchange = () => { if (input.files[0]) handlePhoto(input.files[0]); };
  function showPhotoErr(m) { const e = $('#photoErr'); e.textContent = m; e.classList.remove('hidden'); drop.classList.remove('ok'); drop.classList.add('bad'); }
  function handlePhoto(file) {
    $('#photoErr').classList.add('hidden'); PHOTO = null; drop.classList.remove('ok', 'bad');
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) { showPhotoErr('Csak JPG, PNG vagy WebP lehet.'); return; }
    if (file.size > PR.maxMB * 1024 * 1024) { showPhotoErr('A fotó legfeljebb ' + PR.maxMB + ' MB lehet.'); return; }
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      if (img.naturalWidth < PR.minWidth || img.naturalHeight < PR.minHeight) {
        showPhotoErr('A fotó legalább ' + PR.minWidth + '×' + PR.minHeight + ' pixel legyen (most ' + img.naturalWidth + '×' + img.naturalHeight + ').'); return;
      }
      PHOTO = file;
      $('#photoPrev').src = url; $('#photoPrev').classList.remove('hidden');
      $('#photoText').textContent = '✓ Kép kiválasztva (' + img.naturalWidth + '×' + img.naturalHeight + '). Kattints a cseréhez.';
      drop.classList.add('ok');
    };
    img.onerror = () => showPhotoErr('A kép nem olvasható.');
    img.src = url;
  }

  // ---- hibák: egyetlen lista a lap tetején ----
  function showErrors(errs) {
    const m = $('#msg'); m.innerHTML = '';
    const box = el('div', 'msg msg-err'); box.appendChild(el('strong', null, 'Kérlek javítsd ezeket:'));
    const ul = el('ul'); errs.forEach((e) => ul.appendChild(el('li', null, e))); box.appendChild(ul);
    m.appendChild(box); window.scrollTo(0, 0);
  }

  // ---- beküldés ----
  const SUBMIT_TXT = $('#submitBtn').textContent;
  $('#form').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const errs = []; $('#msg').innerHTML = '';
    const startTime = timeVal('#start_h', '#start_m'), endTime = timeVal('#end_h', '#end_m'), days = selectedDays();
    if (timePartial('#start_h', '#start_m')) errs.push('A kezdésnél az órát és a percet is válaszd ki.');
    else if (!startTime) errs.push('Add meg a kezdés időpontját (óra és perc).');
    if (timePartial('#end_h', '#end_m')) errs.push('A végénél az órát és a percet is válaszd ki (vagy hagyd mindkettőt üresen).');
    // ÉJFÉL UTÁNI VÉG: a kezdésnél korábbi vég a KÖVETKEZŐ hajnalt jelenti; a határ és a szöveg a szervertől jön.
    if (endTime && startTime) {
      if (endTime === startTime) errs.push('A vége ne ugyanaz legyen, mint a kezdés.');
      else if (endTime < startTime && endTime > CFG.dayCut.time) errs.push(CFG.dayCut.message);
    }
    if (!days.length) errs.push('Válassz legalább egy napot.');
    if ($('#title').value.trim().length < 5) errs.push('A cím legalább 5 karakter legyen.');
    if ($('#organizer_names').value.trim().length < 2) errs.push('Add meg a szervező(k) nevét.');
    if (!$('#venue').value) errs.push('Válassz helyszínt.');
    const dl = $('#description').value.trim().length;
    if (dl < TR.descriptionMin || dl > TR.descriptionMax) errs.push('A leírás ' + TR.descriptionMin + '–' + TR.descriptionMax + ' karakter legyen.');
    if (!PHOTO && !CFG.hasPhoto) errs.push('A fotó feltöltése kötelező (megfelelő felbontással).');
    if (!$('#photographer_credit').value.trim()) errs.push('Add meg a fotós nevét.');
    if (!$('#photo_rights_declared').checked) errs.push('Fogadd el a fotó jogi nyilatkozatát.');
    (CFG.category.extraFields || []).forEach((f) => {
      const sw = f.requiredIf ? $('#x_' + f.requiredIf) : null;
      const need = f.required || (sw && sw.checked);
      const inp = $('#x_' + f.key);
      if (need && f.type !== 'checkbox' && inp && !inp.value.trim()) errs.push('Hiányzó kötelező mező: ' + f.label + '.');
    });
    if (errs.length) { showErrors(errs); return; }

    const fd = new FormData($('#form'));
    fd.set('start_time', startTime); fd.set('end_time', endTime);
    fd.delete('photo'); if (PHOTO) fd.set('photo', PHOTO);
    const btn = $('#submitBtn'); btn.disabled = true; btn.textContent = 'Küldés…';
    const fail = (list) => { showErrors(list); btn.disabled = false; btn.textContent = SUBMIT_TXT; };
    let r;
    try {
      r = await fetch($('#form').action, {
        method: 'POST', body: fd, credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      });
    } catch (e) { fail(['Hálózati hiba. Próbáld újra.']); return; }
    let j = null; try { j = await r.json(); } catch (e) { /* nem JSON válasz — lent kezeljük */ }
    if (r.ok && j && j.ok) {
      $('#form').classList.add('hidden'); $('#done').classList.remove('hidden'); window.scrollTo(0, 0);
      return;
    }
    // NÉMA HIBA TILOS: minden nem-sikeres válasz látható üzenetet ad.
    if (j && Array.isArray(j.errors)) fail(j.errors);
    else if (r.status === 413) fail(['A fotó feltöltése nem sikerült: a fájl túl nagy a szervernek. A program NEM jött létre. Próbáld kisebb fájllal; ha most sem megy, keresd a szervezőt.']);
    else if (r.status === 419 || r.status === 401) fail(['Lejárt a belépésed. Lépj be újra (a beírt adatokat előtte másold ki), majd küldd be újra.']);
    else fail(['A beküldés mentése nem sikerült. Próbáld újra később.']);
  });
})();
