# Vásárhelyi Forgatag 2027 — Forgatag OS v2

> ## 📍 ELŐSZÖR AZ `ALLAPOT.md`-T OLVASD EL
> Abban van, hol tartunk, mi készült el, mi a következő lépés, és mik a nyitott pontok.
> **Ez a fájl a szabályokat tartalmazza, az `ALLAPOT.md` az állapotot.** Ne a mappát analizáld
> végig — ebből a két fájlból kiderül minden, és onnan tudod, melyik további dokumentum kell.
>
> 🔴 **Minden kör VÉGÉN frissítsd az `ALLAPOT.md`-t.** Ez nem opcionális. A 2026-os `HANDOFF.md`
> azért vált használhatatlanná, mert senki nem frissítette, és a végén rossz irányba küldte
> volna a munkát.

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
**A csoportok EGY helyen, ikonnal: `App\Enums\NavigationGroup`.** Az oldalsáv asztali nézetben is
ikonokra csukható (`sidebarCollapsibleOnDesktop`), ezért MINDEN csoportnak ikon kell (enélkül becsukva
a csoport felirata eltűnik, az elemei szétszóródnak). Ikonos csoport elemének NEM lehet saját ikonja
(Filament-szabály — becsukva a csoport ikonja egy legördülőt nyit az elemekkel). Új csoport = új eset
a felsorolásban, címkével ÉS ikonnal; az erőforrás `$navigationGroup`-ja erre mutat, nem szövegre.

## 2/c. A téma (`/tema`) — döntések a 2026-os tapasztalat alapján

🔴 **NINCS láthatatlan beépített alapérték.** Amit az oldal használ — logó, fejléc-logó, favicon,
firka-motívumok —, az **valódi, feltöltött fájl** legyen az első perctől, és **látszódjon a
szerkesztőben**.

**Miért:** 2026-ban a téma logó-mezője üres volt, miközben az oldalon végig ott volt a logó, mert a
kódból jött. Ugyanez a firkáknál: a hat hely üres, a kiment motívumok beépítettek. Következmény:
**a szerkesztőből nem lehetett megtudni, mi van valójában kint.** Ez egyszerre sérti az „egy forrás"
és a „néma hiba tilos" szabályt.

**Teendő induláskor:** a 2026-os logót és a beépített firka-motívumokat **rendes feltöltésként**
töltsük be a témába. Onnantól cserélhetők.

**A favicon átvehető** a 2026-os témából — de a tárolt értéke ma **teljes címmel a NAS-ra mutat**
(`https://admin.forgatag.ro/assets/…`), ami a NAS nélkül eltörik. Importkor saját fájllá kell tenni.

**A „Komponensek" szekció KIMARAD** (tulajdonosi döntés, 2026-10-21). Két dolog volt benne, és
gyűjtőfiók maradt: a chat-gomb kapcsolója a chatbot többi beállításához kerül, a „MA" jelvény
háttérszíne pedig a színek közé.

**A „Kategóriacsoportok” szekció és a csoportszínek MEGSZŰNTEK** (tulajdonosi döntés, 2026-10-21).
A csoport-fogalmat már 2026. július 22-én elhagyták (forgatag-os `70c5ca6`); a hét `--grp-*` szín azóta
gazdátlan volt, és betűre egyezett a firka-palettával. Díszítőszín a `--doodle-*` tokenekből jön.
**Csoportokat ne találj ki** — ha valaha kell, a kategóriákhoz kerül, a tulajdonos megadja.

**Világos mód (◐) — CSAK a beküldő űrlapon** (2. adósság, 2026-10-21). A témában a fő színeknek
`light` fele is van — a 2026-os beküldő űrlap tényleges világos palettája (forgatag-os
`submit-api/public/index.html`). A publikus oldal 2026-ban szándékosan sötét-only lett; hogy a 2027-es
publikus oldalon legyen-e váltó, az a publikus váz körének kérdése — kérdezd a tulajdonost.

**A „Mozgás" szekció marad.** Ez szabályozza a kártyák felúszását, a gombok reakciósebességét,
a „MA" jelvény lüktetését és a firkák lebegését, három fokozattal. Valódi haszna, hogy mozgásra
érzékeny látogatóknak levehető.

