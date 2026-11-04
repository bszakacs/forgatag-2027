# Prompt Code-nak — 13. A mozgás- és dekor-réteg

> **Használat:** másold be Code-nak.

---

## Hatókör és miért önálló lépés

**Ez nem laponkénti munka, hanem egy réteg.** Az Arculat „Mozgás" és „Firkák" beállításai vezérlik —
ahogy 2026-ban is. Ha laponként készülne, a lapok elcsúsznának egymástól, és a `/tema` kapcsolói
nem hatnának rájuk egységesen.

**Visszamenőleg hozzányúl MINDEN eddig elkészült laphoz:** Rólunk, hírlista, hír, aloldalak,
programkereső, napi nézet, program lapja.

⛔ **A kezdőlapot NE kezdd el.** Az a következő kör, és erre a rétegre fog épülni.

## Olvasd el ELŐSZÖR

- `ALLAPOT.md`, `CLAUDE.md` — **különösen a 2/e pontot** (a frontend 1:1 jön át) és a token-szabályt.
- A 2026-os megvalósítás **csak olvasásra**: a landing firka-dekorja, a reveal-szkript, és a
  `..\forgatag-os\frontend\src\lib\theme.ts` „Mozgás" és „Dekor (firkák)" szekciói.

🔴 **NE ÉRTELMEZD, EMELD ÁT.** A programkereső körében ez volt a legnagyobb időveszteség.

---

## 1. Amit Code maga sorolt fel hiányként

- **A „fonál"** — a görgetésre kirajzolódó kanyargós vonal a képek között, a rajta haladó
  jelölőkarikával (Rólunk).
- **A fonál menti firkák** — a díszítő motívumok, amiket ugyanez a szkript szórt el a lapon.
- **A felúszás** — a szakaszok görgetésre úsztak be.
- **A háttér színfoltjai** — a 2026-os egyik kék foltja kimaradt.
- **A kártyák fényes kiemelése** a hírlistán (most egyszerűbb a hatás egérrel fölé víve).
- **A programkereső kártyáinak felúszása** és a lapon szórt firkák (`#pageDecor` üres, előkészítve).

## 2. A vezérlés — a témából, nem beégetve

**Mozgás:** mennyiség (Teljes / Finom / Minimális) · mikrointerakció-sebesség · reveal-sebesség ·
„MA" pulzálás · firka-lebegés.

**Firkák:** sűrűség (Nincs / Visszafogott / Alap) · átlátszóság · nyitókép szélein ·
fotó nélküli kártyákon · 8 szín · 6 egyedi SVG-hely.

**Mindegyik kapcsolónak valódi hatása legyen minden lapon.** Ha egy kapcsoló nem hat valahol, az
hiba — a `/tema` némán hatástalan beállítása pontosan az a hibafajta, amit a token-szabály tilt.

## 3. Hozzáférhetőség — ez nem opcionális

🔴 **A rendszer-szintű „kevesebb mozgás" beállítást tiszteletben kell tartani**
(`prefers-reduced-motion`). Akinek a telefonján ez be van kapcsolva, annak a felúszás és a lebegés
ne induljon el — mozgásra érzékeny embereknél ez rosszullétet okoz.

Ez **felülírja** a téma „Mozgás" beállítását: ha a látogató kevesebb mozgást kér, azt kapja.

## 4. Teljesítmény — itt könnyű elrontani

⚠️ **A programkeresőn több száz kártya lehet egyszerre.** Ha mindegyikhez görgetés-figyelő és
szórt SVG tartozik, az telefonon akadni fog.

- A felúszás **a látótérbe kerüléskor** induljon, ne folyamatos görgetés-figyeléssel.
- A firkák **ne egyenként** kerüljenek a lapra, ha egy közös réteggel megoldható.
- **Mérd meg telefonon**, ne csak gépen: a kereső görgetése maradjon sima.

## 5. Ellenőrzés, mielőtt készre jelented

- **Minden eddigi lap** megkapja a réteget: Rólunk, hírlista, hír, aloldalak, programkereső,
  napi nézet, program lapja.
- **A téma minden kapcsolója hat** — próbáld ki mindegyiket, lapról lapra.
- **A „kevesebb mozgás" rendszerbeállítás leállítja** a mozgást, a téma beállításától függetlenül.
- **375 px-en, valódi telefonon** a programkereső görgetése sima marad.
- A Rólunk a 2026-os mellé téve **ugyanúgy néz ki** — fonál, karika, firkák, felúszás, háttér.
- Élesben is telepítve és megnézve.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Semmit ne hagyj ki azzal, hogy „egyszerűbb így"** (2/e). Ha valami nem fér bele, **kérdezz**.
- **A kezdőlapot NE kezdd el.**
- **Néma hiba tilos.**
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
- **A kör végén frissítsd az `ALLAPOT.md`-t.**
