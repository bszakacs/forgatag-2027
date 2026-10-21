# Vásárhelyi Forgatag 2027 — Forgatag OS v2

Fesztivál-operációs rendszer a Vásárhelyi Forgataghoz (magyar közösségi fesztivál,
Marosvásárhely/Târgu Mureș). **A felhasználó (Portik) magyarul dolgozik és nem fejlesztő** —
magyarázz laikusbarátan, tömören, magyarul. Döntéskérő dokumentumot ne írj: ahol részletkérdés
merül fel, döntsd el a legkonzervatívabb irányba, csináld meg, és egy sorban írd le, mit döntöttél.

> **Ez egy ÚJ projekt.** A 2026-os rendszer a `../forgatag-os` mappában él, **befagyasztva**.
> Onnan csak olvass, és csak azt, amit ez a dokumentum felsorol. Oda **ne írj**.

---

## 1. Kanonikus tények (SOHA nem kitalált adat)

- **Fesztivál: 2027. augusztus 25–29.** (szerda–vasárnap). Szervezői adat, soha nem beégetve.
  **A fesztiválnapok az adatbázisban élnek és az adminban szerkeszthetők** — a feliratokat a
  rendszer számolja a dátumból. Az előkészítő napok számát a szervezők adják meg.
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

## 2/b. A `seed/` fájlok szerepe — KEZDŐ TARTALOM, nem igazságforrás

**Semmilyen szervezői adat nem él fájlban.** A `seed/` fájlok (fesztiválnapok, kategóriák,
helyszínek, szponzorszintek, védett nevek) **egyszer töltődnek be** az adatbázisba a projekt
indulásakor. Onnantól **az adatbázis az igazság**, és minden az adminban szerkeszthető.

**Miért más ez, mint 2026-ban:** akkor három külön program olvasta ugyanazt (Astro build,
beküldő űrlap, séma-eszköz), és a JSON-fájl volt az egyetlen közös pont. Most **egy alkalmazás van
és egy adatbázis** — az indok megszűnt.

**Következmény:** ha egy adat évről évre vagy menet közben változhat, akkor **adatbázisba kerül**,
nem fájlba. Kódban csak az marad, ami a rendszer szerkezete (státuszok, szerepkörnevek, útvonalak).

### DE: az adatbázisban lenni ≠ külön menüpont

⛔ **Az admin menüje szűk és tiszta marad.** Csak az kap saját menüpontot, amit **rendszeresen
szerkesztenek**: programok, hírek, helyszínek, kategóriák, médiatár, támogatók, felhasználók.

**Minden más EGYETLEN „Beállítások" lapra megy.** Ez a lap a fesztivállal kapcsolatos minden
beállítás otthona — nem keletkezik több menüpont, ahogy a rendszer nő.

**A lap felépítése: tematikus blokkokra osztva.** Egy blokk = egy téma, saját címmel, egymás alatt.
Például:

| Blokk | Mi van benne |
|---|---|
| Fesztivál alapadatai | év, kezdő és záró dátum, előkészítő napok száma, nyitónap, a fesztivál neve HU/RO |
| Programbeküldés | nyitva/zárva kapcsoló, a zárt állapot üzenete, határidő (csak nap) |
| Szabadulószoba | saját mezői: időpont-sávok, koordinátor e-mail-címe, nyitva/zárva |
| Értesítések | koordinátorok e-mail-címei témánként (KI kapja). Feladó NINCS itt: az a szerver SMTP-fiókja (.env). Egy funkció = egy cím. |

**Ha egy blokk új funkcióval jön, ide kerül, nem új menüpontba.**

**Amikor már sok lesz a mező, akkor — és csak akkor — vezetünk be füleket** ezen az EGY lapon belül.
Addig egyszerű, egymás alatti blokkok. **Új menüpontot akkor se.**

⚠️ A `/tema` **külön marad**, mert saját szerepköre van (arculat-szerkesztő). Aki a színeket állítja,
az ne tudja lekapcsolni a programbeküldést.

**Konkrétan a fesztiválnapok:** NEM külön gyűjtemény és NEM külön menüpont. A Beállítások lapon
megadott dátumokból a rendszer **számolja** a napokat, és azok határolják be, mely dátumokra lehet
programot beküldeni. A napokhoz nincs mit szerkeszteni — az esemény kap dátumot, és kész.

**Ha új menüpontot akarsz felvenni, előbb kérdezd meg.** Egy zsúfolt admin ugyanolyan hiba, mint
egy hiányzó funkció.