**Melyik téma-verziót vettük át — ELDÖNTVE (tulajdonosi döntés, 2026-10-21):** a LEGUTOLSÓ
publikáltat (`schema-bot`, 2026-09-02 13:04). Az öt megőrzött verzió mezőnként azonos volt (csak a
`version` számláló változott) — lásd `MENTES-LELTAR.md` 14. Megépítve: lásd a 8. pont „Téma-alap” bekezdését.

## 2/d. Médiatár (tulajdonosi döntés, 2026-10-21)

**Megoldás:** a Filament rácsos médiakezelője (Curator) — WordPress-szerű böngésző, újrafelhasználható
képek, automatikus méretváltozatok. **A saját mezőink** a mi tábláinkban: jogtulajdonos, licenc,
fotós-kredit, fókuszpont. *(Kockázat: közösségi bővítmény. Elviseljük, mert az adat a mi
tábláinkban van — ha a bővítmény elhal, csak a böngésző-felületet kell pótolni.)*

### A licenc kötelezősége — KÉT külön eset

| Honnan jön a kép | Licenc |
|---|---|
| **Publikus beküldő űrlapról** (külsős forrás) | **KÖTELEZŐ mező.** „Ismeretlen" jogállású kép **nem publikálható.** A fotós nevét és a jogi nyilatkozatot az űrlap már ma is kéri. |
| **Adminban feltöltve** (saját forrás) | **Nem kötelező.** A Forgatag csapata vállalja érte a felelősséget; az alapérték saját forrás legyen, és ne álljon az útba. |

### Gyűjtemények — „mappák", de csak vizuálisan

A Curatorban **nincs mappafa**, és nem is kell: a fizikai tárolón ne legyen mappa-rendezés.
Helyette **egy „gyűjtemény" mező + szűrő**, baloldali listával, ami mappák benyomását kelti.

**Alapértelmezés: a gyűjtemény abból jön, HONNAN töltötték fel.** Ha egy képet egy hír vagy egy
program szerkesztőlapjáról töltenek fel, az **magától a „Hírek", illetve „Programok" gyűjteménybe
kerül** — a felhasználónak nem kell semmit csinálnia. Ugyanígy a támogatók, a galériák, az arculat.

**A médiatárba közvetlenül feltöltött kép „Rendezetlen"**, és utólag gyűjteménybe tehető.
Elsőre elég egy többszörös kijelölés + „Áthelyezés gyűjteménybe" művelet; a fogd-és-vidd csak akkor,
ha ez kevésnek bizonyul.

**Előny a valódi mappához képest:** egy kép **több gyűjteménybe is tartozhat.**

*Megjegyzés a mennyiségről:* az 1091 képből a legtöbb nem szabadon álló — 467 galériakép, 112 logó,
241 programborító a saját rekordján keresztül érhető el. A médiatárban böngészendő anyag néhány száz.

*Bónusz:* a Curator tud **képenként elmentett vágásokat**. Ez válasz a „kép csak egyszer vágódik"
szabályra, és részben kiváltja a fókuszpontot.

### Tárolás — szándékosan kétfelé

- **A médiatár nyilvános mappában.** Így nem kell szimbolikus link, amit a cPanel Fájlkezelő nem
  tud létrehozni.
- **A még jóvá nem hagyott beküldött fotó a ZÁRT tárban marad.** Jóváhagyáskor kerül át a médiatárba.
  **Így egy visszautasított program fotója sosem lesz nyilvánosan elérhető.**

### A 2026-os programborítók — „Programok 2026” (tulajdonosi döntés, 2026-10-22)
A 241 borító **NEM** programhoz kötve jön át (a 2026-os programok nem jönnek át, 7. pont), hanem a külön
**„Programok 2026”** gyűjteménybe, eredet-jelöléssel és a jogállás-adataival. Külsős fotók, „minden jog
fenntartva” — a jogot a tavalyi programra adták: **2027-es programhoz NEM rendelhetők** (modell-őr:
`ProgramItem::saving`). Megőrizzük, mert egy visszatérő programgazda kérheti ugyanazt a képet.

**Megépítve: lásd a 8. pont „Médiatár” bekezdését.**

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

