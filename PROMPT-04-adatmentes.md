# Prompt Code-nak — 04. A 2026-os adatok kimentése

> **Használat:** másold be Code-nak.

---

## Miért ez a kör, és miért most

A 2027-es rendszer alapanyaga a 2026-os Directus-adatbázisban és fájltárában van: a téma, a lapok
tartalma, a hírek, a helyszínek, a kategóriák, a menük, a támogatók, a galériák, a képek.
**Ez a NAS-on él, és a NAS mostantól csak fejlesztőkörnyezet** — nem üzemeltetjük tovább úgy,
ahogy a fesztivál alatt.

🔴 **Ezért ezt most kell kimenteni, egyszer, mindent** — nem hónapok múlva, amikor szükség lesz rá,
és kiderül, hogy a NAS épp nem elérhető, lejárt egy tanúsítvány, vagy elment az áram.

**Ez a kör NEM importálás.** Semmit nem töltesz be a 2027-es rendszerbe. A cél: az alapanyag
legyen biztonságban és legyen róla leltár.

## Amit ki kell menteni

**1. A teljes adatbázis.** A Directus mögötti PostgreSQL mentése. Ebben van minden tartalom és
a téma-JSON is.

**2. A feltöltött fájlok.** A Directus kép- és fájltára, **eredeti méretben** — nem a méretre vágott
változatok.

**3. A téma-JSON külön is**, önálló fájlként. Kicsi, és a következő körnek pont ez kell.

## Amit NE keverj össze vele

A statikus HTML-kimenet (az, ami ma a `forgatag.ro`-n fut) **nem alapanyag**: abban a cím, a dátum,
a szöveg és a kép már egyetlen lappá van összegyúrva, a téma pedig csak generált CSS-ként van benne.
Az a kimenet, ez a bemenet. **A statikus kimenetre továbbra is szükség van**, de másra: az lesz a
2026-os archívum. Most nem azzal foglalkozunk.

## Hogyan

A hozzáférés módját te döntöd el, de **két ismert akadály van**, ezekkel számolj:

- **A tulajdonos Directus-fiókján kétfaktoros belépés van.** 2026-ban emiatt nem futott le több
  automatizálás. Ha API-n keresztül mennél, ehhez technikai token kell — ezt a felhasználónak kell
  létrehoznia, mondd meg neki pontosan, hol.
- **A NAS-on parancsot futtatni csak a DSM felületén át lehet**, a felhasználó kattintásával.
  Jelszót nem gépelsz.

**Válaszd a legkevesebb mozgó alkatrészt igénylő utat**, és írd le a felhasználónak kattintásszinten,
mit csináljon.

## Hova kerüljön

- **NE a git-tárolóba.** Az adatbázis-mentés és a képanyag túl nagy hozzá, és személyes adatokat is
  tartalmaz (beküldők nevei, e-mail-címei, önkéntes-jelentkezések).
- Egy külön mappába a gépen, a `forgatag-2027` mappán kívül.
- **A helyét írd be a `CLAUDE.md`-be**, hogy később bárki megtalálja.
- Mondd meg a felhasználónak, hogy **készítsen róla egy második másolatot** máshova. Egyetlen
  példányban lévő mentés nem mentés.

## Leltár — ez a kör igazi haszna

A mentés után készíts egy **rövid, magyar nyelvű leltárt** `MENTES-LELTAR.md` néven a
`forgatag-2027` mappába:

- melyik gyűjteményben hány sor van (hírek, lapok, helyszínek, kategóriák, menük, támogatók,
  galériák, programok, fordítások),
- hány fájl és mekkora összesen,
- **mi az, ami hiányzik vagy gyanús** — üres gyűjtemény, hivatkozott de nem létező kép, olyan sor,
  aminek nincs román párja.

Ez a leltár mondja meg a következő körnek, hogy mivel dolgozunk. **Ne szépítsd:** ha valami
hiányosnak látszik, azt írd le.

## Amit NE csinálj ebben a körben

- Ne importálj semmit a 2027-es rendszerbe.
- Ne alakíts át adatot.
- Ne telepíts semmit a tárhelyre.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Néma hiba tilos.** Ha egy gyűjtemény nem jött át, azt mondd ki — ne jelents sikert azért, mert
  a parancs hibaüzenet nélkül lefutott. 2026-ban kétszer fordult elő pont ez.
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