**Rokon dolgok menücsoportba kerülnek, nem külön menüpontokba.** Például a „Felhasználók" csoport
alatt az admin-felhasználók és a programgazdák. Egy csoport két eleme nem két menüpont.

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
| Kezdő tartalom: kategóriák, helyszínek, szponzorszintek, napok, védett nevek — **egyszeri betöltéshez**, lásd a 2/b pontot | `seed/` |
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
egyedi kulcs), szponzorszintek; `forgatag:torzsadatok` (csak üres táblába ír; a beállításoknál
csak a hiányzó blokkot hozza létre).

**Beállítások (2/b):** `settings` tábla, blokkonként egy sor (`festival`, `submission`,
`notifications`), kezdőérték: `seed/settings.json`. Lap: `App\Filament\Pages\Beallitasok`
(új blokk = új Section + a `BLOKKOK` lista + a seed). Olvasók: `App\Support\Settings`,
`App\Support\Festival` (a napokat SZÁMOLJA: kezdő − előkészítő napok … záró),
`App\Support\Submission::isOpen()` (kapcsoló ÉS határidő — az űrlap és a szerver is ezt kérdezi).
Értesítési témák: `App\Enums\NotificationTopic` (a téma kód, a címek a Beállításokban).
Admin-lapok közös alapja: `App\Filament\Resources\Resource`; a gombok magyar címe az `AppServiceProvider`-ben.

Később jön: médiatár (nyitott kérdés), szerepkörök + napló (4. fázis), szponzorok logóval.
**A 2/b elv miatt még átköltözik:** a védett nevek (`do-not-translate.json` → tábla, a fordítási fázisban).

**2. Programbeküldő űrlap — kész helyben (2026-10-12).** `/bekuldes` (belépés: `/bekuldes/belepes`).
Mezők/szabályok/hibaszövegek a 2026-os `submit-api`-ból; a leírás EGY helyen: `App\Support\SubmissionForm`
(űrlap + szerver), validáció+mentés: `App\Services\ProgramSubmission`, szövegtisztítás: `App\Support\TextCleaner`
(párja: `public/js/bekuldes.js`). Belépés: külön `bekuldo` őr (`program_managers`), a Filament `authGuard('web')`.
Fotó: nem nyilvános tár (`storage/app/private/bekuldesek/`, `program_items.photo_path`) — ELŐBB a fotó, és
ha nem megy, a program nem jön létre (+ koordinátori levél). Levelek: `App\Support\BekuldesLevelek` →
`Notifier` (soha nem dob: a mentés utáni levélhiba nem lehet „sikertelen beküldés”). Jóváhagyási levél:
`ProgramItem::updated` (draft/pending → published). Korlát: `throttle:bekuldes` (30/óra/IP), belépés 5 hiba → 15 perc.
A publikus lapok téma-tokenjei IDEIGLENESEN `public/css/tema.css` (amíg a /tema nem publikál).
Tesztek: `tests/Feature/ProgramBekuldesTest.php` — a `vendor/` dev-csomagok nélkül van a tárolóban, ezért a
tesztet a konténerben egy másolatban futtatjuk (`/tmp/proj`, ott `composer install`), a `vendor/`-hoz nem nyúlunk.
**Élesben mérve (proba.forgatag.ro, 2026-10-12):** a levél kimegy (SMTP `noreply@forgatag.ro`,
`mail.forgatag.ro:465`, `MAIL_SCHEME=smtps` a szerveri .env-ben; a koordinátori levél megérkezett) ·
9,99 MB-os fotó felmegy, 2,5 mp (PHP: `upload_max_filesize=12M`, `post_max_size=16M`, cPanel
„Select PHP Version → Options”) · a belépési zárolás működik (a 6. próbára zár). **Csapda:** a szerveri
.env-ben a `MAIL_MAILER` csak EGYSZER szerepeljen — a Laravel az ELSŐ előfordulást veszi.
A jóváhagyási levél: élesben mérve 2026-10-21 (lásd a programlista bekezdését).

**„Felhasználók” menücsoport — kész helyben (2026-10-13).** Két lap: `Users\UserResource`
(admin-felhasználók; saját magát senki nem törölheti) és `ProgramManagers\ProgramManagerResource`
(programgazdák: név, e-mail, jelszó, aktív, kategóriák). Új programgazda → **üdvözlő levél** a belépési
címmel, a jelszóval és a kategóriák linkjeivel (`BekuldesLevelek::udvozlo`); üres jelszómezőnél a rendszer
generál (`ProgramManager::generatePassword`). Ha a levél nem megy ki, a koordinátor tartós figyelmeztetést
lát a jelszóval. **Elfelejtett jelszó:** `/bekuldes/jelszo` (`JelszoController`), külön jelszókezelő
(`program_managers`) és külön token-tábla; a válasz nem árulja el, létezik-e a cím; a link 60 percig él,
egyszer használható. Az ideiglenes `forgatag:programgazda` kivezetve (kód, `.cpanel.yml`, minta-.env;
a szerveri .env `PROGRAMGAZDA_*` sorait a felhasználó törli).

