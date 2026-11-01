# Prompt Code-nak — 12. A programkereső 2. része: térkép, helyszín- és kategórialapok

> **Használat:** másold be Code-nak.

---

## Hatókör

Az 1. részben elkészült a kereső, a napi nézet és a program lapja. **Most jön:**

1. **a térkép** — a helye már ki van hagyva (440 px-es hasáb a lista mellett jobbra),
2. **a helyszínlapok**,
3. **a kategórialapok**,
4. **a gombok bekapcsolása**, amiknek a helye már megvan: a felugró ablak „Mutasd a térképen"
   gombja, a mobil „Térkép" gomb, a helyszínlista térképszámai.

## Olvasd el ELŐSZÖR

- `ALLAPOT.md`, `CLAUDE.md` — **különösen a 2/e pontot** (a frontend 1:1 jön át).
- A 2026-os megvalósítás **csak olvasásra**: `..\forgatag-os\frontend\src\lib\finder-map.ts`,
  `..\forgatag-os\frontend\src\components\MapEditorView.astro`, a programkereső térkép-része.

🔴 **A 2/e szabály itt is él: ne értelmezd, EMELD ÁT.** Az 1. részben ez volt a legnagyobb
időveszteség — a layout értelmezésből épült, és újra kellett csinálni.

---

## 1. Az adat, ami még hiányzik

⚠️ **A térkép-pozíció a 2027-es adatbázisban EGYETLEN helyszínnél sincs meg** (a `venues` tábla
`map_*` mezői üresek). **A 2026-os mentésből kell áthozni** — ez az első lépés, enélkül a térkép üres.

**Öt helyszínnek a 2026-os mentésben sincs pozíciója:** Vár, Várkert · Állatkert ·
Kultúrpalota, nagyterem · Marosvásárhelyi vikendtelep · Fociakadémia.
**Ezeket ne találd ki** — a térképen jelöld úgy, ahogy 2026 tette (a leltár szerint egy részük a
térkép szélén volt jelölve), és **sorold fel a jelentésben**, hogy a szervezők pótolhassák.

## 2. A térkép

Beágyazott SVG, kattintható helyszínekkel.

🔴 **A térkép a htmx-csere célterületén KÍVÜL marad** — ez az 1. részben már így lett előkészítve
(`#pkMapCol`, `#pkMapSheetIn`). Az `innerHTML`-csere megsemmisítené az eseménykezelőit.

**A szűrésre célzott frissítéssel reagáljon** — ne a térkép újrarajzolásával. A válaszban egy
adat-elem jöjjön, amiből a térkép magát frissíti.

**Ezt a próbát most el kell végezni** (az 1. részben még nem lehetett): **szűrj, aztán kattints a
térképre.** Ha a kattintás nem működik, az eseménykezelők elvesztek.

## 3. Helyszín- és kategórialapok

A 2026-os megfelelőik szerint. A helyszínlapon a térképszámok is működjenek.

## 4. Amit az 1. részből át kell nézni

Code maga jelezte: **a napi nézetet (`/nap/…`) és a program lapját nem vetette össze sorról sorra**
a 2026-os `DailyProgramView` és `ProgramDetailView` mellé téve. **Most vesd össze** — ott lakik
ugyanaz a kockázat, ami a keresőnél megvolt.

## 5. A sebesség

A szűrés élesben sem volt gyors. **Mérd meg, mi viszi az időt** — a lekérdezés, a renderelés vagy a
szerver —, és ha adatbázis-indexelés kell, tedd meg. **A programkereső lesz a fesztivál
legterheltebb lapja.**

## 6. Ellenőrzés, mielőtt készre jelented

- **Szűrj, aztán kattints a térképre** — működik.
- A térkép a szűrésre frissül, de **nem rajzolódik újra**.
- **375 px-en** a térképlap, a „Térkép" gomb, a helyszín- és kategórialapok.
- A felugró ablak **„Mutasd a térképen"** gombja odavisz, ahova kell.
- **A lábléc látszik** minden új lapon (az 1. részben ez hiba volt).
- **Románul** is végigpróbálva.
- Élesben is telepítve és megnézve, **mért válaszidővel**.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Semmit ne hagyj ki azzal, hogy „egyszerűbb így".** Ha valami nem fér bele, **kérdezz** (2/e).
- **Néma hiba tilos.**
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
- **A kör végén frissítsd az `ALLAPOT.md`-t.**
