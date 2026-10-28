# Prompt Code-nak — 08. Az admin tartalmi gyűjteményei

> **Használat:** másold be Code-nak.

---

## Hatókör

Hírek, menü, aloldalak. *(A galéria és a támogatók váza már kész.)*

**Ez a publikus oldal előfeltétele:** enélkül nincs mit megjeleníteni rajta.

**A képek már a médiatárban vannak**, eredet-jelöléssel (`legacy_id`) — azokon keresztül kösd be
őket, **ne töltsd fel újra**.

## Olvasd el ELŐSZÖR

- `ALLAPOT.md`, `CLAUDE.md` — különösen a menü-szabályt (2/b) és a médiatár-döntést (2/d).
- A 2026-os tartalom a mentésben, és a lapok forrása **csak olvasásra**:
  `..\forgatag-os\frontend\src\rolunk\`, `..\forgatag-os\frontend\src\fesztivalrend\` stb.

---

## 1. Hírek

Kétnyelvű (HU/RO), borítókép a médiatárból, állapot (piszkozat / publikált), megjelenés dátuma.

**A 2026-os hírekből csak NÉHÁNYAT hozunk át, tesztelésre** (tulajdonosi döntés) — 3–5 darab elég.
A többi nem jön: időhöz kötött bejelentések, 2027-ben értéktelenek, és a 2026-os archívum őrzi őket.
*(A leltár szerint egyiküknek sincs román szövege sem.)*

🔴 **A tesztadat legyen felismerhető és EGY LÉPÉSBEN törölhető.** Élesítés előtt az adatbázist
ürítjük; ha a tesztadatot kézzel kell vadászni, a legrosszabb eset az, hogy egy tavalyi teszthír
kint marad az éles oldalon. **Ez a szabály a teszteléshez behozott programokra is vonatkozik.**

## 2. Menü

**Hierarchikus:** szülő-gyerek menüpontokkal, fogd-és-vidd átrendezéssel. Kétnyelvű, és egy
menüpont **elrejthető legyen nyelvenként** (2026-ban ez működött).

⚠️ **A fa-szerkesztéshez NE építs harmadik féltől származó bővítményre.** A `CLAUDE.md` indokolja:
a legjobb jelölt is alig karbantartott. **Írd meg magunknak** — ez jobban jár, mint egy elhagyott
csomagtól függeni.

**A 2026-os menüt hozd át kiindulásnak** (2 menü, 21 menüpont). A 2027-es szerkezet ebből alakul majd.

## 3. Aloldalak

Cím, bevezető, blokkok. **A 2026-os lapokat hozd át** — a Rólunk három almenüje, a Galéria és a
videó-archívum **egy az egyben** (tulajdonosi döntés). 13 lap, 9 blokk.

⚠️ **Egy dolgot tudj, de MOST ne javíts:** a 2026-os lapokon a szöveg nagy része **beégetett magyar**,
nem szerkeszthető kulcson — a fesztiválrend lapján például a szövegnek csak a **18%-a** szerkeszthető.
Ez akkor pótlandó, amikor a publikus sablonok megépülnek, nem most. **Csak jegyezd fel** az
`ALLAPOT.md`-be, hogy ne vesszen el.

## 4. Mellékesen: az 5. adósság törlesztése

A beküldő űrlap extra mezői olyan kategória-slugra hivatkoznak, ami 2026-ban megszűnt
(`kerekasztal-beszelgetesek`). Ma **csendben** nem ad extra mezőket. Javítsd ki, és **nézd át, nincs-e
még ilyen** — a leltár szerint öt kategória szűnt meg és négy új jött.

**És tegyél bele egy védelmet:** ha a konfigurációban olyan kategória-slug szerepel, ami nem létezik,
az **ne maradjon néma** — legyen látható jelzés. Ez pontosan a „néma hiba tilos" szabály esete.

## 5. Menüpontok — a szabály érvényes

`CLAUDE.md` 2/b: rokon dolgok **menücsoportba** kerülnek. A hírek, a menü, az aloldalak, a galéria
és a támogatók egy **„Tartalom"** csoportba valók, nem öt külön menüpontba.

## 6. Ellenőrzés, mielőtt készre jelented

- **375 px-en** használható minden lap, a menü fogd-és-vidd átrendezése is.
- A behozott lapok és menüpontok **tényleg megjelennek**, a képeikkel együtt — nem elég, hogy a sor
  létrejött.
- A menüpont **nyelvenkénti elrejtése** működik.
- **Kategóriagazda-fiókkal is próbáld végig** az új felületeket, a közvetlen címet is begépelve
  (4. adósság).
- Élesben is telepítve és megnézve.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Néma hiba tilos.** Ha egy lap vagy menüpont nem jött át, azt **számszerűen** mondd ki.
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
- **A kör végén frissítsd az `ALLAPOT.md`-t.**
