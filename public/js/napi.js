/*
 * A NAPI NÉZET szkriptje — a 2026-os components/DailyProgramView.astro <script>-je BETŰRE (CLAUDE.md 2/e):
 * helyszín-választó + szabadszavas szűrés a lapon belül (a szerver a teljes napot adja, JS nélkül is), a darabszám és a
 * „nincs találat” doboz frissítése, és a sorok élő állapota (MOST ZAJLIK / HAMAROSAN · N perc / ELMARAD) + a lap órája.
 * A feliratok a lapból (#dp-labels), mint 2026-ban.
 */
(function () {
  var lab = document.getElementById('dp-labels');
  if (!lab) return;
  var L = JSON.parse(lab.textContent);
  var items = [].slice.call(document.querySelectorAll('.filterable'));
  var venueSel = document.getElementById('venue-filter');
  var searchIn = document.getElementById('prog-search');

  function matches(el) {
    var v = venueSel.value;
    if (v && el.dataset.venue !== v && el.dataset.vparent !== v) return false;
    var q = searchIn.value.trim().toLowerCase();
    if (q && (el.dataset.search || '').indexOf(q) < 0) return false;
    return true;
  }
  function apply() {
    var n = 0;
    items.forEach(function (el) { var ok = matches(el); el.style.display = ok ? '' : 'none'; if (ok) n++; });
    var c = document.getElementById('prog-count'); if (c) c.textContent = String(n);
    var nm = document.getElementById('no-match'); if (nm) nm.hidden = n !== 0;
  }
  venueSel.addEventListener('change', apply);
  searchIn.addEventListener('input', apply);
  document.getElementById('clear-filters').addEventListener('click', function () {
    venueSel.value = ''; searchIn.value = ''; apply();
  });

  // Státusz-pöttyök + óra (idő alapján, élőben)
  function pad(n) { return String(n).padStart(2, '0'); }
  function updateStatus() {
    var now = new Date();
    var clock = document.getElementById('now-clock');
    if (clock) { clock.hidden = false; clock.textContent = '🕐 ' + pad(now.getHours()) + ':' + pad(now.getMinutes()); }
    document.querySelectorAll('.rail-row').forEach(function (row) {
      var badge = row.querySelector('.rail-status');
      if (!badge) return;
      var date = row.dataset.date, start = row.dataset.start, end = row.dataset.end;
      var cls = '', txt = '';
      if (row.dataset.cancelled === '1') { cls = 'st-cancelled'; txt = '✕ ' + L.cancelled; }
      else if (date && start) {
        var s = new Date(date + 'T' + start + ':00');
        var e = end ? new Date(date + 'T' + end + ':00') : new Date(s.getTime() + 3600000);
        if (now >= s && now <= e) { cls = 'st-now'; txt = '● ' + L.now; }
        else { var d = Math.round((s - now) / 60000); if (d > 0 && d <= 60) { cls = 'st-soon'; txt = '◔ ' + L.soon + ' · ' + d + ' ' + L.min; } }
      }
      if (txt) { badge.textContent = txt; badge.className = 'rail-status ' + cls; badge.hidden = false; row.classList.toggle('is-cancelled', cls === 'st-cancelled'); }
      else { badge.hidden = true; }
    });
  }
  updateStatus();
  setInterval(updateStatus, 30000);
})();