**Programlista + jóváhagyási sor — kész helyben (2026-10-13).** `ProgramItems\ProgramItemResource`
(`/admin/programok`, a menü első pontja, jelvény: hány vár jóváhagyásra). Fülek: Teendő (alap) ·
Közzétéve · Mind; szűrők: állapot, kategória (elsődleges), helyszín, nap, fotó, elavult fordítás.
Mobilon csak cím (alatta kategória + első nap) és állapot; a sor maga a link.
**Lépések EGY helyen: `App\Services\ProgramReview`** — draft →(Jóváhagyásra küldés)→ pending_review
→(Jóváhagyás)→ published; **Visszaküldés javításra** (draftból és pending-ből, kötelező indok, levél a
beküldőnek a javítás linkjével) → draft. Jóváhagyni csak pending_review-ból lehet, és csak ha nincs
akadály (`approvalBlockers`: fotó + a FÁJLJA is megvan, jog-nyilatkozat, kategória, helyszín, időpont) —
a szerver is ellenőrzi, nem csak a gomb szürke. A jóváhagyási levelet továbbra is a modell `updated`
eseménye küldi; az eredménye (`$approvalMailSent`) a gomb visszajelzésében látszik (kiment / NEM ment ki).
**Napló:** `program_review_events`, APPEND-ONLY (a modell tiltja a módosítást/törlést); a beküldés és a
beküldő javítása is ide ír. A visszaküldés indokának egyetlen helye ez — a beküldő a „Saját
beküldéseim” lapon ebből látja (`ProgramItem::openReturn`).
**Fotó az adminnak:** `/admin/programok/{id}/foto` (`Admin\ProgramFotoController`), a panel
`authenticatedRoutes`-ában — csak belépett admin. **Elavult fordítás:** piros jelvény a listában, piros
figyelmeztetés + kiemelt sor a program lapján (a fordítás szekció előkészítve, ma még üres).
Szerkesztés és új program felvitele az adminban: még NINCS (későbbi kör).
**Élesben mérve (proba.forgatag.ro, 2026-10-21):** a teljes út (beküldés → jóváhagyásra küldés →
jóváhagyás) működik; a fotó az admin program-lapján látszik; a gomb zöld visszajelzést adott, és a
jóváhagyási levél pár másodperc alatt megérkezett a programgazda postafiókjába.

### Adósságok — nyilvántartva, később szabályt sértenének (tulajdonosi rögzítés, 2026-10-12)

1. **`public/css/tema.css` IDEIGLENES.** Amikor a /tema elkészül, ennek **nyomtalanul** el kell tűnnie:
   minden szín a publikált témából jön (token-szabály). Ne maradjon második forrás.
2. **A beküldő űrlapról hiányzik a világos/sötét váltó** (2026-ban volt ◐). Ez visszalépés — a /tema
   fázisában pótolni kell.

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

### Feljegyezve a későbbi fázisokra (tulajdonosi rögzítés, 2026-10-13)

- **KÖVETKEZŐ LÉPÉS: sor-szintű jog (kategóriagazda) — KÜLÖN lépés, mert szerepkörök is kellenek hozzá.**
  Ma minden admin-fiók teljes jogú, ezért a programlista most nem szivárogtat. A szűkítés helye: a
  `ProgramItem` modell **globális scope**-ja (a lista, a globális kereső, a kapcsolat-kezelők és a
  fotó-útvonal mind a modellen át kérdez — külön kód nélkül szűkülnek). A scope CSAK a `web` őr
  kategóriagazdájára hathat — a beküldő (`bekuldo` őr) saját programjait nem szűkítheti el. Export/import
  ma nincs; ha lesz, az import soronként is ellenőrizzen (a Filament `ImportAction` alapból nem teszi).
- **Napi automatikus adatbázis-mentés — ÁPRILISIG KÖTELEZŐ** (`ATALLAS-TERV.md` kockázati pont). Amint
  valódi beküldések érkeznek, működnie kell (cPanel backup + letöltés a NAS-ra). Még nem sürgős, de
  nem csúszhat a beküldés megnyitása utánra.

## 9. Nyitott kérdések — ne döntsd el magad

- A 2027-es előkészítő napok pontos száma (most 2 — „kettő biztos”; a Beállításokban átírható).
- A koordinátorok értesítési címei (a Beállításokban üresen állnak — nem találunk ki címet).
- A jóváhagyók személye; a kategóriagazdák névsora.
- Kik kapnak arculat-szerkesztő fiókot.
- Melyik médiatár-megoldás (a próbatelepítés után, valós képanyaggal mérve).
