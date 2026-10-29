# HOL TARTUNK

> **Ezt olvasd el először, minden új beszélgetés elején.** Utána a `CLAUDE.md`-t.
> **Ezt a fájlt minden kör VÉGÉN frissíteni kell** — a 2026-os `HANDOFF.md` azért vált
> használhatatlanná, mert senki nem frissítette.

**Utolsó frissítés:** 2026-10-28 (PROMPT-08, tartalmi gyűjtemények)

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

- **Arculat-szerkesztő** (`/admin/tema`, menü: „Arculat”, `PROMPT-06`, 2026-10-21) — 11 szekció a
  2026-os feliratokkal; élő előnézet a böngészőben (csúszkahúzás közben 0 szerverhívás, mérve);
  piszkozat külön a kint lévőtől; publikálás előtt különbség-lista; kontraszt-őr (4.5:1 alatt csak
  írásos felülbírálással, a szerver is ellenőrzi); utolsó 10 verzió visszaállítással; JSON export/import;
  gyári visszaállítás (egészben és fájlonként). Az arculat-szerkesztő csak ezt a lapot éri el.
  **Kategóriacsoport-színek kivezetve** (tulajdonosi döntés: a csoportokat 2026 júliusában megszüntették).
  **◐ világos/sötét váltó a beküldő űrlapon** (a 2026-os űrlap világos palettájával).
  Élesben: lásd a „MOST folyik” pontot.

- **Médiatár** (`PROMPT-07`, 2026-10-22) — **kész; a 2026-os behozatal ÉLESBEN is lefutott (2026-10-28 17:07)**,
  a számok betűre egyeznek a helyi méréssel (lásd lent). Élő képernyős próba még hátra van (MOST folyik).
  Curator + saját mezők (jogtulajdonos, licenc, fotós, eredet); menü: „Médiatár” (2026-10-28 óta önálló
  menüpont; a Galériák és a Támogatók a „Tartalom” csoportban). A gyűjtemény a feltöltés helyéből jön (galéria/támogató lapjáról magától oda;
  közvetlen feltöltés → „Rendezetlen”); tömeges „Áthelyezés gyűjteménybe”. Licenc: beküldött képnél kötelező,
  adminban nem áll az útba. Jóváhagyáskor a beküldött fotó a zárt tárból a médiatárba („Programok”) kerül;
  visszaküldött program fotója nem. A téma fájljai az „Arculat” gyűjteményben (**6. adósság törlesztve**).
  **A 2026-os képanyag helyben behozva:** 1091 fájlból 1031 a médiatárba, 26 a zárt tárba, 34 szándékosan
  kihagyva (önkéntesek személyes mellékletei); galéria-kapcsolat 467/467, logó-kapcsolat 112/112; 241 borító
  a „Programok 2026”-ba (eredet-jelöléssel, 2027-es programhoz nem rendelhető — tulajdonosi döntés). 375 px-en
  ellenőrizve. Részletek: `CLAUDE.md` 8. pont „Médiatár”. 111 teszt zöld.

