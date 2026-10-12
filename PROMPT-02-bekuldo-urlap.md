# Prompt Code-nak — 02. A programbeküldő űrlap

> **Használat:** másold be Code-nak. Minden döntés benne van, nincs kitöltendő rész.

---

## Hatókör — ebben a fázisban CSAK a programbeküldő űrlap

**Nem építed meg** a szabadulószoba, az olimpia és az önkéntes űrlapot. Azok külön fázisok lesznek,
és erre épülnek rá. Ez az első, mert ez teszi le a közös alapokat: publikus útvonalak, beküldői
belépés, képfeltöltés, szerver-oldali validáció, értesítések, jóváhagyási állapotok.

**Lassan lépkedünk.** Ha félúton úgy látod, hogy a feladat nagyobb, mint amit egy körben jól meg
lehet csinálni, állj meg és jelezd — ne told ki a kört.

## Olvasd el ELŐSZÖR

1. Ebben a mappában: `CLAUDE.md` és `ATALLAS-TERV.md`.
2. A **2026-os megvalósítás** a `..\forgatag-os` mappában, **csak olvasásra**:
   - `submit-api\form-config.mjs` — a kategóriánkénti extra mezők
   - `submit-api\server.mjs` — a teljes szerver-oldali validáció, szövegtisztítás, képellenőrzés
   - `submit-api\public\index.html` — az űrlap és a kliensoldali viselkedés
   - `BEKULDO-URLAP-SPEC.md`, `BEKULDO-AUTH-TERV.md`, `IDOPONT-ISMETLODES-TERV.md`

**A 2026-os kód a kiindulás, nem a cél.** A mezőket, a validációs szabályokat és a hibaüzenetek
szövegét **vedd át egy az egyben** — azok kiforrottak, egy fesztivált végigszolgáltak. A
szerkezetet viszont Laravelben építsd újra, és végezd el az alább felsorolt javításokat.

---

## 1. Amit változatlanul átveszel

- **Minden mező, minden felirat, minden hibaüzenet szövege** a 2026-os kódból. Ne fogalmazz újra.
- **A kategóriánkénti adaptív extra mezők** logikája: egy leíró-tömb kategória-slugonként, ugyanaz
  a forrás hajtja a megjelenítést ÉS a szerver-oldali validációt. **Ismeretlen kategória → csak az
  alapmezők** (ez a hibatűrő viselkedés szándékos, tartsd meg).
  Ez a mezőleírás a rendszer **szerkezete**, ezért maradhat kódban — ne csinálj belőle admin-felületet.
- **A szövegtisztítás** beküldés előtt: emoji-eltávolítás, szóköz-összevonás, és a CSUPA NAGYBETŰS
  szöveg automatikus javítása magyar lokállal. Szerveren és böngészőben egyaránt.
- **A fotó szabályai:** kötelező, egy darab, min. 1200×800 px, max. 10 MB, JPG/PNG/WebP.
  A méretet a szerver a fájl tartalmából olvassa ki, ne a kliens állításából.
  Fotós neve kötelező, és a jogi nyilatkozat pipálása kötelező.
- **Az időpont-szabályok**, beleértve az éjfél utáni végződést: ha a vége korábbi a kezdésnél, az a
  következő hajnalt jelenti, és **legfeljebb a fesztiválnap záró órája** lehet (ma 03:00).
  A program ilyenkor is a kezdés napjához tartozik.
- **Egy nap = egy kezdés + egy opcionális vég + egy szöveges megjegyzés.** Napon belül több idősáv
  **NEM** lesz (tulajdonosi döntés). Az ebédszünet marad megjegyzés.
- **Ismétlődő napok:** a beküldő kipipálja, mely fesztiválnapokon fut a program ugyanabban az
  időpontban, és ebből generálódik naponként egy-egy időpont-sor.
- **Egy hosszú lap**, nem varázsló. A hibák egyetlen listában, a lap tetején.
- **Nincs piszkozat-mentés** (tulajdonosi döntés). Egy ülésben kell kitölteni.

## 2. Amit MÁSKÉPP csinálsz — javítások

### 2.1 A beküldő csak a saját kategóriáiba küldhet be
**2026-ban ez hiba volt:** a belépett programfelelőshöz tartozott egy kategória-lista, de a
beküldési végpont **bármelyik kategóriát elfogadta**. Most a szerver utasítsa vissza, ha a beküldő
nem jogosult az adott kategóriára.

