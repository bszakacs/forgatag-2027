# Átállási terv — Forgatag OS v2 a cPanel tárhelyen

**Tulajdonosi döntés, 2026-09-10.** A fő adatbázis ÉS a beküldési platform a cPanel tárhelyen fut.
A NAS ezután **kizárólag fejlesztőkörnyezet**. Cél: egy új adatbeküldő aldomain (program,
szabadulószoba, bármi) felvétele **tiszta cPanel-munka** legyen, NAS/DSM-beállítás nélkül.

**Kizárva:** VPS · WordPress · Drupal · PrestaShop · Node a szerveren.
A 11 megvizsgált alternatíva és a kiütő ellenérvek: `../forgatag-os/ALTERNATIVAK-VIZSGALAT-2026-09-10.md`.

---

## 1. A tárhely adottságai (ellenőrizve, 2026-09-10)

| Adottság | Érték | Következmény |
|---|---|---|
| PHP | **8.4** | Laravel 13 (min. PHP 8.3) + Filament 5 fut rajta |
| SSH / Terminal | **nincs** | a telepítés nem parancssorból megy |
| Git Version Control | **van** | ez a telepítő csatorna — `.cpanel.yml` futtatja a lépéseket |
| Aldomain gyökere | **szabadon állítható** | a `public/` mappára mutathat, ahogy a Laravel kéri |
| Node.js | **nincs** | ezért nem futhat ott Directus és az öt Node-app |

## 2. A választott architektúra

**Egyetlen Laravel-alkalmazás**, benne a publikus oldal, az admin és az összes beküldő űrlap,
egyetlen MySQL adatbázissal.

| Réteg | Megoldás |
|---|---|
| Keretrendszer | Laravel 13 (PHP 8.4) |
| Admin | Filament 5 — `/admin`, ebbe olvad a `/tema`, `/szerkeszto`, `/terkep-szerkeszto` |
| Publikus sablonok | Blade — keretfájl + fejléc/lábléc partial, laponként egy sablon |
| Szűrés, lapozás | **htmx** — valódi adatbázis-lekérdezés, GET, gyorsítótárazható, a szűrés a címsorban |
| Apró mozgások | vékony Alpine-réteg (mobilmenü, legördülő, harmonika) |
| Médiatár | Filament + rácsos médiakezelő bővítmény, saját mezőkkel (jogtulajdonos, licenc, fotós, fókuszpont) |

**Node nincs a publikus oldal láncában:** a htmx és az Alpine egy-egy fájl a repóban, a CSS kézzel írt.
Node egyetlen esetben kell, a fejlesztői gépen: ha egyedi Filament-admin-témát építünk.

### Amit ez kivezet

Directus · `submit-api` · `volunteer-api` · `olympics-api` · `escape-room-api` · `favorites-api` ·
`build-runner` · a tartalom FTP-s publikálása · a DSM fordított proxyk · a NAS-os tanúsítványok ·
a séma-bot és a 2FA ütközése · az `elo.forgatag.ro` szivárgásának kockázata · a kétszeres
belépéskezelés, és vele a visszatérő 401-es hibacsalád.

### Amit megőrzünk

Az Amphora-arculatot és a tokenrendszert · a `/tema` logikáját · az adatmodellt · a sor-szintű
jogosultságot · a jóváhagyási sort · a fordítás-státuszokat · a `seed/` fájlokat mint egyetlen forrást.

## 3. Telepítés Terminál nélkül

1. A **fejlesztői gépen** (Dockerben) fut a `composer install --no-dev -o` és — ha kell — az
   eszközfordítás.
2. A `vendor/` és a lefordított eszközök **egy külön `deploy` ágba** kerülnek (a fejlesztői ágba nem).
3. `git push` → a cPanel **Git Version Control** lehúzza, majd lefuttatja a `.cpanel.yml` lépéseit:
   `php artisan migrate --force` · `filament:optimize` · `optimize` (config- és útvonal-gyorsítótár).