**Így van megépítve (2026-10-21) — ehhez igazodj, ne kerüld meg:**
- **Mit tehet** egy szerepkör: `App\Enums\UserRole` (a mátrix EGY helyen) → `app/Policies` (az alap
  `Policy` mindent tilt). A panel `strictAuthorization()`-nel fut: policy nélküli új modell lapja hibával
  áll meg, nem nyílik ki mindenkinek. **Új modell = új policy.** Új lap (Page) = saját `canAccess()`.
- **Melyik sorokon:** `App\Models\Scopes\RowAccess` (egy forrás) → `ProgramScope` a programon,
  `ProgramChildScope` (`RowRestrictedChild` trait) az időponton, fordításon, naplón. **Írásra is**: a
  modellek `saving`/`deleting` őre idegen kategóriába nem enged írni (ez fedi az importot soronként).
  Az elsődleges kategória dönt; kategória nélküli kategóriagazda SEMMIT nem lát. Ha új modell programhoz
  tartozik, kapja meg a `RowRestrictedChild` traitet.
- **Szivárgás-biztos alapállás:** a szűkítés minden kérésben hat, ahol a belépett admin kategóriagazda.
  A beküldő lapok (`BekuldoBelepve`) a `bekuldo` őrre állnak át. ⚠ A jövőbeli **publikus oldal** kéréseiben
  ugyanígy át kell állni (különben egy belépett kategóriagazda kevesebb programot látna a nyilvános lapon).
- ⚠ **Belépett felhasználó nélkül NINCS szűkítés** (parancssor, ütemező, sorba tett feladat). Ezért
  **export/import CSAK szinkron futhat** (`QUEUE_CONNECTION=sync`), vagy a feladat maga állítsa be a
  felhasználót. Nyers `DB::table()` / `Builder::insert()` megkerüli a modellt — admin-felületen tilos.
- Tesztek: `tests/Feature/SorSzintuJogTest.php` — a hat felület külön-külön. **Ellenpróbával mérve:** a
  szűkítést kikapcsolva 9 teszt elbukik.
- **Élesben mérve (proba.forgatag.ro, 2026-10-21), kategóriagazda-fiókkal, böngészőből:** idegen
  kategóriájú program → a lista üres · a globális kereső nem találja · a begépelt `/admin/programok/{id}`
  404 · a `/foto` 404.

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

A médiatár, a galériák és a támogatók (logóval) elkészültek — lásd lent, „Médiatár”.
**A 2/b elv miatt még átköltözik:** a védett nevek (`do-not-translate.json` → tábla, a fordítási fázisban).

**2. Programbeküldő űrlap — kész helyben (2026-10-12).** `/bekuldes` (belépés: `/bekuldes/belepes`).
Mezők/szabályok/hibaszövegek a 2026-os `submit-api`-ból; a leírás EGY helyen: `App\Support\SubmissionForm`
(űrlap + szerver), validáció+mentés: `App\Services\ProgramSubmission`, szövegtisztítás: `App\Support\TextCleaner`
(párja: `public/js/bekuldes.js`). Belépés: külön `bekuldo` őr (`program_managers`), a Filament `authGuard('web')`.
Fotó: nem nyilvános tár (`storage/app/private/bekuldesek/`, `program_items.photo_path`) — ELŐBB a fotó, és
ha nem megy, a program nem jön létre (+ koordinátori levél). Levelek: `App\Support\BekuldesLevelek` →
`Notifier` (soha nem dob: a mentés utáni levélhiba nem lehet „sikertelen beküldés”). Jóváhagyási levél:
`ProgramItem::updated` (draft/pending → published). Korlát: `throttle:bekuldes` (30/óra/IP), belépés 5 hiba → 15 perc.
A lapok szín/betű/forma/mozgás tokenjei a publikált témából jönnek (lásd „Téma-alap”).
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

**Szerepkörök + sor-szintű jog — kész helyben (2026-10-21).** Részletek a 4. pontban. `users.role`
(új fiók: csak olvasó; a migráció a MEGLÉVŐ fiókokat szuperadminná tette), `category_user` pivot.
Kategóriagazda: csak a saját kategóriái, átnéz/jóváhagyásra küld/visszaküld, **jóváhagyni nem tud**.
Koordinátor: mindent lát, jóváhagy, programgazdák + Beállítások. Admin-felhasználók: csak szuperadmin.
Saját szerepkörét senki nem állíthatja át (kizárás ellen).

