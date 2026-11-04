/*
 * A PROGRAM LAPJA — a 2026-os ProgramDetailView megosztás-szkriptje BETŰRE: a megosztott link a programkeresőbe, a felugró
 * ablakra visz (a „Vissza a programkeresőhöz” címe), nem erre a lapra. Telefonon a rendszer megosztója, máshol vágólap.
 * (A „Naptárba” a naptár-körben kapcsol be.)
 */
(function () {
  var btn = document.getElementById('pd-share');
  if (!btn) return;
  btn.addEventListener('click', async function () {
    var vissza = document.querySelector('.article-back');
    var url = vissza ? new URL(vissza.getAttribute('href'), location.href).href : location.href;
    var title = document.title;
    if (navigator.share) { try { await navigator.share({ title: title, url: url }); } catch (e) { } }
    else { try { await navigator.clipboard.writeText(url); btn.textContent = '✓ ' + (btn.dataset.copied || ''); setTimeout(function () { location.reload(); }, 1400); } catch (e) { } }
  });
})();
