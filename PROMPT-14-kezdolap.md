# Prompt Code-nak — 14. A kezdőlap

> **Használat:** másold be Code-nak.

---

## Hatókör

A kezdőlap — a legösszetettebb egyetlen lap. **A mozgás- és dekor-rétegre épül**, ami az előző
körben elkészült: a felúszás és a firkák onnan jönnek, ne építsd újra.

## Olvasd el ELŐSZÖR

- `ALLAPOT.md`, `CLAUDE.md` — **különösen a 2/e pontot** (a frontend 1:1 jön át).
- A 2026-os kezdőlap **csak olvasásra**.

🔴 **NE ÉRTELMEZD, EMELD ÁT.** Ez eddig kétszer okozott újramunkát.

---

## 1. Két állapot, egy kód — tulajdonosi döntés

A mai 2026-os kezdőlap **fesztivál utáni állapotban** van: a **visszaszámláló** és a **kiemelt
programok sávja** kikerült belőle (a fesztivál véget ért), és bekerült a **partner-logó csúszka**.

**A 2027-es oldal végig fog menni mindkét állapoton**, ezért **mindkettő épüljön meg**, és a
különbség csak **kapcsoló** legyen a Beállításokban:

| Elem | Fesztivál előtt / alatt | Utána |
|---|---|---|
| Visszaszámláló | **látszik** | rejtve |
| Kiemelt programok sávja | **látszik** | rejtve |
| Partner-logó csúszka | látszik | látszik |

**Így tavasszal nem kell újraépíteni.** Most induljon a fesztivál utáni állapotban, hogy a mai
2026-os kezdőlappal egyezzen.

## 2. Amit a lap tartalmaz

A 2026-os kezdőlap szerint: **nyitókép/csúszka** (5 dia), a hero szövege, **kiemelt programok**
sávja (kapcsolható), **hírek**, **„Forgatag számokban"**, **médiatár**, **szervezők**, és
**„A Forgatag mögött állnak"** — a partnerek és támogatók logói mozgó sávban.

**A hero szövege szerkeszthető legyen**, ne beégetve. (A 2026-os jelenlegi szövege:
„2027. augusztus 25–29. között újra találkozunk!")

## 3. Amit NE hozz vissza

⚠️ **Ezek a 2026-os kezdőlapról SZÁNDÉKOSAN hiányoznak** (a v7 landing átvételekor estek ki, a
törölt régi `HomeView`-hoz tartoztak), és **a hozzájuk tartozó `/tema`-vezérlők is kikerültek:**

- canvas fény-részecskék
- „Pörgess egy programot!" sorsoló
- futó névsor (marquee)
- aurora-réteg

**Ha régebbi kódban rájuk találsz, NE élessz fel őket.** A „1:1" a **mai** állapotra vonatkozik.

## 4. A dekor-rétegből jön

A kártya-felúszás és a **landing firka-dekorja** — a nyitókép két szélén lengő, valamint a lapon
szórt, sodródó motívumok, a `/tema` firka-füléről szabályozva. **Ezek már megvannak**, csak be kell
kötni őket. Ne készíts párhuzamos megoldást.

## 5. Teljesítmény

A kezdőlap az első benyomás, és **telefonon nyitják meg először**. A csúszka képei, a logó-sáv és a
dekor együtt könnyen nehézzé teszik.

- A képek **azon az arányon** készüljenek, amin megjelennek (kép csak egyszer vágódik).
- A látótéren kívüli képek **ne töltsenek be azonnal**.
- **Mérd meg valódi telefonon**, mobilhálózaton, mennyi idő alatt jelenik meg a nyitókép.

## 6. Ellenőrzés, mielőtt készre jelented

- A lap a **2026-os mellé téve ugyanúgy néz ki** — szakaszról szakaszra.
- A **visszaszámláló és a kiemelt sáv kapcsolója** működik mindkét irányba.
- A **hero szövege szerkeszthető** az adminból.
- A négy kivezetett elem **nincs sehol**.
- **375 px-en**, valódi telefonon, és a nyitókép megjelenési idejét **megmérve**.
- **Románul** is.
- Élesben is telepítve és megnézve.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Semmit ne hagyj ki azzal, hogy „egyszerűbb így"** (2/e). Ha valami nem fér bele, **kérdezz**.
- **Néma hiba tilos.**
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
- **A kör végén frissítsd az `ALLAPOT.md`-t.**
