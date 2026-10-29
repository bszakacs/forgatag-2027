# Prompt Code-nak — 09. A publikus váz

> **Használat:** másold be Code-nak.

---

## Hatókör és a kör célja

**Ez bizonyítás, nem tartalom-gyártás.** Ma semmi nem igazolja, hogy a Blade + htmx + az áthozott
CSS együtt működik. Ez a kör ezt méri meg egy kis felületen, mielőtt a nagy darabok ráépülnek.

**Amit megépítünk:**
1. a keret (`<head>`, fejléc, lábléc, menü) — újrafelhasználható részekre bontva,
2. **egy egyszerű tartalmi lap** (Rólunk),
3. **a hírek listája és egy hír lapja**,
4. **egy htmx-próba** a hírlista lapozásán,
5. a **„Hamarosan" lap**, a korai élesítéshez.

🔴 **A kezdőlapot NE most csináld.** A 2026-os kezdőlapon kártya-felúszás, firka-dekor és csúszka
van — ha azzal kezdünk, összekeveredik két kérdés: „működik-e a technológia" és „reprodukálható-e a
bonyolult kezdőlap". Előbb az elsőre kell válasz.

## Olvasd el ELŐSZÖR

- `ALLAPOT.md`, `CLAUDE.md` — **a token-szabályt és a Filament-CSS csapdáját** (2/c alatt).
- A 2026-os megfelelők **csak olvasásra**: `..\forgatag-os\frontend\src\layouts\`,
  `..\forgatag-os\frontend\src\rolunk\`, `..\forgatag-os\frontend\src\styles\`.

---

## 1. A szerkezet — emberi szemmel átlátható

A tulajdonos kifejezetten ezt kérte:

```
resources/views/
  layouts/app.blade.php     ← a keret: <head>, meta, CSS
  partials/header.blade.php ← fejléc, EGY helyen
  partials/footer.blade.php ← lábléc, EGY helyen
  partials/nav.blade.php    ← menü
  hirek/index.blade.php · hirek/show.blade.php · oldal/show.blade.php
```

**Egy fejléc-fájl, egy lábléc-fájl, egy keret.** Ne szóródjon szét, és ne legyen beégetve.

## 2. A menü az adatbázisból

A fejléc menüje a most elkészült menü-gyűjteményből jöjjön, a hierarchiával és a **nyelvenkénti
elrejtéssel** együtt. Nem beégetve.

## 3. Két nyelv

HU az alap, a román a `/ro/` előtag alatt — ahogy 2026-ban. A nyelvváltó a fejlécben, a téma
kapcsolója szerint ki/be kapcsolható.

**Ahol nincs román szöveg, a magyar látszódjon** — ez a `CLAUDE.md` szabálya. Üres lap vagy
„hiányzó fordítás" felirat nem elfogadható.

## 4. A htmx-próba — kicsiben, de valódi

A hírlista lapozása menjen **htmx-szel**: valódi adatbázis-lekérdezés, csak a lista cserélődjön,
oldalújratöltés nélkül, és **a lapszám jelenjen meg a címsorban** (megosztható, könyvjelzőzhető).

**Ne gépelj hozzá JavaScriptet.** A htmx attribútumként kerüljön a HTML-be — ez az oka, hogy ezt
választottuk.

**Ellenőrizd, hogy JavaScript nélkül is működik:** a lapozás essen vissza rendes
oldalbetöltésre. Ami a címsorba kerül, azt a kereső is meg kell tudja nyitni.

## 5. A CSS — a token-szabály éles próbája

- Minden szín, betű és forma a **generált téma-CSS-ből** jön. A 2026-os lapok CSS-ét át lehet
  hozni, de **oldalra szűkített `:root`-felülírás nem maradhat benne.**
- 🔴 **A Filament témáját SOHA ne linkeld a publikus lapra.** Behúzza a Tailwind preflightját, ami
  globálisan nullázza a margókat és a címsorstílusokat.
- A **Tingler Print csak 400-as vastagságban létezik.** Ahol címbetű van, 400 legyen, és
  `font-synthesis: none` — különben Androidon körvonalas lesz.

## 6. A szerkeszthető szövegek

A Rólunk lap szövegei a most elkészült „szerkeszthető szövegek" kulcsokból jöjjenek.

⚠️ **Itt derül ki a 2026-os hiányosság:** a lapokon a szöveg nagy része beégetett magyar volt.
**Most pótold** — ami a lapon szöveg, az legyen szerkeszthető, ne beégetve. Ez az a pillanat,
amiről az `ALLAPOT.md` szól.

## 7. A „Hamarosan" lap

Egy önálló, igényes lap a korai élesítéshez: a fesztivál neve, a 2027-es dátum, a logó, és egy
rövid szöveg — **szerkeszthetően**, nem beégetve. Kétnyelvű.

**Kapcsolóval**: ha a „Hamarosan" mód be van kapcsolva, a publikus oldal ezt mutatja; az admin és a
beküldő űrlap **változatlanul elérhető marad**. A kapcsoló a Beállítások lapra kerül, új blokkba.

## 8. Ellenőrzés, mielőtt készre jelented

- **375 px-en** minden lap. A mobil a fő nézet.
- A lapozás **JavaScript nélkül is működik** (próbáld ki kikapcsolt JS-sel).
- A lapozás után a **címsor tartalmazza a lapszámot**, és az a cím külön megnyitva is működik.
- A román oldalon **nincs üres hely** ott, ahol nincs fordítás — a magyar látszik.
- **A Filament CSS-e nincs a publikus lapon** — nézd meg a forrást, ne feltételezd.
- A „Hamarosan" mód **nem zárja ki** az admint és a beküldő űrlapot.
- Élesben is telepítve és megnézve.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **A kezdőlapot és a programkeresőt NE kezdd el.** Ha félúton úgy látod, hogy enélkül nem megy,
  állj meg és jelezd.
- **Néma hiba tilos.**
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
- **A kör végén frissítsd az `ALLAPOT.md`-t.**
