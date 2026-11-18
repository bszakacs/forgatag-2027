# Prompt Code-nak — 19. A fordítási lánc

> **Használat:** másold be Code-nak.

---

## Hatókör

A magyar → román fordítás teljes láncának megépítése: a gépi futás, a fordítás-státuszok, a
jóváhagyási felület és a megjelenítési szabályok.

**A döntések megvannak a `CLAUDE.md` 5. pontjában** — azokat ne nyisd újra, csak hajtsd végre.

## Olvasd el ELŐSZÖR

- `ALLAPOT.md`, `CLAUDE.md` — **az 5. pontot (fordítás) teljes egészében.**
- A 2026-os megvalósítás **csak olvasásra**: `..\forgatag-os\tooling\translate.mjs`.

---

## 1. A hatókör, ami szűkebb, mint hinnéd

🔴 **A gépi fordítás CSAK a beküldött programokra fut** — cím, leírás, jegyinformáció.
**Indok:** azok külsős, nem általunk írt szövegek.

**A saját tartalmainkat kézzel fordítjuk** — hírek, kategória- és helyszínnevek, menü, laptartalom.
**A gépi futás hozzájuk NEM nyúl.** 2026-ban ez többször elcsúszott; a `translate.mjs`-ben bent
maradt a hírek fordítása, pedig nem oda való.

## 2. Az adatszerkezet — amit 2026-ban elrontottunk

🔴 **A fordítás NE polimorf táblában éljen.** 2026-ban egyetlen tábla szolgált ki elvben mindent
(`entity` + `entity_id` szabad szövegként, valódi idegen kulcs nélkül).

**Következmény, ami hónapokig mérgezett:** a rendszer nem látta a kapcsolatot, ezért **nem tudta
megmutatni a fordítást a program lapján**, és emiatt született minden kerülőút — plusz oszlopok,
tükör-mezők, egy megfeneklett saját bővítmény, és egy **végtelen kör** a szinkronizálásban.

**Most: gyűjteményenkénti fordítás-tábla, valódi idegen kulccsal.** Innen magától jön a nyelvfüles,
egy képernyős szerkesztő, hackek nélkül.

## 3. A státuszok és a megjelenítés

Státuszok: `machine_translated` → `reviewed` → `approved`, és `outdated`.

**Megjelenítési sorrend:** `approved` → `reviewed` → `machine_translated`.
**Az `outdated` KIMARAD** — ott a **magyar szöveg** látszik, mert a magyar forrás megváltozott, és a
régi román téves adatot (időpont, ár, helyszín) tartalmazhat.

🔴 **Az emberi fordítás SÉRTHETETLEN.** A gépi futás **nem írhat felül** `reviewed`, `approved` és
`outdated` sort sem.

🔴 **Kötelező ellensúly:** az `outdated` sorok **feltűnően látszódjanak** a jóváhagyási sorban.
Enélkül a román oldalon **észrevétlenül** maradhat téves adat — ez a szabály azért van, mert a
gép nem javítja ki őket, és csak ember veheti észre.

## 4. Mikor fusson

2026-os tulajdonosi döntés: **a gépi fordítás már a beküldéskor lefut**, a `draft` státuszú
programokra is, hogy a koordinátor **egyetlen körben** lássa a magyar és a román szöveget.

**2026-ban ez a build-lánchoz volt kötve — az most nincs.** Tehát kell neki saját indítás:
a beküldéskor, és mellé egy ütemezett pótló futás (cPanel cron).

**Négy garancia, ami 2026-ban bevált — tartsd meg:**
- egyszerre csak egy futás (zár),
- **üres futásnál NINCS API-hívás** (költség),
- kulcs nélkül **csendben kimarad**, nem törik el tőle semmi,
- hibánál sem dönt be semmit, de **a hiba látszódjon** (nem néma).

**A változatlan szöveget ne fordítsa újra** — a forrás lenyomata alapján. Ez pénz.

## 5. A védett nevek

Egyetlen forrás: `seed/do-not-translate.json`. A prompt-utasításba is bekerül, **és a futás után
ellenőrizve is van.**

**A fesztivál román neve `Vâltoarea Mureșeană`**, ragozással:
alanyeset `Vâltoarea Mureșeană` · elöljárószó után `Vâltoare` · birtokos `Vâltorii Mureșene`.
**A `Vâltoarei` alak MINDIG hibás.**

⚠️ **Csapda:** az `Ebforgatag` önálló programnév, tartalmazza a „forgatag" szót — **SOHA nem cserélhető.**

💡 **Gyors hibakereső:** a román nyelvben **nincs `ő` és `ű`** — ha ilyen betű van román szövegben,
az bent maradt magyar szó. Ezt tedd be ellenőrzésnek a futás után.

## 6. A jóváhagyási felület

A fordító és a koordinátor lássa egy képernyőn a magyart és a románt, és tudjon státuszt állítani.
**A „fordító" szerepkör** csak ehhez férjen hozzá.

## 7. A felhasználónak szóló teendő

**Az `ANTHROPIC_API_KEY`-t a szerveri `.env`-be kell beírni** — jelszót nem gépelsz, ezt a
tulajdonos teszi. Írd le, hol.

Modell: **`claude-sonnet-5`** (fordításhoz elég), felülírható beállítással.

## 8. Ellenőrzés, mielőtt készre jelented

- Egy beküldött program **fordítása megjelenik** a koordinátor előtt, egy képernyőn a magyarral.
- A gépi futás **nem írja felül** a kézzel átnézett sort — próbáld ki.
- A magyar forrást megváltoztatva a sor **`outdated` lesz**, és **feltűnően látszik** a sorban.
- `outdated` esetén a román oldalon **a magyar szöveg** látszik, nem a régi román.
- **Kulcs nélkül** a rendszer nem törik el.
- **Üres futásnál nincs API-hívás** (naplóból ellenőrizve).
- A védett nevek **épen maradnak**, és a `Vâltoarei` alak **nem fordul elő**.
- **Nincs `ő` és `ű`** a generált román szövegben.
- Élesben is telepítve és megnézve.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Néma hiba tilos.**
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
- **A kör végén frissítsd az `ALLAPOT.md`-t.**