**Programlista + jóváhagyási sor — kész helyben (2026-10-13).** `ProgramItems\ProgramItemResource`
(`/admin/programok`, a menüben a Vezérlőpult után, jelvény: hány vár jóváhagyásra). Fülek: Teendő (alap) ·
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

**Téma-alap — kész, élesben mérve (2026-10-21).** A téma mint ADATFORRÁS; a szerkesztője lent.
- **Adat:** `theme_versions` tábla, APPEND-ONLY (`App\Models\ThemeVersion`); a LEGUTOLSÓ sor a kint lévő
  téma. Kezdőérték: `seed/tema/tema.json` + `seed/tema/fajlok/` — a `forgatag:torzsadatok` tölti be
  (`Theme::importSeed`, csak üres táblába). A 2026-os adathoz képest: a „Komponensek” megszűnt
  (`colors.badgeMaBg`/`badgeMaFg`, `header.navbarBlur`; a chat-gomb kapcsolója kimaradt), a firkák
  `doodles.motifs` (6 fájl, a 2026-os `custom` helyett), a `version/_fgv/meta` helyett a tábla sora.
- **CSS:** `App\Support\ThemeCss::generate` (a 2026-os `themeToCss` átirata, SZIGORÚ: hiányzó kötelező
  mező → hiba, nincs tartalék). Fájlba íródik: `public/arculat/tema-<a CSS tartalmának lenyomata>.css`
  — bármilyen változás (adat VAGY generátor-kód) új fájlnevet ad, a gyorsítótár magától frissül.
  A telepítés kiírja (`forgatag:tema-css` a `.cpanel.yml`-ben); ha mégis hiányzik, az első kérés
  (`Theme::cssUrl`). Téma nélkül a lap 500-zal áll meg.
- **Fájlok:** logó, fejléc-logó, favicon, 6 firka = valódi fájl a `public/arculat/fajlok/` alatt,
  lenyomatos névvel (`Theme::storeAsset`; az SVG tisztítva). A téma-adat csak ilyen helyi útvonalat
  fogadhat el; `admin.forgatag.ro`/`elo.forgatag.ro` a publikáláskor tiltott. A `public/arculat/`
  a `.gitignore`-ban van (a szerveren keletkezik — nem piszkítja a munkafát).
- **Olvasók:** `Theme::cssUrl()`, `assetUrl('logo'|'logoHeader'|'favicon')`, `motifSvg(0–5)`,
  `fontPreloads()`, `get('colors.bg.dark')`. Betűk: `ThemeCss::FONTS` (csak önhostolt, a valódi
  vastagság-tartománnyal és dőlt-adottsággal). **Címbetű-szabály (6. pont, Tingler csak 400):** ahol
  `var(--font-head)` szerepel, oda `font-weight:var(--head-wght)` / `var(--head-wght-2)`; ahol
  `var(--font-accent)`, oda `font-weight:var(--accent-wght)` + `font-style:var(--accent-style)` —
  különben a környező szöveg 700/800-át örökli. A generátor a betű tartományán kívüli vastagságot
  hibával elutasítja; a `fonts.css` tiltja a mesterséges vastagítást (`font-synthesis:none`); a
  `TemaTest` minden `public/css/*.css` szabályt végigellenőriz. A firka-dekor
  jelölése a lapokon: `[data-decor="hero"|"ambient"]` réteg + `.decor` elem (a téma sűrűség/mozgás
  beállítása erre hat).
- Tesztek: `tests/Feature/TemaTest.php`; a `tests/TestCase.php` minden adatbázisos tesztnek betölti a
  kezdő témát (hamis arculati tárba), és törli a `Settings` statikus gyorsítótárát (különben a tesztek
  között átszivárog, és elrejti, ha egy teszt nem állítja be a beállításait).

