# Prompt Code-nak — 01. Próbatelepítés

> **Használat:** másold be az alábbi szöveget Code-nak. Minden adat ki van töltve.

---

Új projekt indul. A munkamappa: `E:\Work\mysites\forgatag.ro\utils\forgatag-2027`

**Először olvasd el ebben a mappában a `CLAUDE.md`-t és az `ATALLAS-TERV.md`-t.** Minden döntés ott van,
ne nyisd újra őket.

## Amit tudnod kell a helyzetről

- A szomszédos `..\forgatag-os` mappa a **2026-os rendszer, befagyasztva**. Onnan **csak olvashatsz**,
  oda **semmit nem írsz**. Most nem is kell belőle semmi.
- A cél tárhely: **cPanel osztott tárhely, PHP 8.4, MySQL, NINCS SSH és NINCS Terminal.**
  A kijuttatás a cPanel **Git Version Control** felületén történik, `.cpanel.yml`-lel.
- **Te a szerveren semmit nem tudsz futtatni.** Minden szerveroldali lépést a felhasználó kattint,
  a cPanel felületén. A te dolgod: a kódot előkészíteni, és **pontosan megmondani, mit kattintson.**
- Jelszót nem gépelsz. Az adatbázis jelszavát a felhasználó írja be.

## A mappa a szerveren — kötelező szabály

A tárhelyen **minden év a saját mappájában él a `public_html` alatt** (`forgatag2026`,
`forgatag2025` stb.), és a gyökér `.htaccess` irányítja a `forgatag.ro`-t az aktuális év mappájába.
**Ezt a mintát tartjuk.**

- Az alkalmazás helye: **`public_html/forgatag2027`**
- A webgyökér (aldomain docroot): **`public_html/forgatag2027/public`**
- Az átkapcsolás a legvégén annyi lesz, hogy a gyökér `.htaccess` a `forgatag2026` helyett a
  `forgatag2027/public` mappára mutat. **Ezt MOST NE csináld meg** — a 2026-os oldalhoz a fejlesztés
  alatt nem nyúlunk.

🔴 **Biztonsági következmény, amit kötelező kezelni.** Mivel az alkalmazás a `public_html` alatt van,
a `.env`, a `vendor/` és a `storage/` elvileg elérhető a webről — a `forgatag.ro/forgatag2027/.env`
kiadná az adatbázis jelszavát.

1. A `public_html/forgatag2027/` mappába kerüljön egy `.htaccess`, ami **mindent tilt, kivéve a
   `public/` almappát**.
2. **Telepítés után ellenőrizd, ne csak feltételezd.** A felhasználóval nyittasd meg:
   - `forgatag.ro/forgatag2027/.env` → **403 vagy 404 kell legyen, NEM a fájl tartalma**
   - `forgatag.ro/forgatag2027/vendor/` → ugyanígy

   Ha bármelyik kiadja a tartalmat, az súlyos hiba: **állj meg és jelentsd.**

## Ez egy MÉRÉS, nem építkezés

**Semmit ne építs a tényleges rendszerből.** Nincs adatmodell, nincs űrlap, nincs publikus oldal.
Egyetlen kérdésre keressük a választ: **működik-e a cPanel Git-telepítés shell-hozzáférés nélkül.**
A cPanel dokumentációja ebben önmagával ellentmond, és az egész ütemterv ezen áll.

## A feladat két szakaszból áll — a sorrend kötelező

### A szakasz — a csatorna mérése (ELŐSZÖR EZ)

Cél: kiderüljön, hogy a „Deploy HEAD Commit" egyáltalán lefut-e. Laravel nélkül, hogy egyetlen
változót mérjünk.

1. `git init` a `forgatag-2027` mappában, `main` ág.
2. Hozz létre **mindössze két fájlt**:
   - `proba.txt` — egyetlen sor szöveggel
   - `.cpanel.yml` — a telepítési feladat **egyetlen dolgot csináljon**: másolja a `proba.txt`-et a
     cél mappába, és írjon mellé egy `telepitve.txt`-et a pontos időbélyeggel.
     Használj **abszolút útvonalakat** (`/bin/cp`, `/bin/date`) — a cPanel példái is így írják, és a
     `PATH` nincs dokumentálva.