- **Tartalom: hírek, menü, aloldalak** (`PROMPT-08`, 2026-10-28) — **kész, élesben telepítve 2026-10-28** (az élő próba vége: „MOST folyik”).
  Új **„Tartalom” menücsoport**: Hírek · Menü · Aloldalak · Galériák · Támogatók (a Médiatár önálló menüpont lett —
  egyelemű csoport nem maradhatott). Csak szuperadmin és koordinátor éri el.
  - **Menü:** saját fa-szerkesztő (`/admin/menu`), bővítmény nélkül: fogd-és-vidd + nyíl-gombok (fel/le/beljebb/
    kijjebb — mobilon ez a biztos út), **HU/RO gomb soronként = nyelvenkénti elrejtés**, legfeljebb 2 szint (a 3. szintet
    emberi üzenettel elutasítja, a fa visszaugrik).
  - **Aloldalak:** cím, bevezető, SEO, nyitókép, **szerkeszthető szövegek** (a sablon kulcsai, képpel is), **blokkok**
    (szöveg, kép, gomb, elválasztó, YouTube-videók), blokk nyelvenként rejthető. Rendszerlap nem törölhető, slugja fix.
  - **Hírek:** HU/RO, borító a médiatárból, piszkozat/publikált, megjelenés dátuma.
  - **Szövegszerkesztő (2026-10-29, tulajdonosi kérés):** a hírek és az aloldal-blokkok szövege **WYSIWYG** (a Markdown-
    szerkesztő helyett), saját **„HTML” gombbal** (`</>`: a forrás ablakban látszik és átírható). A szöveg HTML-ként
    tárolódik; a meglévő Markdown-szövegeket egy migráció átalakította (a 2026-os megjelenítéssel egyezően, a beírt HTML
    semlegesítve). Kép a szövegbe NEM tölthető (a képek a médiatárban, kép-blokkal). 375 px-en ellenőrizve, 124 teszt zöld.
  - **Behozva a 2026-ból — számra:** menü 2/2, menüpont 21/21, lap 13/13, szerkeszthető szöveg 85 (köztük **16/16 kép
    a médiatárból**, `legacy_id`-n át, nem újrafeltöltve), videó 26/26 (3 csoport → 3 videós blokk a „Videó archívum”
    lapon), **teszthír 4** (12-ből; 8 szándékosan nem). **Lapblokk 0/9 — szándékosan:** mind a 9 egy nem létező laphoz
    tartozott, és próbaszemét volt („dsadsa”, „Teszte csere”). Csomag: `database/import/tartalom-2026.json`.
  - **TESZTADAT — felismerhető, egy lépésben törölhető:** `is_test` jelölés hírnél ÉS programnál; TESZT jelvény a
    listában és a szerkesztőlapon, piros figyelmeztetés a nyitólapon, törlés: **Beállítások → Tesztadatok** (vagy
    `php artisan forgatag:tesztadat-torles`). A teszthír-behozatal jelölése fájlban van, így az élesítés előtti
    adatbázis-ürítés + telepítés után **nem jönnek vissza**.
  - **5. adósság törlesztve** (lent). Kategóriagazda-fiókkal böngészőből végigpróbálva: az új lapok (közvetlen címmel is)
    403, a menüben nem látszanak. 375 px-en minden új lap használható. 122 teszt zöld.

## Ami MOST folyik

1. **A médiatár élő próbája** — a behozatal élesben kész: 1031/1031 a médiatárba, 26/26 a zárt tárba, 34 szándékosan
   kihagyva; galéria 467/467, logó 112/112; hiba 0; a 3 zip törölve. **Tennivaló (1):** a „Balázs Ferenc Önképző Kör”
   logója 14765×7384 (109 MP) — a bélyegképe nem készül el, kisebbre kell cserélni (a kép „Csere” fülén).
   Hátra van (a felhasználóval, az ő belépésével): egy galéria, a támogatók logói, 375 px, egy jóváhagyás élesben.
   **Tanulságok az élesítésből** (`CLAUDE.md` „A tárhely mért adottságai”): a telepítő-PHP-ban (ea-php84) nincs zip,
   ezért a behozatal az alt-php84-gyel fut; új composer-csomag után `package:discover` kell (most a telepítés része).
2. **Az Arculat-szerkesztő élő próbája** (arculat-szerkesztő fiókkal) — ha még nem volt meg.
3. **A Tartalom élő próbája** (PROMPT-08) — **telepítve, 2026-10-28, a napló hibátlan:** migráció kész, kategóriák 23
   (a megszűnt 5 mind törölhető volt, egyik sem maradt bent), 13 lap, 2 menü / 21 menüpont, 4 teszthír; a menük az
   adminban látszanak (a tulajdonos saját böngészőjéből). **Hátra van (a felhasználóval):** az Aloldalak lapon a Rólunk
   képei, a Hírek TESZT jelvénye, a nyitólap piros „Tesztadat” sávja, egy menüpont áthúzása, 375 px.

