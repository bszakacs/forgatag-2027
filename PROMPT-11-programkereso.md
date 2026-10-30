# Prompt Code-nak — 11. A programkereső

> **Használat:** másold be Code-nak.

---

## Hatókör

A projekt legnagyobb és legösszetettebb publikus darabja:

1. **a programkereső** — szűrők, kereső, találati lista,
2. **a napi nézet** (`/nap/<dátum>`),
3. **a program lapja**,
4. **helyszín- és kategórialapok**,
5. **a térkép** a helyszínekkel.

**NEM ebben a körben:** a kedvencek és a naptár-export. Azok külön, rövidebb körök lesznek.
A kezdőlap sem.

## Olvasd el ELŐSZÖR

- `ALLAPOT.md`, `CLAUDE.md`.
- A 2026-os megvalósítás **csak olvasásra**:
  `..\forgatag-os\frontend\src\components\ProgramFinderView.astro`,
  `..\forgatag-os\frontend\src\lib\programfinder.ts`,
  `..\forgatag-os\frontend\src\lib\finder-map.ts`,
  `..\forgatag-os\frontend\src\programkereso\style.css`,
  `..\forgatag-os\frontend\src\components\DayView.astro`, `DayTabs.astro`.

**A kinézet és az elrendezés átvehető** — azon egy fesztiválnyi csiszolás van. **A működés viszont
alapjaiban más lesz:** 2026-ban a böngésző egy előre legyártott adatcsomagban szűrt, most **valódi
adatbázis-lekérdezés** fut.

---

## 1. A szűrés — ez a kör lényege

**Nap · kategória · helyszín · szabadszavas keresés · „Most zajlik".**

- A szűrők **az adatbázisba** menjenek le, ne egy adatszigetbe.
- **A szűrés jelenjen meg a címsorban** (`/programok?nap=2027-08-27&kategoria=koncert`), hogy
  megosztható, könyvjelzőzhető és a kereső számára is nyitható legyen. 2026-ban ez nem ment.
- **htmx-szel**: csak a találati lista cserélődjön, oldalújratöltés nélkül.

**Hogy azonnalinak érződjön:** a nap-, kategória- és helyszínszűrő **kattintás**, nem gépelés — azt
késleltetés nélkül indítsd, és a kiválasztott gomb kiemelése **azonnal**, kliensoldalon történjen,
míg a lista egy körfordulóval később cserélődik. Csak a szabadszavas keresőnek kell ~250–300 ms
késleltetés.

**Kötelező htmx-beállítások:** `htmx.config.historyCacheSize = 0` · minden szűrő-mezőnek legyen
`id`-je (a htmx `id` szerint tartja meg a fókuszt) · `hx-sync` a keresőmezőre, hogy a gyors gépelés
ne indítson párhuzamos kéréseket.

**És működjön JavaScript nélkül is:** az űrlap essen vissza rendes `GET`-re. A publikus váz körében
ez már bevált, ugyanazt a mintát kövesd.

## 2. A térkép

Beágyazott SVG, kattintható helyszínekkel.

🔴 **A térkép a htmx-csere célterületén KÍVÜL legyen.** Az `innerHTML`-csere megsemmisíti a benne
lévő eseménykezelőket — ez 2026-ban is hibaforrás volt. Ha a térképnek frissülnie kell a szűrésre,
azt külön, célzott frissítéssel oldd meg.

⚠️ **A helyszíneknek ma nincs GPS-koordinátájuk** (46/46 üres a mentés szerint), a térkép-pozíció
viszont 41-nél megvan. A térkép tehát a **térkép-pozíció** alapján működjön, ne GPS alapján.
Az 5 hiányzót **soroljátok fel a jelentésben**.

## 3. A fesztiválnap hajnali 3-ig tart

Egy 23:00–00:30 program **a kezdés napjához tartozik**, nem a következőhöz. A napi nézet, a
szűrés és a „Most zajlik" mind e szerint számoljon. A záró órát a Beállítások adja, nem beégetve.

A **„Mi van ma?"** tartalék célja a **nyitónap**, nem a lista első (előkészítő) napja.

## 4. Három csapda, ami 2026-ban megharapott minket

1. **A logó belóg a fejléc alá** (szándékosan). Ezért ami közvetlenül alatta van, arra rálóg.
   2026-ban emiatt a szűrősáv gombjai nem voltak elérhetők. **A megoldás nem a sáv beljebb tolása
   volt** — az csúnya lett —, hanem hogy **a találatszám sora kerüljön a szűrők fölé**, így a logó
   egy nem kattintható szövegre lóg rá.
2. **Rétegsorrend (z-index):** a ragadó sávok, a legördülők, a fejléc és a mobil menü egymást
   takarják. **Egy skála, egy helyen** — ne foltozd egyedileg.
3. **Kép csak egyszer vágódik:** a programkártya képe azon az arányon készüljön, amin megjelenik.

## 5. Mobil

**A mobil a fő nézet.** 375 px-en a szűrőpanel, a legördülők, a térkép és a lista mind használható
kell legyen, vízszintes görgetés nélkül. A 2026-os mobil-viselkedést vedd át — azon sok csiszolás van.

## 6. Ellenőrzés, mielőtt készre jelented

- **375 px-en** minden: szűrők, legördülők, térkép, lista, program lapja.
- **JavaScript nélkül** is működik a szűrés és a lapozás.
- A szűrt cím **külön megnyitva is ugyanazt adja** (másold ki és nyisd meg új ablakban).
- Egy **23:00–00:30** program a **kezdés napján** jelenik meg.
- A **térkép eseménykezelői túlélik** a lista cseréjét — szűrj, aztán kattints a térképre.
- A szűrősávra **nem lóg rá a logó** úgy, hogy egy gomb elérhetetlen legyen.
- **Románul is** végigpróbálva; ahol nincs fordítás, a magyar látszik.
- Élesben is telepítve és megnézve.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Ez nagy darab.** Ha félúton úgy látod, hogy egy körben nem fér el jól, **állj meg és jelezd** —
  inkább két kör, mint egy félkész.
- **A kedvenceket, a naptár-exportot és a kezdőlapot NE kezdd el.**
- **Néma hiba tilos.**
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
- **A kör végén frissítsd az `ALLAPOT.md`-t.**
