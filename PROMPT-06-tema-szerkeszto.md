# Prompt Code-nak — 06. A `/tema` szerkesztőfelület

> **Használat:** másold be Code-nak.

---

## Hatókör

A téma-alap kész: a publikált témából generálódik minden token, és a CSS lenyomatos fájlnévvel megy ki.
**Most a szerkesztőfelület épül meg**, hogy a téma módosítása ne legyen fejlesztői feladat.

Ez a Filament-panel egy egyedi lapja. **Új menüpont ehhez kivételesen kell** — a `/tema` saját
szerepkörrel rendelkezik (arculat-szerkesztő), és nem a Beállítások közé való, mert aki a színeket
állítja, az ne férjen hozzá a programbeküldés kapcsolójához.

## Olvasd el ELŐSZÖR

- Ebben a mappában: `ALLAPOT.md`, `CLAUDE.md` — **különösen a 2/c pontot** (téma-döntések) és a
  token-szabályt.
- A 2026-os megvalósítás **csak olvasásra**:
  `..\forgatag-os\frontend\src\lib\theme.ts` (az alapértékek és a 13 szekció leírása) és
  `..\forgatag-os\frontend\src\components\ThemeEditorView.astro` (a felület, ~1550 sor).

**A szekciók beosztását, a mezők feliratait és a súgószövegeket vedd át.** Azok kiforrottak, és a
felhasználó ismeri őket.

---

## 1. A szekciók

A 2026-os 13 szekcióból **12 marad**, bal oldali harmonikában, egyszerre egy nyitva:

1. Identitás · 2. Fejléc · 3. Színek — Alapok · 4. Színek — Akcent és gradiens ·
5. Színek — Kategóriacsoportok · 6. Fény (glow) · 7. Tipográfia · 8. Forma és térköz ·
9. Mozgás · 10. Dekor (firkák) · 11. Analitika · 12. Social linkek

**A „Komponensek" szekció KIMARAD** (tulajdonosi döntés). A tartalma már átköltözött: a „MA" jelvény
színe a színek közé, a fejléc üveghatása a fejléc-beállításokhoz. A chat-gomb kapcsolója nem ide jön.

⚠️ **A kategóriacsoportok színeit ellenőrizd:** 2026-ban 7 csoport volt, de a kategóriák azóta
változtak (23 van, öt megszűnt, négy új jött). Ha a csoportosítás nem áll össze, **állj meg és kérdezz**
— ne találj ki csoportokat.

## 2. Élő előnézet — kliensoldalon, szerver-körfordulás nélkül

🔴 **Az előnézet NE menjen a szerverhez.** A csúszka húzásakor vagy a színválasztó mozgatásakor a
böngésző írja rá a token értékét az előnézeti elemre. Szerverhez csak **mentéskor** fordulunk.

**Indok:** egy csúszka másodpercenként tucatnyi értéket ad; ha mindegyik kérés lenne, a felület
akadozna az osztott tárhelyen. 2026-ban is így működött, és jól.

Az előnézeti panelt zárd ki a felület frissítési köréből, hogy ne írja felül.

## 3. Kontraszt-őr

A 2026-os megoldás: a felület **élőben figyeli a szöveg/háttér kontrasztot**, és **nem engedi
publikálni**, ha 4.5:1 alá megy — kivéve, ha a felhasználó írásban felülbírálja.

**Ezt tartsd meg.** Ez védi meg a látogatót attól, hogy egy szép, de olvashatatlan palettától ne
lássa a programokat.

## 4. Publikálás — most egyszerűbb, mint 2026-ban

**A „Élesítés most" és „Élő publikálás" gombok ELTŰNNEK.** Nincs újraépítés: a publikálás annyi,
hogy a téma elmentődik és a CSS újragenerálódik, új lenyomatos fájlnévvel. **Másodpercek, nem percek.**

Tartsd meg viszont:
- **a piszkozat és a publikált állapot különválasztását** (a felhasználó addig állítgathat, amíg nem publikál),
- **az utolsó 10 verzió előzményét**, visszaállítással,
- a publikálás előtti **különbség-előnézetet** (mi változik),
- a **JSON export/import**ot és a **gyári visszaállítást**.

🔴 Az előzmény 2026-ban konkrétan megmentett minket: abból derült ki, hogy az utolsó publikálás egy
gépi próba volt, nem emberi munka.

## 5. Nincs láthatatlan beépített alapérték — `CLAUDE.md` 2/c

A logó, a fejléc-logó, a favicon és a hat firka-hely **valódi feltöltött fájl**, és ez a szerkesztőben
is **látszódjon**. Ha a felhasználó töröl egyet, az ne „essen vissza" egy kódbeli alapértékre
csendben — vagy jelezze, hogy kötelező, vagy adjon vissza egy feltölthető alapkészletet.

## 6. Szerepkör

**Arculat-szerkesztő:** csak a `/tema` laphoz fér hozzá, semmi máshoz. A sor-szintű jognál tanultak
itt is érvényesek: **a közvetlen cím begépelésével se jusson be máshova** — ezt élesben próbáld ki.

## 7. A 2. adósság törlesztése

Itt pótold a **világos/sötét váltót** a beküldő űrlapon (2026-ban volt, most hiányzik).

## 8. Ellenőrzés, mielőtt készre jelented

- **375 px-en** használható-e a szerkesztő (a felhasználó telefonról is megnyithatja).
- A csúszkák húzása **nem akadozik** — nincs szerver-hívás közben.
- A kontraszt-őr tényleg **megakadályozza** a publikálást 4.5:1 alatt.
- Publikálás után a CSS fájlneve **megváltozik**, és a böngésző az újat tölti.
- Az arculat-szerkesztő fiók **nem jut be máshova**, közvetlen címmel sem.
- Az előzményből **vissza lehet állni** egy korábbi verzióra.
- Élesben is telepítve és megnézve.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Néma hiba tilos.** Ha egy téma-mező nem menthető, az látszódjon.
- **A kategóriacsoportoknál állj meg és kérdezz**, ha a 2026-os csoportosítás nem áll össze a mai
  kategóriákkal.
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
- **A kör végén frissítsd az `ALLAPOT.md`-t.**