## Ami ezután jön, ebben a sorrendben

1. **A publikus váz** — keretfájl, fejléc, lábléc, egy lap, a valódi token-forrásra kötve.
   ⚠ A hír- és blokkszöveg HTML: a publikus sablon **szűrve** (engedélyezett címkék listájával) jelenítse meg, ne nyersen.
   *Itt dől el, hogy a Blade + htmx + az áthozott CSS együtt működik-e. Ma bizonyítatlan.*
   **A tartalom már kész hozzá:** menü (`Menu`/`MenuItem::target()`, `show_hu`/`show_ro` szerint szűrve), lapok
   (`Page` + `PageText` kulcsok + `PageBlock`), hírek (`NewsItem`, csak `published`). ⚠ A publikus lapon a
   TESZT-hír is látszik (szándékosan, hogy tesztelni lehessen) — élesítés előtt a Tesztadatok törlése kötelező.
2. **A beégetett szövegek pótlása a lapokon** (9. adósság) — a publikus sablonok megépítésével együtt.
3. **A programkereső** — a legnagyobb és legösszetettebb darab.
4. **A két hátralévő egyedi szerkesztő** — `/szerkeszto`, térkép-szerkesztő (a `/tema` kész).
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
2. ~~Hiányzik a világos/sötét váltó a beküldő űrlapon~~ — **törlesztve 2026-10-21** (◐, a 2026-os
   űrlap világos palettájával; az Arculat-szerkesztőben a „Világos” oszlop állítja).
3. **A koordinátor nem látja a fotót** olyan felületen, ahol még nincs megoldva — új felület
   építésekor ellenőrizni.
4. **Export, import és helyszín-lap** még nem létezik; amikor megépül, **kategóriagazda-fiókkal
   végig kell próbálni**, a közvetlen címet is begépelve. *(A PROMPT-08 új lapjai — hírek, menü, aloldalak —
   így végigpróbálva, 2026-10-28: mind 403.)*
5. ~~**Élő hiba:** a beküldő űrlap extra mezői megszűnt kategória-slugra hivatkoznak~~ — **törlesztve 2026-10-28.**
   A kezdő kategórialista (`seed/categories.csv`) most a 2026-os végleges 23 (román névvel); a meglévő adatbázist egy
   migráció igazítja (a megszűnt 5-ből csak a használatlant törli). Átnézve: a `kerekasztal-beszelgetesek` volt az
   EGYETLEN megszűnt slug az extra mezők között; a 4 új kategóriának 2026-ban sem volt extra mezője (nem találtunk ki).
   **Védelem:** ha a leírás nem létező kategóriára hivatkozik (törölt/átnevezett), **piros figyelmeztetés a nyitólapon**
   (`App\Filament\Widgets\Figyelmeztetesek`), és a teszt a seed-listával is összeveti.
6. ~~A téma fájljai külön tárban vannak~~ — **törlesztve 2026-10-22**: a médiatár „Arculat” gyűjteményében
   látszanak (ugyanaz a fájl, nem másolat); cserélni továbbra is a /tema-ban lehet.
7. **Az admin témáját kézzel kell fordítani** (`npm run admin-tema`, a fejlesztőgépen), ha új Filament-nézet
   új Tailwind-osztályt használ — különben az új osztály a szerveren hatástalan. A lefordított fájl a tárolóban van.
8. **A Curator két részletét átvettük** (a panel „Fájlok hozzáadása” művelete, a képválasztó panel-indítója) —
   a csomag frissítésekor össze kell vetni a sajátunkat az újjal (`App\Livewire\MediaPanel`, `App\Filament\Forms\MediaPicker`).