**Arculat-szerkesztő (`/admin/tema`) — kész helyben (2026-10-21).** Filament-lap (`App\Filament\Pages\Tema`,
menüpont: „Arculat” — kivételes saját menüpont, PROMPT-06), 11 szekció a 2026-os feliratokkal.
- **A leírás EGY helyen:** `App\Support\ThemeEditorSpec` — szekciók, feliratok, súgók, tartományok,
  kontraszt-szabályok. Ebből épül a felület, ÉS ebből ellenőriz a szerver (`validate`: a megengedett
  SZERKEZET a `seed/tema/tema.json` — ismeretlen mező hiba, így kivezetett kulcs importtal sem jön vissza).
- **Élő előnézet szerver nélkül:** `public/js/tema-szerkeszto.js` + `public/js/tema-css.js` (a PHP
  `ThemeCss` BETŰHŰ párja; a táblázatokat a szerver adja). A szerkesztő betöltéskor összeveti a két
  generátor kimenetét, eltérésnél piros figyelmeztetés. **Ha a `ThemeCss`-t módosítod, a JS-t is.**
  Az előnézet iframe: `resources/views/tema/elonezet.blade.php` (a valódi publikus CSS-sel, Filament nélkül).
- **Végpontok:** `Admin\TemaController` (`/admin/tema-api/*`): vázlat, publikálás, feltöltés, gyári,
  verzió, előnézet. A JSON-t NYERSEN olvassa (a `ConvertEmptyStringsToNull` az üres szöveget null-ra
  cserélné — a témában az üres szöveg érvényes érték).
- **Piszkozat:** `theme_drafts` (egy közös sor, `base_version_id`-val; ha közben más publikált, figyelmeztet).
  **Publikálás:** `Theme::publish` — szerkezet + értékek + fájlok + **kontraszt** (`ThemeContrast`, mindkét
  mód); hiba esetén csak írásos (≥10 karakteres) felülbírálással, ami a verzión marad (`contrast_override`).
  **Előzmény:** az utolsó 10, „Betöltés” → a szerkesztőbe, kikerülni publikálással (különbséggel).
- **Jog:** `UserRole::canEditTheme` (szuperadmin, arculat-szerkesztő). Az arculat-szerkesztőnek a
  nyitólap (`App\Filament\Pages\Dashboard`) a `/admin/tema`-ra irányít; minden más lap 403/404.
- Tesztek: `tests/Feature/TemaSzerkesztoTest.php` (jog szerepkörönként külön futásban — egy teszten belüli
  felhasználóváltást az `AuthenticateSession` kiléptetésnek venné).

**Médiatár — kész helyben, a 2026-os képanyaggal (2026-10-22, PROMPT-07).** Döntések: 2/d.
- **Csomag:** Curator 5.6 (`awcodes/filament-curator`). Beállítás: `config/curator.php` (lemez, a mi
  modellünk/lapjaink). A Glide-kulcs: `CURATOR_GLIDE_TOKEN`, ha nincs, az `APP_KEY`-ből származtatva — így
  egy környezet sem indulhat nélküle, és titok nem kerül a tárolóba.
- **Tárolás:** `mediatar` lemez = `public/mediatar/` (nyilvános, symlink nélkül; `.gitignore`-ban, csak a
  szkript-tiltó `.htaccess` van a tárolóban). Méretváltozatok: `/curator/...` (Glide, gyorsítótár:
  `storage/app/.cache`). A 16 MP fölötti képekhez ezen az útvonalon 512 MB memória (`AppServiceProvider`).
  **A tárhelyen GD kell** (Select PHP Version) — a helyi Docker-képbe bekerült (gd, exif).
- **Modell:** `App\Models\Media` (a Curator `curator` táblája + `rights_holder`, `license`, `photographer_credit`,
  `origin`, `legacy_id`, `legacy_note`). **Jogállás-őr EGY helyen:** `Media::jogallasHiba()` — `origin=bekuldes`
  → licenc kötelező, `ismeretlen` tilos, jogtulajdonos kötelező; adminban feltöltött → alapérték „Saját forrás”.
  **Törlés-őr:** `Media::usages()` (galéria, logó, borító, arculat) — használt kép nem törölhető (policy + modell + FK restrict).
