/*
 * PROGRAMKERESŐ — a vékony kliens-réteg (Alpine). A szűrés a szerveren fut (App\Support\ProgramKereso); ez a fájl a
 * 2026-os KEZELÉST adja vissza (`../forgatag-os/frontend/src/programkereso/script.js`, CLAUDE.md 2/e), és nélküle is
 * minden működik (az űrlap rendes GET, a címkék és a kártyák rendes linkek):
 *   · a kiválasztott nap (vagy a „Most zajlik”) újbóli megnyomása kikapcsolja → minden nap;
 *   · a „Most zajlik” és a napszak kizárja egymást (a kettő együtt ellentmondó szűrés lenne);
 *   · a keresőbe írás „minden nap” állapotra vált; a kereső kiürítése vissza a kezdőnapra;
 *   · az aktív szűrő-címke törlése az ŰRLAPOT állítja (az az igazság), és onnan indul a kérés;
 *   · a mobil szűrőpanel nyitása/zárása, a helyszín- és kategórialista böngészése ékezet-függetlenül;
 *   · a felugró ablak „Megosztás” gombja.
 * A szkript az Alpine ELŐTT fut (a lap alján, nem halasztva), ezért az `alpine:init` eseményre regisztrál.
 */
(function () {
  var norm = function (s) {
    return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
  };
  var urlap = function () { return document.getElementById('pk-urlap'); };
  var napRadio = function (ertek) {
    var u = urlap();
    return u ? u.querySelector('input[name="nap"][value="' + ertek + '"]') : null;
  };
  /* A változás jelzése az űrlapnak: a htmx a `change` eseményre kér (egy kérés, a teljes űrlappal). */
  var kerj = function (el) { el.dispatchEvent(new Event('change', { bubbles: true })); };

  /* MEGOSZTÁS (2026): a rendszer megosztó ablaka, ha van; különben a cím a vágólapra. */
  window.pkMegoszt = function (cim, url) {
    if (navigator.share) {
      return navigator.share({ title: cim, url: url }).then(function () { return 'megosztva'; }, function () { return 'megse'; });
    }
    return navigator.clipboard.writeText(url).then(function () { return 'masolva'; }, function () { return 'hiba'; });
  };

  document.addEventListener('alpine:init', function () {
    window.Alpine.data('pkKereso', function () {
      return {
        lap: false,
        szo: { hely: '', kat: '' },

        illik: function (keresett, felirat, be) {
          var k = norm(keresett);
          /* a KIJELÖLT tétel akkor is a listában marad, ha a keresőszóra nem illik (2026) */
          return be || k === '' || norm(felirat).indexOf(k) !== -1;
        },

        /* A már kiválasztott nap / „Most” újbóli megnyomása: minden nap (2026: S.day = null / S.now = false). */
        napKattint: function (e, label) {
          /* A címke kattintása után a böngésző a rádióra is „kattint” (az már bejelölt állapotban fut fel ide) —
             azt hagyni kell, különben a friss kijelölést vonnánk vissza. Csak a gomb felületére kattintás számít. */
          if (e.target.tagName === 'INPUT') return;
          var r = label.querySelector('input');
          if (!r.checked) return;
          e.preventDefault();
          var mind = napRadio('mind');
          mind.checked = true;
          kerj(mind);
        },

        /* „Most zajlik” be → a napszakok ki. */
        mostValt: function () {
          urlap().querySelectorAll('input[name="napszak"]').forEach(function (i) { i.checked = false; });
        },

        /* Napszak be → a „Most zajlik” ki (minden nap). */
        napszakValt: function (el) {
          var most = napRadio('most');
          if (el.checked && most && most.checked) napRadio('mind').checked = true;
        },

        /* Gépelés → minden nap; a kereső kiürítése → vissza a kezdőnapra (2026). A kérést a mező maga indítja. */
        keresesValt: function (el) {
          var mind = napRadio('mind');
          if (el.value.trim() !== '') {
            if (!mind.checked) mind.checked = true;
          } else {
            var alap = napRadio(urlap().dataset.alapnap);
            if (alap) alap.checked = true;
          }
        },

        torol: function (el) {
          var u = urlap();
          var mezo = el.dataset.pkTorol;
          if (!u) { window.location.href = el.href; return; }
          var q = document.getElementById('pk-q');
          if (mezo === '*' || mezo === 'q') q.value = '';
          if (mezo === '*') {
            u.querySelectorAll('input[type=checkbox][data-pk-szuro]').forEach(function (i) { i.checked = false; });
          } else if (mezo === 'helyszin' || mezo === 'kategoria' || mezo === 'napszak') {
            u.querySelectorAll('input[name="' + mezo + '"]').forEach(function (i) {
              if (i.value === el.dataset.ertek) i.checked = false;
            });
          }
          /* MINDENT töröl, a kiválasztott napot is (2026): nap nélkül a lista minden programot mutat. */
          var mind = napRadio('mind');
          if (mezo === '*' || mezo === 'nap') mind.checked = true;
          kerj(mind);
        },
      };
    });
  });
})();