4. **Tartalék, ha a `.cpanel.yml` nem futtathat PHP-t:** jelszóval védett belső karbantartó útvonal,
   vagy a migráció SQL-ként a phpMyAdminban.

**Figyelem:** a cPanel Fájlkezelő nem tud szimbolikus linket létrehozni, ezért a `storage:link`
vagy a `.cpanel.yml`-be megy, vagy a feltöltések eleve a `public/` alá kerülnek.

**Ütemezett futások:** ha van cPanel Cron Job, a Laravel ütemezőjét érdemes percenként hívni —
ebbe kerül a fordítási futás és a karbantartás. Kötelező igény nincs rá.

## 4. Ütemterv — ebben a sorrendben, mert korán ad hasznot

**0. Próbatelepítés** — lásd a 7. pontot. Ez az első commit. *1–2 nap.*

**1. Adatmodell és adatbázis.** A séma Laravel-migrációkban, MySQL-re, tiszta lappal. A 2026-os
programokat **nem** importáljuk; a törzsadatok (kategóriák, helyszínek, szponzorszintek, napok) a
`seed/` fájlokból töltődnek be egy paranccsal. A fordítás gyűjteményenkénti táblában, valódi
idegen kulccsal — nem polimorf szerkezetben. *1–1,5 hét.*

**2. A beküldő űrlapok.** Beküldés, szabadulószoba, kedvencek (+ olimpia, önkéntes, ha kell) →
az alkalmazás publikus útvonalai. Az adaptív, kategóriánkénti űrlaplogika és a szerver-oldali
validáció a `seed/` konfigurációból jön. Értesítés a tárhely levelezőjén.
**Ettől lesz igaz a kért állapot:** új aldomain = cPanel + egy útvonal. *1–1,5 hét.*

**3. Hírek és programok futásidőben.** Programkereső, napi nézet, hírlista, részletlapok Blade-ből,
MySQL-ből, htmx-es szűréssel. **Ettől szűnik meg az élesítési késés.** *2–3 hét.*

**4. A Filament admin.** Gyűjtemények, szerepek, jóváhagyási sor, médiatár, fordítás nyelvfülekkel,
`/tema` és `/szerkeszto` mint a panel lapjai. *2–3 hét.*

**5. A két egyedi képernyő.** Témaszerkesztő élő előnézettel és térkép-szerkesztő.
Az előnézet teljesen kliensoldalon fut, szerver-körfordulás nélkül. A térképen a húzás Pointer
Events + `getScreenCTM().inverse()` — nem bővítmény, mert a kész bővítmények csak listát rendeznek át.
*1–1,5 hét.*

**6. A maradék lapok.** Rólunk, fesztiválrend, galéria, támogatók. Itt pótoljuk a szerkeszthető
szövegek lefedettségét is: ma a fesztiválrend lapján 25 kulcs a szöveg 18%-át fedi, a többi
beégetett magyar. Mivel a sablonokat úgyis újraírjuk, ez majdnem ingyen van. *1 hét.*

**7. Átkapcsolás és archiválás.** Lásd a 6. pontot. *1 hét.*

**Összesen: 9–13 hét munkával töltött idő, naptárban 3–4 hónap.**
**A határidőt nem a fesztivál szabja meg, hanem a beküldés:** a programok tavasszal érkeznek, tehát
a beküldő űrlapnak **2027 áprilisára** élnie kell.

## 5. Kockázatok, amiket előre kimondunk

- **Ez a háttérrendszer újraépítése.** Hetek munkája, több körben. Nem finomhangolás.
- 🔴 **A `.cpanel.yml` futtathatósága a fő ismeretlen.** Ezért a 0. fázis nem tervezés, hanem mérés.
- **Lemezkvóta.** A képanyag a Directus asset-tárából a tárhely `storage/`-jába kerül.
  A 2025–2026-os anyag nem kevés — a kvótát előre meg kell nézni.
- **Imunify360.** A tárhely egyszer már blokkolta a párhuzamos FTP-kapcsolatokat. A Git-alapú
  telepítés ezt kikerüli, de a whitelistet érdemes megtartani.
