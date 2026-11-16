# Prompt Code-nak — 18. A térkép-szerkesztő

> **Használat:** másold be Code-nak.

---

## Hatókör

A `/terkep-szerkeszto` újraépítése: a helyszínek elhelyezése a rajzolt térképen, fogd-és-vidd
módon. **Rövid kör**, és független a többitől.

**Miért most:** két helyszín ma beállítatlan, és a helyszínek évről évre változnak — enélkül minden
új helyszín elhelyezése fejlesztői feladat maradna.

## Olvasd el ELŐSZÖR

- `ALLAPOT.md`, `CLAUDE.md` — **különösen a 2/e pontot** (a frontend 1:1 jön át).
- A 2026-os megvalósítás **csak olvasásra**:
  `..\forgatag-os\frontend\src\components\MapEditorView.astro`,
  `..\forgatag-os\frontend\src\lib\finder-map.ts`.

🔴 **NE ÉRTELMEZD, EMELD ÁT.**

---

## 1. Mit kell tudnia

A 2026-os szerkesztő szerint: a helyszínt **ráhúzod a rajzolt térképre**, és az elmentett pozíció
jelenik meg a programkeresőben, a helyszínlapon és a `/terkep` lapon.

**A tárolt adat** (a mentésből már átjött, 41 helyszínnél):
`map_zone` · `map_x` · `map_y` · `map_no` · `map_edge` · `map_bearing`.

**A SZÉL-JELÖLÉST is tudnia kell:** ami kiesik a térkép látható területéről, az a **szélére kerül egy
irányt mutató jelöléssel** (`map_edge`, `map_bearing`). Így van kezelve az Állatkert, a
Marosvásárhelyi vikendtelep és a Fociakadémia.

## 2. A húzás megvalósítása

**Ne használj fogd-és-vidd bővítményt.** A kész bővítmények listát rendeznek át, 2D-koordinátát nem
adnak — nem illenek ide.

A helyes megoldás néhány tucat sor: **Pointer Events** (egyszerre kezeli az egeret és az érintést),
és a képernyő-koordináta átváltása a térkép saját koordinátájára a `getScreenCTM().inverse()`
segítségével.

## 3. Ami a 2026-os rendszerben hibázott — ne hozd át

🔴 **A 2026-os térkép-szerkesztő 401-es hibával elszállt**, mert a lap az egyik címen futott, és egy
másikra próbált írni. **Ez a hibafajta nálunk szerkezetileg nem létezhet** — egy alkalmazás, egy
belépés. Ha mégis ilyesmibe futsz, az azt jelenti, hogy valamit rosszul kötöttél be: **állj meg.**

## 4. Hol legyen

A Filament-panel egyedi lapja, a **térképhez tartozó menücsoportban** vagy a helyszínek mellett —
**ne vegyél fel új főmenüpontot** (`CLAUDE.md` 2/b). Ha nem egyértelmű, hova való, **kérdezz.**

**Jogosultság:** aki a helyszíneket szerkesztheti. **Az arculat-szerkesztő NE férjen hozzá.**
Ezt élesben próbáld ki, a közvetlen címet is begépelve.

## 5. Az első dolog, amit vele el kell végezni

**Két helyszínnek nincs pozíciója**, pedig a város belsejében van:
**Vár, Várkert** és **Kultúrpalota, nagyterem**. A szerkesztő elkészülte után **helyezd el őket** —
vagy ha a pontos helyüket nem tudod, **kérdezd meg a tulajdonost**, és írd le, hol kell húznia.

## 6. Ellenőrzés, mielőtt készre jelented

- Egy helyszín **áthúzása megmarad** mentés után, és **megjelenik** a programkeresőben, a
  helyszínlapon és a `/terkep` lapon.
- A **szél-jelölés** működik: a térképen kívülre húzott helyszín a szélen, irányjelvénnyel jelenik meg.
- **Érintéssel is működik** (tableten vagy telefonon) — nem csak egérrel.
- **Az arculat-szerkesztő nem fér hozzá**, közvetlen címmel sem.
- A két beállítatlan helyszín **a helyére került**.
- Élesben is telepítve és megnézve.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Néma hiba tilos.** Ha egy pozíció nem mentődik el, az látszódjon.
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
- **A kör végén frissítsd az `ALLAPOT.md`-t.**