3. Kösd a távoli tárolóhoz és told fel:
   **`https://github.com/bszakacs/forgatag-2027.git`**
4. **Állj meg, és írd le a felhasználónak pontosan, mit kattintson a cPanelben:**
   - hol veszi fel a tárolót a Git Version Control alatt (klónozás a fenti címről),
   - **Repository Path: `public_html/forgatag2027`** — lásd alább a mappa-szabályt,
   - hol van a „Deploy HEAD Commit" gomb,
   - és **hol nézi meg az eredményt**: létrejött-e a `telepitve.txt`, illetve a naplót a
     `/home/<felhasználó>/.cpanel/logs/` alatt.
   - Figyelmeztesd: a klónozás csak **nem létező vagy üres** mappába megy. A `forgatag2027`
     mappát NE hozza létre előre.

**Ha az A szakasz elbukik, ÁLLJ MEG.** Ne kezdj kerülőutat keresni magadtól. Írd le, pontosan mi
történt és mit mondott a napló, és várd meg a döntést. A B szakasz ilyenkor értelmetlen.

### B szakasz — csak az A sikere után

1. **Docker-fejlesztőkörnyezet**: `docker-compose.yml` PHP 8.4 + MySQL konténerrel.
   A verziókat a tárhellyel azonosra pinneld, ne „latest"-et írj. A MySQL/MariaDB pontos verzióját
   a felhasználó meg tudja nézni a cPanel főoldalán — **kérdezd meg, ne találgass.**
   Az `ext-intl` legyen benne a PHP-képben (a Filament kötelező igénye).
2. **Laravel 13** telepítése a mappába, majd **Filament 5** panel (`/admin`).
3. Egy admin-felhasználó létrehozása (a jelszót a felhasználó adja meg, te ne találj ki jelszót).
4. **Ellenőrizd helyben**, a Dockerben: jön-e a Laravel kezdőlap és a Filament belépőképernyő.
   Csak ezután menj tovább.
5. **A `vendor/` mappa KERÜLJÖN BE a tárolóba** ennél a próbánál. Indok: nem tudjuk, fut-e a
   `composer` a szerveren, és most nem azt mérjük. Egy változót mérünk egyszerre.
6. Bővítsd a `.cpanel.yml`-t: fájlmásolás, majd `php artisan migrate --force`, `filament:optimize`,
   `optimize`. **Minden lépés abszolút útvonallal.** Ha a `php` útvonala bizonytalan, írd oda a
   `/usr/local/bin/php` és az `ea-php84` változatot is kommentben, hogy a felhasználó tudja cserélni.
7. Készíts egy `.env.szerverre.txt` mintafájlt: minden kitöltve, **az adatbázis jelszava helyén
   placeholder**. A felhasználó ezt másolja majd `.env` néven a szerverre, a Fájlkezelőben.
   A `.env` **nem kerülhet a tárolóba** — ellenőrizd a `.gitignore`-t.
8. **Állj meg újra, és írd le kattintásról kattintásra**, mit csináljon a felhasználó:
   - az aldomain létrehozása és a gyökere a `public_html/forgatag2027/public` mappára,
   - a `.env` felvitele a Fájlkezelőben,
   - a jogosultságok, ha kellenek,
   - és mit kell látnia, ha sikerült: a Filament belépőképernyőt.

## Amit mindenképp mérj és jelents

| Kérdés | Miért |
|---|---|
| Lefutott-e a „Deploy HEAD Commit" shell nélkül? | Ezen áll az egész terv |
| Aktív-e az **`ext-intl`**? | Enélkül a Filament el sem indul |
| Aktív-e a `proc_open`? | Letiltva az e-mail-küldés is elhalhat |
| Milyen MySQL/MariaDB verzió fut? | A Docker-környezetet ehhez kell igazítani |
| Futott-e `php` a telepítési lépésben? | Ha nem, a migrációra tartalékút kell |

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Néma hiba tilos.** Ha valami nem sikerült, azt mondd ki — ne jelents sikert azért, mert a
  parancs hibaüzenet nélkül lefutott.
- A felhasználó nem fejlesztő. A neki szóló lépéseket magyarul, kattintásszinten írd le.
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló kattintási lista.
