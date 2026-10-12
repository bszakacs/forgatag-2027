# Vásárhelyi Forgatag 2027 — Forgatag OS v2

Fesztivál-operációs rendszer a Vásárhelyi Forgataghoz (magyar közösségi fesztivál,
Marosvásárhely/Târgu Mureș). **A felhasználó (Portik) magyarul dolgozik és nem fejlesztő** —
magyarázz laikusbarátan, tömören, magyarul. Döntéskérő dokumentumot ne írj: ahol részletkérdés
merül fel, döntsd el a legkonzervatívabb irányba, csináld meg, és egy sorban írd le, mit döntöttél.

> **Ez egy ÚJ projekt.** A 2026-os rendszer a `../forgatag-os` mappában él, **befagyasztva**.
> Onnan csak olvass, és csak azt, amit ez a dokumentum felsorol. Oda **ne írj**.

---

## 1. Kanonikus tények (SOHA nem kitalált adat)

- **Fesztivál: 2027. augusztus 25–29.** (szerda–vasárnap). Szervezői adat. Konfigurálható mezőként
  kezelendő, nem beégetve. Az előkészítő napok számát a szervezők adják meg — a napok egyetlen
  forrása a `seed/festival-days.json` marad.
- **A fesztivál román neve: `Vâltoarea Mureșeană`.** Rövid alak: `Vâltoarea`.
  **Ragozás:** alanyeset `Vâltoarea Mureșeană` · elöljárószó után `Vâltoare` (*la Vâltoare*,
  *în Vâltoare*) · birtokos `Vâltorii Mureșene`. **A `Vâltoarei` alak MINDIG hibás.**
  **Csapda:** az `Ebforgatag` önálló programnév, tartalmazza a „forgatag" szót — SOHA nem cserélendő.
  Kötött fordítások egyetlen forrása: `seed/do-not-translate.json`.
- **A román nyelvben nincs `ő` és `ű`** — ha ilyen betű van román szövegben, az bent maradt magyar szó.
  Ez a leggyorsabb hibakereső heurisztika a fordításokra.
- A forgatag.ro-n futó **webshop (WooCommerce) marad**, ahhoz nem nyúlunk.

## 2. Architektúra — eldöntött, ne nyisd újra

**Egyetlen Laravel-alkalmazás a cPanel tárhelyen.** Benne: publikus oldal, admin és az összes
beküldő űrlap, egyetlen MySQL adatbázissal.

| Réteg | Döntés |
|---|---|
| Keretrendszer | **Laravel 13** (PHP 8.4) |
| Admin | **Filament 5** — `/admin`, ebbe olvad a `/tema`, `/szerkeszto`, `/terkep-szerkeszto` |
| Adatbázis | **MySQL a tárhelyen** — ez az egyetlen igazság |
| Publikus sablonok | **Blade** — keretfájl + fejléc- és lábléc-partial |
| Interaktivitás | **htmx** (szűrés, lapozás) + vékony **Alpine** (menü, legördülő) |
| Fejlesztés | **Docker** — PHP és MySQL a tárhellyel AZONOS verzióval |
| Kijuttatás | **cPanel Git Version Control** + `.cpanel.yml` |

**Kizárva (tulajdonosi döntés):** VPS · WordPress · Drupal · PrestaShop · Node a szerveren ·
Directus · Inertia/React/Vue · statikus oldalgenerálás.

**Az átállás terve, az ütemterv és a tárhely adottságai:** `ATALLAS-TERV.md`.
**Indoklás és a 11 megvizsgált alternatíva (archív, csak olvasásra):**
`../forgatag-os/ALTERNATIVAK-VIZSGALAT-2026-09-10.md`.

### Amiért ez a felállás született — a négy orvosolt hiba

1. **Statikus oldal** → minden tartalomváltozás 15–30 perc és fájltömeg-feltöltés volt.
   **Most:** futásidejű kiszolgálás, mentés után azonnal kint.
2. **Két gép (NAS + tárhely)** → a helyi javítás nem jutott ki magától.
   **Most:** minden a tárhelyen; ami nincs feltolva, az nincs kint — nincs harmadik állapot.
3. **Négy admin belépési pont** → visszatérő 401-es hibák.
   **Most:** egy alkalmazás, egy belépés.
4. **Új beküldő aldomain NAS-beállítást igényelt** → **most:** tiszta cPanel-munka, ~15 perc.

## 3. Állandó szabályok