- **Gyűjtemények:** `media_collections` + pivot. Rendszer-gyűjtemények: `App\Enums\Gyujtemeny` (a kód ezekre hivatkozik,
  nem törölhetők/nevezhetők át); a felhasználó sajátot is létrehozhat. **„Rendezetlen” = egyetlen gyűjteményben sincs**
  (nem tárolt). **A gyűjtemény a feltöltés helyéből:** `App\Filament\Forms\MediaPicker::make(...)->gyujtemeny(...)`
  → `App\Livewire\MediaPanel` (zárolt tulajdonság). ⚠ A Curator a feltöltést `self::createMediaFiles()`-szel hívja,
  ezért a panel az `addFilesAction` testét veszi át — csomagfrissítéskor összevetendő.
- **Admin:** „Médiatár” önálló menüpont; a Galériák és a Támogatók 2026-10-28 óta a „Tartalom” csoportban. A médiatár lapja:
  `App\Filament\Mediatar\*` — SZÁNDÉKOSAN a felfedezett `Resources` mappán kívül (a bővítmény regisztrálja; különben
  kétszer kerülne a menübe). Bal oldalt gyűjtemény-lista (mobilon vízszintes sor), tömeges „Áthelyezés gyűjteménybe” /
  „Hozzáadás gyűjteményhez” / „Kivétel”. Jog: `UserRole::canManageMedia` (szuperadmin, koordinátor).
- **Az admin saját témája:** `resources/css/filament/admin/theme.css` → **`npm run admin-tema`** →
  `public/css/admin-tema.css` (a tárolóban; a szerveren nincs Node). A Curator nézetei Tailwind-osztályokat
  használnak — **új Filament-nézet/osztály után újra kell fordítani**, különben az új osztály hatástalan.
  ⚠ A Filament closure-paramétereit NÉV szerint adja (`$query`, `$record`): más névvel üres Builder jön, némán.
- **Jóváhagyáskor zárt tár → médiatár:** `App\Services\ProgramPhoto::publish` (a `ProgramReview::approve` lépésében,
  a státuszváltás ELŐTT; ha nem sikerül, a jóváhagyás sem, a levél sem megy). A beküldött eredeti a zárt tárban marad;
  a program `cover_media_id`-je a médiatár-képre mutat („Programok”, `origin=bekuldes`, „minden jog fenntartva”,
  jogtulajdonos = a programgazda). A program lapja mutatja, megtörtént-e.
- **Arculat (6. adósság törlesztve):** a téma fájljai ugyanott maradnak (`public/arculat/fajlok/`), a médiatár egy sort
  kap róluk (`disk=arculat`, „Arculat” gyűjtemény) — `App\Support\ArculatMedia`, a `Theme::storeAsset` hívja. Nem
  törölhetők, nem cserélhetők a médiatárból (a /tema-ban cserélhetők).
- **Galéria, támogató:** `galleries` + `gallery_media` (sorrend), `sponsors` (évhez kötve, `logo_media_id`).
  `sponsor_tiers.year`: év nélkül = a 2027-es kezdőlista; `2026` = a 2026-os szintek (más nevek!).
- **A 2026-os behozatal:** `forgatag:mediatar-csomag` (FEJLESZTŐGÉPEN, a mentésből) → `database/import/mediatar-2026.json`
  (minden fájl sorsa; személyes adat nincs benne) + `import-2026-N.zip`-ek (a mentés mellé, `MENTES-2026/import-csomag/`).
  `forgatag:mediatar-import` (a TELEPÍTÉS utolsó lépése): a `storage/app/import-2026/` zip-jeiből, sha256-tal
  ellenőrizve, ismétlés-biztosan (`legacy_id`); a teljesen behozott zip-et törli; számszerű jelentés a médiatár
  lapján (`storage/app/import-2026/jelentes.json`), hiánynál piros és a parancs hibával lép ki.
  **Helyben mérve:** 1091 = 1031 médiatár + 26 zárt tár + 34 kihagyott; galéria 467/467, logó 112/112; ~70 mp.
  A hír-, csapat-, kézműves-, aloldal-képek eredete a `legacy_id`/`legacy_note`-ban — a tartalmi kör ezen köt be.