- **Egy kosárban minden.** Eddig a NAS kiesése csak az adminisztrációt vitte; ezután a tárhely
  kiesése mindent visz. Cserébe a tárhely megbízhatóbb és nem otthoni áramon lóg.
  **Napi automatikus adatbázis-mentés kötelező** (cPanel backup + letöltés a NAS-ra).
- **Séma-elcsúszás.** A Laravel — a Symfony/Doctrine-nal ellentétben — nem tudja kimutatni, ha a kód
  és az adatbázis eltér; ott derül ki, amikor egy lekérdezés hibára fut. **Ellensúly:**
  `migrate --force` a `.cpanel.yml`-ben, és évnyitáskor egy séma-pillanatkép a repóba.
- **Sor-szintű jog.** A szűkítés felületenként hat; egy kihagyott felület adatszivárgás.
  **Modell-szintű globális scope kötelező**, nem felületenkénti felülírás.

## 6. A 2026-os év archiválása — EZ A LEGUTOLSÓ LÉPÉS

⛔ **A fejlesztés teljes ideje alatt a 2026-os oldalhoz NEM nyúlunk.** A `forgatag.ro` gyökerében él
tovább, változatlanul. Az archiválás és az átkapcsolás **ugyanaz az esemény, a legvégén**.

**Átépíteni nem kell: ami ma kint van, az már maga a befagyasztott év**, arculatostul, statikus
HTML-ként.

- **Helye: `forgatag.ro/forgatag2026`** — almappa (tulajdonosi parancs, nem mérlegelendő).
- **Egyetlen teendő:** a belső hivatkozások gyökérből indulnak (`/programok`, `/nap/<dátum>`,
  `/assets/…`), tehát almappából eltörnének. **Egy újraépítés kell `/forgatag2026` alapúttal**, plusz
  egy kereső-cserés kör a kézzel beírt abszolút útvonalakra, amiket az építő nem lát (ilyen volt a
  favicon hét lapon). ~Fél nap, egyszer.
- **A kedvencek-gomb kiveendő** az archívumból: a NAS-ra hívott, ott némán hibázna.
- **Évenként ismételhető minta:** mappa + adatbázis duplázása, a lezárt év adminja kikapcsol
  (`ARCHIVE_MODE`), az élő rendszer tiszta lappal indul.

## 7. Első konkrét lépés — a próbatelepítés

Egy **üres Laravel 13 + Filament 5** alkalmazás felvitele cPanel Git Version Controllal egy
próba-aldomainre (pl. `proba.forgatag.ro`), a `.cpanel.yml`-lel együtt. **Ez az új repó első commitja.**

🔴 **Ezen áll az egész terv.** A cPanel dokumentációja önmagával ellentmond abban, hogy a
„Deploy HEAD Commit" működik-e shell-hozzáférés nélkül: az egyik lapja szerint shell nélkül csak
létrehozni, klónozni, törölni és megtekinteni lehet a tárolókat; a másik kattintható felületként
írja le a telepítést.

**Amit a próba mér:**

| Kérdés | Ha nem |
|---|---|
| Lefut-e a „Deploy HEAD Commit" shell nélkül? | A teljes kijuttatási lánc átalakul, az ütemterv újraírandó |
| Fut-e a `composer` a telepítési lépésben? | A `vendor/` mappa megy a repóba (legitim: a Drupal is így szállít) |
| Aktív-e az **`ext-intl`**? | A Filament nem indul el — a tárhelyszolgáltatónál kell kérni |
| Aktív-e a `proc_open`? | Bizonyos funkciók, köztük az e-mail-küldés, elhalhatnak |
| Állítható-e az aldomain gyökere a `public` almappára? | Más útvonal-elrendezés kell |

Ha a próba lefut és a Filament belépőképernyő megjelenik, **a terv többi része mérnöki munka.**
Ha nem fut le, az egy nap alatt kiderül, nem három hét múlva.