### 2.2 „Saját beküldéseim" lap + javítás jóváhagyásig
A belépett beküldő lássa a saját programjait listában, és **amíg a program nincs jóváhagyva,
szerkeszthesse**. Jóváhagyás után zárolódik; ott már csak a koordinátor módosíthat.
**2026-ban semmilyen javítási lehetőség nem volt**, minden a koordinátoron ment keresztül.

### 2.3 Minden beállítás a Beállítások lapról jön — nem környezeti változóból
A `CLAUDE.md` 2/b pontja szerint. Konkrétan innen jöjjön:
- a beküldés **nyitva/zárva** állapota és a zárt állapot üzenete,
- az **értesítési e-mail-címek**,
- a **fesztiválnapok** (a Beállításokban megadott dátumokból számolva),
- a **fesztiválnap záró órája** (ma 03:00).

🔴 **2026-ban ez két konkrét bajt okozott:** az önkéntes űrlapot kétszer kellett kérni, hogy
lezárjuk, mert konténer-újraindítás kellett hozzá; és a szabadulószoba értesítései egy nem létező
címre mentek, mert nem volt hova beírni a koordinátort.

### 2.4 A záró óra ne legyen két helyen leírva
**2026-ban a böngészőben `'03:00'` volt beégetve, a szerver viszont a seed-fájlból olvasta.**
Ha az egyik változott, a két hibaüzenet szétcsúszott. Most **egy forrásból** jöjjön mindkettő.

### 2.5 Néma hiba tilos a képfeltöltésnél
**2026-ban:** ha a fotó feltöltése elhasalt, a program fotó nélkül létrejött, és a beküldő
**sikerüzenetet látott**. Ez a projekt legdrágább hibafajtája.
Most: ha a kép nem ment fel, az **látszódjon** — a beküldő tudja meg, és a koordinátor is kapjon
róla jelzést.

### 2.6 Védelem a végponton
**2026-ban a beküldési végponton nem volt sem limit, sem méz-bödön, sem captcha** — csak a
belépési fal védte. Most tegyél rá **IP-alapú gyakorisági korlátot**, és a belépésre tartsd meg a
zárolást ismételt hibás próbálkozásnál.

### 2.7 A beküldői belépés ne keveredjen az adminnal
A beküldők **nem** admin-felhasználók. Külön belépési útvonal, külön jelszótár, és a Filament
adminba **semmilyen körülmények között nem tudnak belépni**.

---

## 3. Mi történik beküldés után

- A program **draft** állapotban jön létre, forrásként megjelölve, hogy publikus űrlapról érkezett.
- A **koordinátor kap értesítő levelet** (a címet a Beállítások adja).
- A beküldő **a jóváhagyáskor** kap levelet, nem beküldéskor — ahogy 2026-ban is.
- A **napok szerinti időpont-sorok** generálódjanak: a megmaradó napok **helyben frissüljenek**
  (az azonosítójuk ne változzon), a kivett napok töröljenek, az újak beszúródjanak.
- **A gépi fordítás ebben a fázisban NEM feladat.** Csak hagyj neki tiszta helyet: legyen egyértelmű,
  mely mezők fordítandók (cím, leírás, jegyinformáció). Fordítást ne hívj.

---

## 4. Ellenőrzés, mielőtt készre jelented

- **375 px-en is végigpróbálod** az egész űrlapot. A mobil a fő nézet.
- Végigpróbálod **legalább három kategóriával**: egy extra mezők nélkülivel, a könyvbemutatós
  feltételes mezőkkel, és egy olyannal, aminek kötelező extra mezői vannak.
- Kipróbálod az **éjfél utáni végződést** (23:00 → 00:30 átmegy; 23:00 → 04:00 elbukik).
- Kipróbálod, hogy **más kategóriájába nem tudsz beküldeni**.
- Kipróbálod, hogy **zárt állapotban a végpont is nemet mond**, nemcsak az űrlap tűnik el.
- Kipróbálod a **képfeltöltés hibáját** is, és ellenőrzöd, hogy látszik.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Új admin-menüpontot ne vegyél fel.** Ha úgy érzed, kell, előbb kérdezd meg.
- A felhasználó nem fejlesztő. Ami neki teendő, azt magyarul, kattintásszinten írd le.
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