- **Token-szabály:** oldalra/komponensre szűkített `:root`-felülírás **TILOS**. Minden téma-tulajdonú
  token (szín, betű, forma, mozgás, firka) a `/tema`-ból publikált témából jön. Saját token-NÉV
  megtartható, ha a témára van kötve (`--ink:var(--fg)`). Téma nélkül a kiszolgálás álljon meg hibával.
- **A Filament témáját SOHA nem linkeljük a publikus lapra.** Behúzza a Tailwind preflightját, ami
  globálisan nullázza a margókat és a címsorstílusokat. A két CSS-világ szerkezetileg elkülönül:
  a Filament a saját vázát használja, a publikus oldal a mienket.
- **Négy-hely szabály:** ha egy funkciót kivezetünk, a felület, a séma, a tárolt érték és minden
  hivatkozás is menjen vele. Holt kód nem maradhat.
- **Néma hiba tilos.** Ha valami nem sikerül, az látszódjon — emberi nyelven a felületen, vagy
  hibával álljon meg. Sikert jelenteni úgy, hogy nem történt semmi: a projekt legdrágább hibafajtája.
- **Egy forrás:** ugyanaz az adat vagy szabály ne legyen két helyen leírva (státusz-szűrő,
  verzió-jelölő, rétegsorrend) — mert elcsúsznak.
- **Egy skála a rétegsorrendre (z-index)**, egy helyen. Ne foltozzuk egyedileg.
- **Kép csak egyszer vágódik:** a kép azon az arányon készüljön, amin megjelenik.
- **A mobil a fő nézet:** minden változást 375 px-en is ellenőrizni kell.
- **AI soha nem generál tényadatot** (dátum, idő, helyszín, ár, fellépő). Minden AI-kimenet emberi
  jóváhagyásig draft.
- **Jelszót nem gépelsz.** A cPanel- és adminjelszót a felhasználó írja be.
- **`elo.forgatag.ro` és minden belső cím:** publikus szövegbe, levélbe SOHA nem kerülhet.

## 4. Jogosultság — ez a legkockázatosabb rész

Szerepek: szuperadmin · programkoordinátor (jóváhagyó) · **kategóriagazda (SOR-SZINTŰ jog: csak a
saját kategóriája)** · fordító · arculat-szerkesztő · csak-olvasó.

⚠️ **A sor-szintű szűkítés felületenként hat** (lista, globális keresés, kapcsolat-kezelők, export,
import). Ha egyet kihagyunk, az adatszivárgás. **Kötelező: modell-szintű globális scope, NEM
felületenkénti felülírás.** Külön figyelem: a Filament `ImportAction` alapból nem ellenőriz
jogosultságot soronként.

## 5. Fordítás

HU a forrásnyelv, RO a fordítás. **Gépi fordítás Claude API-val**, hatóköre **CSAK a beküldött
programok** (cím, leírás, jegyinfó) — azok külsős szövegek. A saját tartalmainkat (hírek,
kategória- és helyszínnevek, menü) **kézzel** fordítjuk.

- Megjelenítési sorrend: `approved` → `reviewed` → `machine_translated`.
  **Az `outdated` KIMARAD** — ott a magyar szöveg látszik, mert a régi román téves adatot tartalmazhat.
- **Az emberi fordítás sérthetetlen:** a gépi futás nem írhat felül `reviewed`, `approved` és
  `outdated` sort sem.
- **Kötelező ellensúly:** az `outdated` sorok feltűnően látszódjanak a jóváhagyási sorban.
- **A fordítás NEM polimorf táblában él.** Gyűjteményenkénti fordítás-tábla, valódi idegen kulccsal.
  (A 2026-os rendszer egyetlen szabadszöveges `entity` oszlopot használt — emiatt nem lehetett a
  fordítást a program lapján megmutatni, és emiatt született minden kerülőút. Ezt most elkerüljük.)

## 6. Mi költözik át a 2026-os repóból

**Átvesszük** (`../forgatag-os`-ból, olvasásra):

| Mit | Honnan |
|---|---|
| Törzsadatok: kategóriák, helyszínek, szponzorszintek, napok, védett nevek | `seed/` |
| CSS-tokenrendszer, Amphora-arculat | `frontend/src/styles/` |
| Önhostolt betűk (Archivo, Instrument Serif, Tingler Print) | `frontend/public/fonts/` |
| Várstérkép SVG | `frontend/public/terkep/varterkep-vektor.svg` |
| Képanyag, galériák | `tooling/gallery-src/`, Directus-asset export |
| Firka-motívumok | `frontend/public/` |