- Tesztek: `tests/Feature/MediatarTest.php` (15 teszt: feltöltés-hely → gyűjtemény, áthelyezés, licenc két esete,
  jóváhagyás/visszaküldés, Programok-2026-őr, törlés-őr, arculat, jog, behozatal + sérült fájl).

**Tartalom: hírek, menü, aloldalak — kész helyben (2026-10-28, PROMPT-08).** „Tartalom” menücsoport
(`NavigationGroup::Tartalom`: Hírek · Menü · Aloldalak · Galériák · Támogatók; a Médiatár önálló menüpont).
Jog: `UserRole::canManageContent` (szuperadmin, koordinátor) → `TartalomPolicy` (+ `PagePolicy`: rendszerlap nem törölhető).
- **Modellek:** `NewsItem` (HU/RO, `cover_media_id`, `status` = `ContentStatus`, `published_on`), `Page` (+ `PageText`: a
  sablon szerkeszthető kulcsai, nyelvenként vagy képpel; + `PageBlock`: richtext/image/cta/divider/videos, `show_hu/ro`),
  `Menu` + `MenuItem` (fa: `parent_id`, `type` = `MenuItemType` page/link/group, `show_hu/ro`). Minden kép a médiatárra mutat,
  restrict-tel; a `Media::usages()` mindet ismeri (használt kép nem törölhető). Rendszerlap (`is_system`): a slug fix, nem törölhető
  (`Page::booted`). Saját tartalom = KÉZZEL fordított, sima `_hu/_ro` mezők (a fordítás-táblák csak a programé).
- **A menüfa EGY író-útja: `App\Support\MenuTree`** (`save` a teljes fára, `move` a nyíl-gombokra; `MAX_DEPTH = 2`; hibás fát
  nem ír ki félig). A szerkesztő `App\Filament\Pages\Menuk` + `resources/views/filament/pages/menuk*.blade.php` +
  `public/css/menu-szerkeszto.css` — **fa-bővítmény NINCS** (szándékosan); a húzás a Filament beépített `x-sortable`
  direktívája (közös `x-sortable-group` → szintek között is). A húzás után a böngésző a TELJES fát küldi (`mentFa`).
- **TESZTADAT (tulajdonosi szabály): `is_test` hírnél és programnál → `App\Support\Tesztadat`** (counts/purge — ÚJ teszt-hordozó
  gyűjtemény = új sor mindkettőben). Látszik: TESZT jelvény, nyitólapi figyelmeztetés (`App\Filament\Widgets\Figyelmeztetesek`);
  törlés: Beállítások → Tesztadatok, vagy `forgatag:tesztadat-torles`. Teszt-programok törlése az adatbázis kaszkádjával (a napló
  append-only modellje így nem akad el). **Ami tesztre jön be, MINDIG `is_test`-tel jöjjön.**
- **A 2026-os tartalom:** `forgatag:tartalom-csomag` (FEJLESZTŐGÉPEN, a mentésből; a dump-olvasó közös: `App\Support\Mentes2026`)
  → `database/import/tartalom-2026.json`; `forgatag:tartalom-import` (a TELEPÍTÉS része, a médiatár-behozatal után): lapok/menük
  csak üres táblába, a teszthírek csak EGYSZER (jelölés: `storage/app/import-2026/tartalom.json` — fájlban, hogy az adatbázis-ürítés
  után ne jöjjenek vissza); hiányzó képnél semmit nem ír, hibával áll meg.
- **5. adósság őre:** `SubmissionForm::unknownSlugs()` → piros figyelmeztetés a nyitólapon; a seed-lista: 2026-os végleges 23.
- Tesztek: `tests/Feature/TartalomTest.php` (10: slug-őr, menüfa + nyilak + mélység, nyelvenkénti rejtés, rendszerlap, tesztadat,
  behozatal számra, kategóriagazda 403 közvetlen címmel).

### Adósságok — nyilvántartva, később szabályt sértenének (tulajdonosi rögzítés, 2026-10-12)

1. ~~**`public/css/tema.css` IDEIGLENES.**~~ **Törlesztve (2026-10-21):** a fájl törölve, minden lap a
   generált téma-CSS-ből dolgozik (lásd „Téma-alap”).
