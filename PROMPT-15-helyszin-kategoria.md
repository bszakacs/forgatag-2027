# Prompt Code-nak — 15. (12/b) Helyszín- és kategórialapok, `/terkep`

> **Használat:** másold be Code-nak.

---

## Hatókör

A programkereső körének záró darabja:

1. **a 8 hiányzó helyszín pótlása** (tulajdonosi döntés megvan),
2. **helyszínlapok**,
3. **kategórialapok** (lista és egy kategória lapja),
4. **a `/terkep` lap**,
5. **a térképszámok bekötése**,
6. **a fejléc keresője**,
7. **a napi nézet és a program lapja sorról sorra összevetve** a 2026-ossal.

## Olvasd el ELŐSZÖR

- `ALLAPOT.md`, `CLAUDE.md` — **különösen a 2/e pontot** (a frontend 1:1 jön át).
- A 2026-os megvalósítás **csak olvasásra**: `VenueDetailView`, `TaxonDetailView`, `TaxonListView`,
  `MapView`, `DailyProgramView`, `ProgramDetailView`, `ProgramCard`.

🔴 **NE ÉRTELMEZD, EMELD ÁT.** Ez eddig kétszer okozott újramunkát.

---

## 1. Az első lépés: a 8 helyszín pótlása

**Tulajdonosi döntés (2026-11-04): ezek 2027-ben is helyszínek lesznek, vedd fel őket.**

`var-csicsergo-1` · `var-csicsergo-2` · `bernady-haz` · `var-pulzus-by-rmdsz-maros` ·
`studium-hub-eloadoterem` · `fociakademia` · `bolyai-szabadulo-szoba` · `uat-galeria`

**A térkép-pozíciójukat is hozd a 2026-os mentésből**, ahol van.

**Utána futtasd újra a tesztprogram-behozatalt** — így a 61 kimaradt program is átjön, és a
helyszínlapokon lesz mit mutatni.

⚠️ **Ez nem oldja meg a helyszínlista egészét.** A projektben **három eltérő szám** van rá
(59 / 52 / 46), és ezt a szervezőknek kell átnézniük a tavaszi beküldés előtt. **Hagyd benne a
nyitott kérdések közt** — ne próbáld kitalálni.

## 2. Helyszín- és kategórialapok

A 2026-os megfelelőik szerint, **a térképszámokkal együtt**. Ma a program lapja ezek helyett a
szűrt keresőre visz (`/programok?helyszin=…`) — ezt váltsák fel a valódi lapok.

## 3. A `/terkep` lap

A 2026-os `MapView` szerint.

## 4. A fejléc keresője

A téma `header.showSearch` kapcsolója már létezik, de a kereső **nincs bekötve**. Vigyen a
`/programok?q=` címre.

## 5. A fotó nélküli kártya sarok-firkája

A `.pc-doodle` — az Arculat „Fotó nélküli kártyákon" kapcsolója. **Ma ennek a kapcsolónak nincs
eleme**, amin hatna. A 2026-os `ProgramCard`-dal együtt épüljön meg.

🔴 **Ez a „némán hatástalan beállítás" esete**, amit a token-szabály tilt — most jön el a pillanat,
hogy megszűnjön.

## 6. Az összevetés, ami eddig kimaradt

**A napi nézetet (`/nap/…`) és a program lapját** vesd össze **sorról sorra** a 2026-os
`DailyProgramView` és `ProgramDetailView` mellé téve. Ezt magad jelezted hiányként két körrel
ezelőtt.

## 7. Ellenőrzés, mielőtt készre jelented

- A 8 új helyszín **lapja működik**, és a tesztprogramok megjelennek rajta.
- A **térképszámok** a helyszínlapon és a `/terkep` lapon is a helyükre mutatnak.
- **Szűrj, aztán kattints a térképre** — továbbra is működik.
- A **fejléc keresője** odavisz, ahova kell.
- A **„Fotó nélküli kártyákon" kapcsolónak van hatása** — ki/be kapcsolva látszik a különbség.
- A napi nézet és a program lapja **a 2026-os mellé téve ugyanaz**.
- **375 px-en** és **románul** is.
- Élesben is telepítve és megnézve, **mért válaszidővel**.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Semmit ne hagyj ki azzal, hogy „egyszerűbb így"** (2/e). Ha valami nem fér bele, **kérdezz**.
- **Néma hiba tilos.**
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
- **A kör végén frissítsd az `ALLAPOT.md`-t.**