**NEM vesszük át:** az Astro frontendet · az öt Node-API-t (`submit-api`, `volunteer-api`,
`olympics-api`, `escape-room-api`, `favorites-api`) · a `build-runner`-t · a Directus-séma eszközöket ·
a `scripts/deploy-nas.sh` és `publish-live.sh` szkripteket · a `docker-compose.yml`-t · a `HANDOFF.md`-t.

**Betűhiba, amit NE hozzunk át:** a Tingler Print csak 400-as vastagságban létezik; ha 700-asként
kérjük, Androidon körvonalas lesz. Mindenhol, ahol fejcímbetű van, 400 legyen, és `font-synthesis: none`.

## 7. A 2026-os év sorsa — EZ A LEGUTOLSÓ LÉPÉS

⛔ **A fejlesztés teljes ideje alatt a 2026-os oldalhoz NEM nyúlunk.** A `forgatag.ro` gyökerében él
tovább, változatlanul, amíg a 2027-es rendszer el nem készül.

Az archiválás és az átkapcsolás **ugyanaz az esemény, a legvégén**. Akkor:
`forgatag.ro/forgatag2026` almappába kerül (tulajdonosi parancs) · egy újraépítés kell
`/forgatag2026` alapúttal, mert a belső hivatkozások ma gyökérből indulnak · a kedvencek-gomb
kiveendő, mert a NAS-ra hívott · **a 2026-os programokat nem importáljuk**, néhányat csak tesztelésre.

## 8. Hol tartunk

**0. Próbatelepítés — KÉSZ (2026-10-12).** `https://proba.forgatag.ro/admin` él (Laravel 13 + Filament 5).
Tároló a szerveren: `/home/forgatag/public_html/forgatag2027`, az aldomain gyökere a `public/`.
Kijuttatás: `git push` → cPanel Git Version Control → **Update from Remote** → **Deploy HEAD Commit**.

**1. Adatmodell — kész helyben:** kategóriák, helyszínek (hierarchia + térképmezők), programgazdák
(+ kategória-pivot), programok (+ további kategóriák), időpontok, program-fordítások (valódi FK,
egyedi kulcs), szponzorszintek; `forgatag:torzsadatok` (csak üres táblába ír). A fesztiválnapok
NEM adatbázisban vannak: `App\Support\Festival` olvassa a `seed/festival-days.json`-t.
Később jön: médiatár (nyitott kérdés), szerepkörök + napló (4. fázis), szponzorok logóval.

### A tárhely mért adottságai és buktatói (próbatelepítés, 2026-10-12)

- **PHP:** a web a CloudLinux-féle 8.4.26-ot használja (fiókszintű „Select PHP Version”, `intl` be).
  A telepítési lépések az `ea-php84`-et (8.4.26, `intl` NINCS, `proc_open` tiltva) — oda nem kell.
  **MariaDB 10.6.27.** A Docker ezekre van pinnelve. A szerver LiteSpeed.
- **Bármilyen helyi változás a szerveri munkafán = inaktív Deploy gomb, vagy „could not contact the
  remote repository” az Update-nél** (félrevezető!). A git akkor is elutasít, ha a helyi módosítás
  betűre egyezik az érkezővel. Ezért: a cPanel által a `public/.htaccess`-be írt blokk betűre a
  tárolóban van; a `php.ini`, `.user.ini`, `.well-known`, `error_log` a `.gitignore`-ban.
  **A MultiPHP Managert erre az aldomainre NE használd** (beír a `.htaccess`-be).
- **A cPanel 0700-zal klónoz** → a `.cpanel.yml` minden telepítéskor 755/644-re állít.
- **A `.env` csak a `.gitignore`-t tartalmazó változat lehúzása UTÁN hozható létre** a szerveren.
- A gyökér `public_html/.htaccess` a létező fájlokat/mappákat átengedi → a `forgatag2027/.htaccess`
  (`Require all denied`, kivéve `public/`) kötelező. Mérve: `.env`, `vendor/`, `.git` → 403.
- A Deploy felülete „fut” állapotban ragadhat; a valóságot a `~/.cpanel/logs` napló mutatja.
- A `vendor/` és a Filament public-assetjei a tárolóban vannak (a szerveren `composer` nem fut).

## 9. Nyitott kérdések — ne döntsd el magad

- A 2027-es előkészítő napok száma és dátuma (a `seed/festival-days.json`-ban most csak az öt fesztiválnap van).
- A jóváhagyók személye; a kategóriagazdák névsora.
- Kik kapnak arculat-szerkesztő fiókot.
- Melyik médiatár-megoldás (a próbatelepítés után, valós képanyaggal mérve).
