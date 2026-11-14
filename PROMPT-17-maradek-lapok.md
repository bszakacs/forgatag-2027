# Prompt Code-nak — 17. A maradék publikus lapok

> **Használat:** másold be Code-nak.

---

## Hatókör

Hat lap, a 2026-os megfelelőik szerint:

**fesztiválrend · galéria · videó-archívum · támogatók · kézművesek · csapat**

**Ezzel megszűnik az, hogy a menü olyan lapokra visz, amik még nem épültek meg** (ma 404-et adnak).

**Ha hatot egy körben nem lehet jól megcsinálni, vágd ketté** — ezt korábban is megtetted, és bevált.
Javaslat a vágásra: előbb a szöveges lapok (fesztiválrend, csapat, kézművesek), utána a
média-nehezek (galéria, videó-archívum, támogatók).

## Olvasd el ELŐSZÖR

- `ALLAPOT.md`, `CLAUDE.md` — **különösen a 2/e pontot** (a frontend 1:1 jön át).
- A 2026-os megfelelők **csak olvasásra**, köztük:
  `..\forgatag-os\frontend\src\fesztivalrend\`,
  `..\forgatag-os\frontend\src\components\GalleryView.astro`, `VideoArchiveView.astro`,
  `..\forgatag-os\frontend\src\designs\forgatag_2026_tamogatok.html` és
  `..\forgatag-os\frontend\src\tamogatok\style.css`.

🔴 **NE ÉRTELMEZD, EMELD ÁT.** Ez eddig kétszer okozott újramunkát.

---

## 1. A beégetett szövegek pótlása — a 9. adósság

🔴 **Ez a kör legfontosabb feladata, és nem külön munka: a lapokkal együtt történik.**

2026-ban a lapok szövegének nagy része **beégetett magyar** volt — a fesztiválrend lapján a
szövegnek csak a **18%-a** volt szerkeszthető kulcson. **Most minden szöveg kulcsra kerül.**

A Rólunk lapnál ez már megtörtént (43 kulcs, 12 új) — **ugyanazt a mintát kövesd**.

**A próba:** cseréld ki az admin felől az összes szöveget, és nézd meg, maradt-e a lapon olyan szó,
ami nem változott.

## 2. Amit örökölsz — ne építsd újra

- **A dekor-réteg** (felúszás, szórt firkák, kártya-kiemelés) kész. **Kösd be**, ne csinálj
  párhuzamosat.
- **A galéria-néző** a kezdőlap körében már elkészült (év → képek hasábokban → nagy kép → Esc), és
  az **aftermovie-lejátszó** is. **Használd újra.**
- **A médiatár** tartalmazza a képeket: 12 galéria 467 képpel, 112 támogatói logó, a csapat és a
  kézművesek képei — **eredet-jelöléssel (`legacy_id`)**. Ezeken keresztül kösd be őket,
  **ne töltsd fel újra.**

## 3. Lapspecifikus jegyzetek

**Támogatók:** a 2026-os terv szerint, **szintenkénti csempeméretekkel**. A logó-csempék magasságát
2026-ban megemeltük — a végleges értékek a `tamogatok\style.css`-ben vannak, azt vedd át.
⚠️ Egy logó (Balázs Ferenc Önképző Kör) 109 megapixeles volt; ha még mindig az van bent, **jelezd**.

**Csapat:** 56 fő. ⚠️ **A szerepkörök nagy részének nincs román fordítása** (45/55) — ahol nincs,
a magyar látszik, ez a szabály.

**Kézművesek:** 67 fő.

**Videó-archívum:** 26 videó, 3 csoportban.

**Galéria:** 12 év (2013–2025, 2020 kimarad — nem hiba, nem volt fesztivál).

## 4. Ellenőrzés, mielőtt készre jelented

- **A menüből EGYETLEN lap sem ad 404-et.**
- **Minden lap minden szövege szerkeszthető** — cseréld ki őket az adminból, és nézd meg, maradt-e
  változatlan szó.
- A lapok a **2026-os megfelelőik mellé téve ugyanúgy néznek ki.**
- A **dekor-réteg hat** rájuk (a téma kapcsolóival kipróbálva).
- **375 px-en** és **románul** is.
- Élesben is telepítve és megnézve, **mért válaszidővel** (a galéria a legnehezebb lap).

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Semmit ne hagyj ki azzal, hogy „egyszerűbb így"** (2/e). Ha valami nem fér bele, **kérdezz**.
- **Néma hiba tilos.**
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
- **A kör végén frissítsd az `ALLAPOT.md`-t.**
