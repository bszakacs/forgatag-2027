# Prompt Code-nak — 21. Kedvencek és naptár-export

> **Használat:** másold be Code-nak.

---

## Hatókör

A programkereső körében előkészített, de kikapcsolt gombok bekapcsolása:

1. **a szív** a sorokon és a kártyákon, a felugró ablak **„Mentés"** gombja, és a
   **„Kedvenc programjaim"** lap (a 13 lappal együtt már átjött),
2. **a felugró ablak „Naptárba" gombja** — naptár-export.

**Tulajdonosi döntés (2026-11-19): a kedvencek kellenek, elmaradhatatlan.**

## Olvasd el ELŐSZÖR

- `ALLAPOT.md`, `CLAUDE.md` — **különösen a 2/e pontot** (1:1).
- A 2026-os megvalósítás **csak olvasásra**: `..\forgatag-os\favorites-api\server.mjs`,
  `..\forgatag-os\frontend\src\components\FavoritesView.astro`, és a
  `forgatag_2026_kedvenceim.html` / `forgatag_2026_kedvenceim_kilepve.html` tervek.

---

## 1. Azonosítás belépés nélkül

A látogató **nem lép be**. A lista a böngészőjében tárolt azonosítóhoz tartozik, és a szerveren él.

🔴 **A megosztható link létfontosságú, nem extra.** Ha a látogató kitörli a böngészője adatait vagy
másik eszközről nyitja meg, **a link az EGYETLEN út vissza a listájához.**
**Tehát a linket feltűnően kell felkínálni**, amint a lista létrejön — ne legyen eldugva.

**Személyes adatot ne kérjünk** hozzá. Ha nincs név és e-mail, nincs mit védeni.

## 2. A szívek és a gyorsítótár — itt könnyű csendben elrontani

🔴 **A szívek állapota látogatónként más.** Ha a szerver a lapba rendereli, hogy mi van megjelölve,
akkor **egy gyorsítótárazott lap valaki más szíveit mutatná.**

**Megoldás:** a lap **semleges szívekkel** érkezzen, és az állapot **betöltés után** kerüljön rá a
böngészőben. Így a lap gyorsítótárazható marad.

**A programkereső a fesztivál legterheltebb lapja** — a gyorsítótárazhatóságát ne áldozzuk fel egy
szív miatt.

## 3. A szív és a htmx

A szívnek **túl kell élnie a találati lista cseréjét.** Szűrés után is működnie kell, és a már
megjelölt programokon látszania kell a jelölésnek.

**Ezt próbáld ki:** jelölj meg egy programot, szűrj, és nézd meg, hogy a szív a helyén van-e.

## 4. A „Kedvenc programjaim" lap

A 2026-os terv szerint — a lap már átjött a 13 lappal együtt, és **a 2026-os rendszerben a NAS-ra
hívott**, ami most természetesen megszűnik.

Két állapota volt: üres lista és feltöltött lista. **Mindkettőt vedd át.**

## 5. Naptár-export

A felugró ablak **„Naptárba"** gombja: egy program hozzáadása a látogató naptárához, szabványos
naptárfájllal.

**Figyelj a fesztiválnap szabályára:** a 23:00–00:30 program **a következő hajnalban ér véget** —
a naptárfájlban a valódi időpont szerepeljen, ne a kezdés napjának 00:30-a.

**Ha kézre esik, a teljes kedvenclista is legyen exportálható** egyben — de ha ez nagyra nőne,
hagyd ki és jelezd.

## 6. Ellenőrzés, mielőtt készre jelented

- Jelölj meg egy programot, **szűrj**, és a szív a helyén van.
- **Másik böngészőben megnyitva a megosztott link** ugyanazt a listát hozza.
- A böngésző adatainak törlése után **a link visszahozza** a listát.
- A **naptárfájl megnyílik** telefonon (próbáld ki valódi eszközön), és egy 23:00–00:30 program
  **a helyes időponttal** kerül be.
- A programkereső **gyorsítótárazható marad** — a lap forrásában ne legyen látogató-specifikus adat.
- **375 px-en** és **románul** is.
- Élesben is telepítve és megnézve.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Néma hiba tilos.** Ha egy mentés nem sikerül, a látogató lássa — egy csendben elvesző kedvenc
  rosszabb, mint egy hibaüzenet.
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
- **A kör végén frissítsd az `ALLAPOT.md`-t.**
