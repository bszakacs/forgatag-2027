# Prompt Code-nak — 05. A téma-alap

> **Használat:** másold be Code-nak.

---

## Hatókör — a téma mint ADATFORRÁS, nem a szerkesztőfelület

Ebben a körben **nem** épül meg a `/tema` szerkesztő 13 szekciója. Az később jön.
Most az kell, hogy **legyen egy valódi téma-forrás, és abból jöjjön minden szín, betű és forma.**

**Indok:** minden vizuális munka erre épül. Ha a publikus váz korábban készül el, az az ideiglenes
`public/css/tema.css`-re épülne, és utólag kellene kiszedni minden lapból. Ez a kör tehát az
1. számú adósságot is törleszti.

**Következmény, amit mondj el a felhasználónak:** amíg a szerkesztő nincs kész, a téma módosítása
fejlesztői feladat marad. Ez átmeneti.

## Olvasd el ELŐSZÖR

- Ebben a mappában: `CLAUDE.md`, **különösen a 2/c pontot** (téma-döntések) és a token-szabályt.
- `MENTES-LELTAR.md` — a mentés helye és a téma állapota.
- A 2026-os logika **csak olvasásra**: `..\forgatag-os\frontend\src\lib\theme.ts` (az alapértékek és a
  szekciók leírása) és `..\forgatag-os\frontend\src\lib\theme-css.ts` (a CSS-generálás).

---

## 1. A téma átvétele

- **A LEGUTOLSÓ publikált verziót vedd át** (tulajdonosi döntés, 2026-10-21) — igen, azt, amit a
  `schema-bot` publikált 2026-09-02-án. Nem kell verziót válogatni.
- A téma-JSON a mentésben van: `tema/theme-data.json`.
- **A „Komponensek" szekció KIMARAD.** Ami benne volt: a chat-gomb kapcsolója (majd a chatbot
  beállításaihoz kerül, most hagyd ki) és a „MA" jelvény háttérszíne (tedd a színek közé).

## 2. A CSS-generálás — egyetlen forrás

A 2026-os `themeToCss` logikát írd át PHP-ra. Ez állítja elő a token-készletet a publikált témából.

**A generált CSS fájlba íródjon publikáláskor, és a fájlnévben legyen a tartalom lenyomata.**
Így egy témamódosítás **magától érvényteleníti a böngésző gyorsítótárát** — nem kell kézi
verziószám-emelgetés.

🔴 **Ez a projekt egyik visszatérő hibacsaládját szünteti meg:** 2026-ban többször fordult elő, hogy
a fájl neve nem változott, ezért a böngésző a régit tartotta, és „nem látszott" a javítás.

## 3. Nincs láthatatlan beépített alapérték — a `CLAUDE.md` 2/c pontja

Ez a kör legfontosabb viselkedésbeli változása.

- **A logó legyen valódi feltöltött fájl.** Töltsd be a 2026-os logót rendes feltöltésként.
  2026-ban a mező üres volt, miközben az oldalon végig ott volt a logó, mert a kódból jött.
- **A firka-motívumok ugyanígy:** a hat hely ne legyen üres. A beépített motívumokat töltsd be
  valódi fájlként.
- **A favicon átvehető**, de a tárolt értéke ma **teljes címmel a NAS-ra mutat**
  (`https://admin.forgatag.ro/assets/…`). Ez a NAS nélkül eltörik — **saját fájllá kell tenni.**

**A próba egyszerű:** a NAS lekapcsolása után sem törhet el semmi a témában. Ellenőrizd, hogy a
generált CSS és a hivatkozott fájlok között **egyetlen `admin.forgatag.ro` vagy `elo.forgatag.ro`
hivatkozás sincs.**

## 4. Az ideiglenes `tema.css` kivezetése

A `public/css/tema.css` **nyomtalanul tűnjön el**. Minden szín, betű és forma a generált téma-CSS-ből
jöjjön. A beküldő űrlap lapjai is erre álljanak át.

**A token-szabály szerint** oldalra vagy komponensre szűkített `:root`-felülírás tilos. Ha a
beküldő űrlap CSS-ében ilyet találsz, vezesd ki.

## 5. Ellenőrzés, mielőtt készre jelented

- **Nincs egyetlen scope-olt `:root`-felülírás sem** a kódbázisban. Keresd meg és mutasd meg,
  hogy nincs.
- A beküldő űrlap és az admin **ugyanúgy néz ki, mint eddig** — a téma átvétele nem változtathat
  a kinézeten. Ha mégis változik valami, azt mondd el.
- **375 px-en** is ellenőrizve.
- A generált CSS neve a tartalom változásakor megváltozik.
- **Nincs NAS-ra mutató hivatkozás.**
- Élesben is telepítve és megnézve.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **A szerkesztőfelületet NE kezdd el.** Ha félúton úgy látod, hogy enélkül nem megy, állj meg és jelezd.
- **Néma hiba tilos.** Ha egy téma-mező nem jött át, azt mondd ki.
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