9. **A 2026-os aloldalak szövegének nagy része BEÉGETETT magyar** (tulajdonosi rögzítés, 2026-10-28 — MOST NEM javítjuk).
   A 2026-os Astro-sablonokban a szöveg zöme nem szerkeszthető kulcson volt; a fesztiválrend lapján például a szövegnek
   csak **18%-a** szerkeszthető. Átjött, ami szerkeszthető volt (85 kulcs, az aloldal lapján „Szerkeszthető szövegek”);
   **a többi a publikus sablonok körében pótlandó** (forrás: `..\forgatag-os\frontend\src\rolunk\`, `fesztivalrend\`,
   `videoarchivum\`, `home-v7\` stb. — csak olvasásra), úgy, hogy minden szöveg kulcsra kerüljön, HU/RO.

## Amit a mentés leltára felszínre hozott

- **Kategóriák: 23, nem 24.** Öt megszűnt, négy új jött. *(2026-10-28: a kezdőlista már ez a 23.)*
- **Egyetlen helyszínnek sincs GPS-koordinátája** (46/46 üres).
- **A 12 hír egyikének sincs román szövege.**
- **A program román címe két helyen van**, és 3 programnál eltér — importkor tisztázni kell.
- **A jóváhagyási és változási napló üres** — 2026-ban nem írt bele semmi.
- **A mentés hitelesítő adatokat tartalmaz** (Directus-tokenek nyílt szövegben) és személyes adatot.
  Ne kerüljön felhőbe szinkronizált mappába.
- **A NAS napi mentése hibás volt** (`pg_dump | gzip` — a sikert a gzip adta vissza). A mostani
  mentés ép, de a régebbiekre nem lehet támaszkodni.
- **A 9 lapblokk mind gazdátlan volt** (egy nem létező laphoz kötve, próbaszöveggel) — nem jött át (2026-10-28).
- **A menü két pontja („Programok”, „Médiatár”) 2026-ban cím nélküli „link” volt** — most „csak almenü-fejléc” típus.

## Nyitott kérdések — ne döntsd el magad

- A 2027-es fesztiválnapok pontos dátumai és az előkészítő napok száma.
- A valódi kategóriagazdák és jóváhagyók személye.
- ~~A médiatár megoldása~~ — **eldöntve 2026-10-21, megépítve 2026-10-22** (`CLAUDE.md` 2/d és 8. pont).
- ~~A 2026-os programborítók helye~~ — **eldöntve 2026-10-22**: „Programok 2026” gyűjtemény, eredet-jelöléssel,
  2027-es programhoz nem rendelhető. (A PROMPT-07 ezzel ellentétes sora javítva.)
- **ÚJ — a szponzorszintek:** a 2027-es kezdőlista a 2025-ös 9 szint, román név nélkül; 2026-ban 11 szint
  volt, más nevekkel („Szervezők”, „Médiapartnerek”, „Kiemelt partnerek”, „Társszervezők”...). A 2026-os
  szintek most évhez kötve, külön jöttek át. Melyik legyen a 2027-es lista?
- **ÚJ — a „Rendezetlen” 59 képe:** 34 demó-kép (2026-os próbatartalom) és 25 egyéb, amire semmi nem
  hivatkozott (pl. 3 logó PDF-ben, néhány fotó). Törölhetők, vagy rendezzük őket?
- **ÚJ — a zárt tár 26 fotója:** 2026-ban beküldött, de meg nem jelent programok fotói
  (`storage/app/private/bekuldesek-2026/`, mellettük `LISTA.json`). Megtartsuk vagy töröljük?
- **ÚJ — az önkéntesek 34 melléklete** (szülői hozzájárulás, motivációs levél) szándékosan NEM jött át
  (személyes adat). A mentésben megvannak; ha az önkéntes-űrlap köre kéri, onnan hozható.
- **ÚJ — a „Kedvenc programjaim” lap:** 2026-ban a NAS-ra hívott (kedvencek-API); a 13 lappal együtt átjött
  (egy az egyben), de a funkció a 2027-es rendszerben még nincs meg. Kell-e 2027-ben kedvenclista?
- Napi automatikus adatbázis-mentés: **be kell állítani, és egyszer vissza is kell tölteni.**
  Nem elég beállítani — lásd a NAS tanulságát.