2. ~~**A beküldő űrlapról hiányzik a világos/sötét váltó.**~~ **Törlesztve (2026-10-21):** ◐ gomb a
   beküldő lapok fejlécében (kulcs: `forgatag-theme`, alap: sötét), a színek a téma `light` feléből.

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
- **A telepítő-PHP-ban (ea-php84) NINCS zip** (mérve 2026-10-28: `zip=0 gd=1`); a web PHP-jában (alt-php84,
  Select PHP Version) VAN. A `.cpanel.yml` ezért a médiatár-behozatalt az alt-php84-gyel futtatja, ha az tud zip-et.
  A cPanel Deploy felülete ilyenkor is „fut”-ban ragadhat — a valóság a `~/.cpanel/logs` naplóban.
- **Új composer-csomag után** a szerveren a régi csomaglista (`bootstrap/cache/packages.php`, nincs a tárolóban)
  maradna → az új csomag nem töltődik be (2026-10-28: „No hint path defined for [curator]”, 500). Ezért a
  `.cpanel.yml` minden telepítéskor `package:discover`-t futtat.
- **Óriásképek:** a 40 MP fölötti kép bélyegképe nem készül el (memória); a behozatali jelentés „Tennivaló”-ként
  név szerint kiírja (2026-os anyagban 1 db: 14765×7384-es logó). Az eredetit nem módosítjuk.

### Feljegyezve a későbbi fázisokra (tulajdonosi rögzítés, 2026-10-13)

- **Export, import, helyszín-lap (kapcsolat-kezelő) MÉG NINCS.** A szűkítés modell-szintű, tehát
  ezekre is hatni fog — de amikor elkészülnek, a kategóriagazda fiókjával böngészőből is végig kell
  próbálni őket (a `SorSzintuJogTest` mintájára tesztet is kapnak), és lásd a 4. pont sorba tett
  feladatokra vonatkozó figyelmeztetését.
- **Napi automatikus adatbázis-mentés — ÁPRILISIG KÖTELEZŐ** (`ATALLAS-TERV.md` kockázati pont). Amint
  valódi beküldések érkeznek, működnie kell (cPanel backup + letöltés a NAS-ra). Még nem sürgős, de
  nem csúszhat a beküldés megnyitása utánra.
  ⚠ A 2026-os NAS-mentés tanulsága: a `pg_dump | gzip` a gzip sikerét jelzi, nem a pg_dumpét —
  az új mentés a zárójelet („dump complete”) is ellenőrizze, különben a félbeszakadt mentés néma.

### A 2026-os adatok mentése (2026-10-21) — az ALAPANYAG
**Helye: `E:\Work\mysites\forgatag.ro\MENTES-2026\2026-10-21\`** (a fejlesztőgépen; NEM git-tároló,
személyes adatot tartalmaz). Teljes PostgreSQL-mentés (visszatöltve ellenőrizve), a Directus fájltára
eredeti méretben (1091 fájl, sha256-tal), a téma-JSON külön (`tema/theme-data.json`).
Mi van benne és mi gyanús: **`MENTES-LELTAR.md`**. Importnál innen dolgozz, ne a NAS-ról.

## 9. Nyitott kérdések — ne döntsd el magad

- A 2027-es előkészítő napok pontos száma (most 2 — „kettő biztos”; a Beállításokban átírható).
- A koordinátorok értesítési címei (a Beállításokban üresen állnak — nem találunk ki címet).
- A jóváhagyók személye; a kategóriagazdák névsora.
- Kik kapnak arculat-szerkesztő fiókot.
- ~~Melyik médiatár-megoldás~~ — eldöntve (2/d), megépítve (8. pont, „Médiatár”).
- A szponzorszintek: a 2027-es kezdőlista a 2025-ös 9 szint (román név nélkül), 2026-ban viszont 11 szint volt,
  más nevekkel (pl. „Szervezők”, „Médiapartnerek”, „Társszervezők”). Melyik legyen a 2027-es lista?
- A behozott, semmihez nem kötött képek sorsa: 34 demó-kép (2026-os próbatartalom) és 25 egyéb, „Rendezetlen”-ben.
- A zárt tárba tett 26 beküldött fotó (2026-ban meg nem jelent programoké): megtartsuk-e?
