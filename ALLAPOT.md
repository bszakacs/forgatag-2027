# HOL TARTUNK

> **Ezt olvasd el először, minden új beszélgetés elején.** Utána a `CLAUDE.md`-t.
> **Ezt a fájlt minden kör VÉGÉN frissíteni kell** — a 2026-os `HANDOFF.md` azért vált
> használhatatlanná, mert senki nem frissítette.

**Utolsó frissítés:** 2026-10-21

---

## A projekt egy bekezdésben

A **Vásárhelyi Forgatag 2027** fesztivál rendszere: egyetlen Laravel 13 + Filament 5 alkalmazás a
cPanel tárhelyen, benne a publikus oldal, az admin és az összes beküldő űrlap, egy MySQL
adatbázissal. A 2026-os rendszer (Directus + Astro + öt Node-app a NAS-on) ezt váltja ki.
A felhasználó **nem fejlesztő** — magyarul, laikusbarátan, tömören.

## Hol fut

| Mi | Hol |
|---|---|
| Próbakörnyezet | `proba.forgatag.ro` — az alkalmazás a `public_html/forgatag2027` mappában |
| Admin | `proba.forgatag.ro/admin` |
| Beküldő űrlap | `proba.forgatag.ro/bekuldes` |
| Kód | GitHub: `bszakacs/forgatag-2027` |
| Kijuttatás | cPanel → Git Version Control → *Update from Remote* → *Deploy HEAD Commit* |
| 2026-os adatmentés | `E:\Work\mysites\forgatag.ro\MENTES-2026\2026-10-21\` (688 MB, nem git) |
| A 2026-os rendszer | `..\forgatag-os` — **befagyasztva, csak olvasásra** |

## Ami KÉSZ és élesben mérve

- **Próbatelepítés** — a cPanel Git-telepítés shell nélkül működik.
- **Adatmodell és adatbázis** — kategóriák, helyszínek, programok, időpontok, fordítások, szponzorok.
- **Beállítások lap** — egyetlen lap, tematikus blokkokkal. Nincs külön menüpont semmire.
- **Beküldő űrlap** (`/bekuldes`) — belépés, kategóriaválasztó, adaptív űrlap, „Saját beküldéseim",
  javítás jóváhagyásig. A teljes lánc élesben végigmérve: beküldés → fotó → jóváhagyás → levél.
- **Felhasználók menücsoport** — admin-felhasználók és programgazdák, üdvözlő levéllel és
  elfelejtett-jelszó folyamattal.
- **Szerepkörök és sor-szintű jog** — a kategóriagazda nem fér hozzá idegen kategóriához;
  élesben, böngészőből is ellenőrizve, a közvetlen cím is 404-et ad.
- **A 2026-os adatok kimentve** — adatbázis + 1091 fájl, ellenőrzött épséggel. Leltár:
  `MENTES-LELTAR.md`.
- **Téma-alap** (`PROMPT-05`, 2026-10-21) — a 2026-os utolsó publikált téma az adatbázisban
  (`theme_versions`); minden szín/betű/forma ebből generált CSS-ből jön, a fájlnévben a tartalom
  lenyomatával (`/arculat/tema-<lenyomat>.css`). Logó, fejléc-logó, favicon és a 6 firka valódi
  feltöltött fájl. **Élesben mérve:** a CSS és a favicon a proba.forgatag.ro-ról jön (200), a régi
  `tema.css` 404, NAS-cím sehol; 375 px-en rendben. Részletek: `CLAUDE.md` 8. pont „Téma-alap”.
  ⚠ **A beküldő űrlap kinézete ezzel megváltozott** (a régi `tema.css` az űrlap saját 2026-os
  színeit hordozta, nem a publikált témát): világosabb lila, narancs gomb, Tingler Print címek.

## Ami MOST folyik

Nincs folyó kör — a következő az alábbi lista 1. pontja.
**A `/tema` szerkesztő MÉG NINCS** (a 4. pontban jön); addig a téma módosítása fejlesztői feladat.

## Ami ezután jön, ebben a sorrendben

1. **Az admin tartalmi gyűjteményei** — hírek, menü, támogatók, aloldalak, galéria.
   *Ez a publikus oldal előfeltétele: enélkül nincs mit megjeleníteni.*
2. **A publikus váz** — keretfájl, fejléc, lábléc, egy lap, a valódi token-forrásra kötve.
   *Itt dől el, hogy a Blade + htmx + az áthozott CSS együtt működik-e. Ma bizonyítatlan.*
3. **A programkereső** — a legnagyobb és legösszetettebb darab.
4. **A három egyedi szerkesztő** — `/tema`, `/szerkeszto`, térkép-szerkesztő.
5. **A fordítási lánc.**
6. **A maradék publikus lapok.**
7. **Átkapcsolás** — lásd lentebb.

**A három hátralévő űrlap** (szabadulószoba `PROMPT-03`, olimpia, önkéntes) **levél a fán**: semmi
nem épül rájuk, bárhova beilleszthetők — például egy nagyobb darab után rövidebb körként.

## Az átkapcsolás — megváltozott!

**Korán kapcsolunk át, „coming soon" oldallal** (tulajdonosi döntés, 2026-10-21). Az oldal már akkor
a valódi címén él, amikor még alig van rajta valami, és a lapok egyenként jelennek meg rajta.

⚠️ **Ebből következik, hogy a 2026-os archiválás is korán jön**, nem a legvégén, ahogy eredetileg
terveztük. Az archívum a `forgatag.ro/forgatag2026` mappába kerül; ehhez egy újraépítés kell
`/forgatag2026` alapúttal (a belső hivatkozások ma gyökérből indulnak), plusz egy kereső-cserés kör
a kézzel beírt abszolút útvonalakra. ~Fél nap, egyszer.

## Adósságok

1. ~~`public/css/tema.css` ideiglenes~~ — **törlesztve 2026-10-21** (a fájl törölve, nincs rá hivatkozás).
2. **Hiányzik a világos/sötét váltó** a beküldő űrlapon (2026-ban volt). Pótlás a `/tema` körében.
   Figyelem: az átvett témában **csak sötét színek vannak** — a világoshoz színkészlet is kell.
3. **A koordinátor nem látja a fotót** olyan felületen, ahol még nincs megoldva — új felület
   építésekor ellenőrizni.
4. **Export, import és helyszín-lap** még nem létezik; amikor megépül, **kategóriagazda-fiókkal
   végig kell próbálni**, a közvetlen címet is begépelve.
5. **Élő hiba:** a beküldő űrlap extra mezői olyan kategória-slugra is hivatkoznak, ami 2026-ban
   megszűnt (`kerekasztal-beszelgetesek`). Ma csendben nem ad extra mezőket.
6. **A téma fájljai ma külön tárban vannak** (`public/arculat/`), nem a médiatárban. Amikor a
   Curator (2/d) megépül, el kell dönteni, hogy az „Arculat” gyűjteménybe költöznek-e — egy forrás.

## Amit a mentés leltára felszínre hozott

- **Kategóriák: 23, nem 24.** Öt megszűnt, négy új jött.
- **Egyetlen helyszínnek sincs GPS-koordinátája** (46/46 üres).
- **A 12 hír egyikének sincs román szövege.**
- **A program román címe két helyen van**, és 3 programnál eltér — importkor tisztázni kell.
- **A jóváhagyási és változási napló üres** — 2026-ban nem írt bele semmi.
- **A mentés hitelesítő adatokat tartalmaz** (Directus-tokenek nyílt szövegben) és személyes adatot.
  Ne kerüljön felhőbe szinkronizált mappába.
- **A NAS napi mentése hibás volt** (`pg_dump | gzip` — a sikert a gzip adta vissza). A mostani
  mentés ép, de a régebbiekre nem lehet támaszkodni.

## Nyitott kérdések — ne döntsd el magad

- A 2027-es fesztiválnapok pontos dátumai és az előkészítő napok száma.
- A valódi kategóriagazdák és jóváhagyók személye.
- ~~A médiatár megoldása~~ — **eldöntve 2026-10-21**, lásd a `CLAUDE.md` 2/d pontját.
  Röviden: Curator + saját mezők; a licenc **csak a beküldött képeknél kötelező**, az adminban
  feltöltött kép saját forrás; a jóvá nem hagyott beküldött fotó zárt tárban marad.
- Napi automatikus adatbázis-mentés: **be kell állítani, és egyszer vissza is kell tölteni.**
  Nem elég beállítani — lásd a NAS tanulságát.
