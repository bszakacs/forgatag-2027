# Prompt Code-nak — 07. A médiatár

> **Használat:** másold be Code-nak.

---

## Hatókör

A médiatár megépítése és a **2026-os képanyag behozatala**. Ez előzi meg a tartalmi gyűjteményeket
(hírek, támogatók, galéria), mert mindegyikhez kép tartozik.

**A döntések már megvannak:** `CLAUDE.md` **2/d pont**. Azt ne nyisd újra, csak hajtsd végre.

## Olvasd el ELŐSZÖR

- `ALLAPOT.md`, `CLAUDE.md` (**2/d pont**).
- `MENTES-LELTAR.md` — mi van a mentésben, és mi gyanús benne.

---

## 1. Az alap

A **Curator** médiakezelő telepítése és regisztrálása a panelen.

⚠️ **A Curator nem fér össze a Spatie Media Library-vel** — a kettőt ne keverd.
⚠️ **A Glide-token minden környezetben beállítandó** (a dokumentáció külön kiemeli).
A szerveri értéket a felhasználó írja be a `.env`-be; te ne gépelj titkot commitolható fájlba.

## 2. A saját mezőink

A Curator saját tábláját bővítjük: **jogtulajdonos · licenc · fotós-kredit · gyűjtemény**.

**A licenc KÉT külön eset** (`CLAUDE.md` 2/d):

| Honnan jön a kép | Licenc |
|---|---|
| Publikus beküldő űrlapról | **kötelező.** „Ismeretlen" jogállású kép **nem publikálható** |
| Adminban feltöltve | **nem kötelező**, saját forrás az alapérték, ne álljon az útba |

## 3. Gyűjtemények — „mappák", de csak szűrőként

**A gyűjtemény abból jön, HONNAN töltötték fel.** Ha a kép egy hír vagy egy program
szerkesztőlapjáról megy fel, az **magától a megfelelő gyűjteménybe kerül** — a felhasználónak nem
kell semmit csinálnia.

- A médiatárba közvetlenül feltöltött kép: **„Rendezetlen"**.
- Áthelyezés: **többszörös kijelölés + „Áthelyezés gyűjteménybe"**. Fogd-és-vidd csak akkor, ha ez
  kevésnek bizonyul.
- A baloldali gyűjtemény-lista adja a mappa-érzetet.
- **Egy kép több gyűjteménybe is tartozhat.**

## 4. A 2026-os képanyag behozatala

A mentésből: **1091 fájl, 657 MB**. A kapcsolatokat is hozd át, ne csak a fájlokat:

| Mi | Hova |
|---|---|
| 467 galériakép | a saját galériájához |
| 112 támogatói logó | a támogatóhoz |
| 241 programborító | a médiatár **„Programok 2026”** gyűjteményébe, eredet-jelöléssel (melyik 2026-os programhoz tartozott) — **NEM** programhoz kötve |
| a `media_asset` 289 sorának adatai (jogtulajdonos, fotós, licenc) | a megfelelő képre |

> **Javítás (tulajdonosi döntés, 2026-10-22):** az eredeti szöveg szerint a borító „a programhoz” került
> volna — ez ellentmondott a `CLAUDE.md` 7. pontjának (**a 2026-os programok nem jönnek át**). A helyes:
> a 241 borító a „Programok 2026” gyűjteménybe kerül, a licenc-, jogtulajdonos- és fotósadataival.
> **Indok:** külsős fotók, „minden jog fenntartva” licenccel; a jogot a tavalyi programra adták. Megőrizzük
> (egy visszatérő programgazda kérheti ugyanazt a képet), de a külön gyűjtemény látszóvá teszi, hogy tavalyi
> anyag, és **2027-es programhoz nem rendelhető** (a rendszer elutasítja).

**A leltár szerint ezekre figyelj:**

- **52 fájlra semmi nem hivatkozik.** Ne dobd el őket — tedd „Rendezetlen" gyűjteménybe, és
  **jelezd a számukat**, hogy a felhasználó eldönthesse.
- **2 fájl típusa hibás** (`application/octet-stream`, a nevük „… másolata"): képek, rossz típussal.
  Javítsd a típust.
- **A téma fájljai** (logó, favicon, firkák) ma külön mappában vannak — ez a 6. adósság.
  **Hozd be őket egy „Arculat" gyűjteménybe**, és ezzel az adósság törlesztve.

## 5. Tárolás — szándékosan kétfelé

- **A médiatár nyilvános mappában.** ⚠️ A cPanel Fájlkezelő **nem tud szimbolikus linket
  létrehozni** — vagy a telepítési lépés hozza létre, vagy a fájlok eleve a nyilvános mappába
  kerülnek. Az utóbbi az egyszerűbb.
- **A még jóvá nem hagyott beküldött fotó a ZÁRT tárban marad.** **Jóváhagyáskor kerül át** a
  médiatárba, a „Programok" gyűjteménybe, a licenc- és fotós-adataival együtt.
  Így egy visszautasított program fotója sosem lesz nyilvánosan elérhető.

## 6. Ellenőrzés, mielőtt készre jelented

- **375 px-en** is használható a médiatár és a képválasztó.
- Egy hír szerkesztőlapjáról feltöltött kép **magától a „Hírek" gyűjteménybe** kerül.
- A beküldő űrlapon **a licenc nélküli kép nem publikálható**; az adminban viszont nem akadályoz.
- Egy program jóváhagyásakor a fotó **átkerül** a zárt tárból a médiatárba.
- **A behozott képek tényleg megjelennek** a saját galériájuknál, támogatójuknál, programjuknál —
  nem elég, hogy a fájl felkerült.
- Élesben is telepítve és megnézve.

---

## A felhasználónak szóló teendő, amit külön írj meg

🔴 **A tárhely lemezkvótáját meg kell nézni, mielőtt a 657 MB képanyag felmegy.**
Mondd meg, hol látja a cPanelben, és mennyi szabad hely kell. Ha kevés, **állj meg** — ne félúton
derüljön ki.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Új menüpont:** a médiatár kaphat sajátot — azt rendszeresen használják.
- **Néma hiba tilos.** Ha egy kép nem jött át vagy egy kapcsolat elveszett, azt **számszerűen**
  mondd ki. 1091 fájlnál a „sikerült" önmagában semmit nem jelent.
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
- **A kör végén frissítsd az `ALLAPOT.md`-t.**
