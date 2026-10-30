# HOL TARTUNK

> **Ezt olvasd el először, minden új beszélgetés elején.** Utána a `CLAUDE.md`-t.
> **Ezt a fájlt minden kör VÉGÉN frissíteni kell** — a 2026-os `HANDOFF.md` azért vált
> használhatatlanná, mert senki nem frissítette.

**Utolsó frissítés:** 2026-10-30 (PROMPT-11, programkereső — 1. rész; a 2. rész a térkép)

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

- **Publikus váz** (`PROMPT-09`, 2026-10-29) — **kész, élesben telepítve és megnézve (2026-10-29).**
  **Élesben mérve (proba.forgatag.ro):** minden publikus cím 200, `?oldal=1` → 301, túl nagy lapszám és meg nem épült rendszerlap → 404;
  `/admin/login` és `/bekuldes/belepes` 200; Filament-CSS a lapon 0; a téma-CSS, a htmx és az Alpine betölt, konzolhiba nincs;
  a képek (Glide/webp) 200; a román fejlécből a „Hírek” eltűnik; 375 px-en Rólunk, mobil menü, román hírlista, Hamarosan rendben.
  **A kérdésre a válasz: igen — a Blade + htmx + Alpine + az áthozott CSS együtt működik.** Részletek: `CLAUDE.md` 8. pont „Publikus váz”.
  - **Keret külön fájlokban:** `layouts/app` · `partials/header` · `partials/nav` · `partials/footer`. A menü az adatbázisból, nyelvenként
    rejtve (a „Hírek” pont románul rejtett — a román fejlécből valóban eltűnik). HU a gyökérben, RO a `/ro/` alatt; nyelvváltó a téma kapcsolójával.
  - **Lapok:** Rólunk (`/rolunk`), hírlista (`/hirek`), hír (`/hirek/<slug>`), saját aloldal közös sablonnal, Hamarosan (`/hamarosan`, és a gyökér).
  - **A négy külön ellenőrzés:** (1) **lapozás JS nélkül** rendes linkekkel, a lapszám a címsorban (`/hirek?oldal=2`), a lapcímben is
    („Hírek – 2. oldal”); htmx-szel csak a lista cserélődik, a lap nem töltődik újra, a „vissza” gomb működik (böngészőben mérve, 18 hírrel).
    (2) **Filament-CSS nincs a publikus lapon** — a forrásban ellenőrizve, és teszt nézi lapról lapra. (3) **Tingler 400 + `font-synthesis:none`**:
    minden címbetűs szabály a téma vastagságát kapja (a TemaTest az új CSS-fájlokat is végignézi). (4) **A Rólunk MINDEN szövege
    szerkeszthető**: 43 kulcs (12 új — köztük a 2026-ban beégetett logónevek és a webshop címe), a lap csak az adatbázisból olvas; teszt
    cseréli ki az összes szöveget, és ellenőrzi, hogy beégetett szó nem maradt.
  - **Az admin „Szerkeszthető szövegek” része emberi feliratot mutat** (pl. „Történet — 2. bekezdés”) a kulcsnév helyett, a lapon
    elfoglalt sorrendben; kép-kulcsnál képválasztót, linknél egy mezőt.
  - **Hamarosan mód:** Beállítások → új „Publikus oldal” blokk (kapcsoló + a lap címe/szövege HU/RO + a lábléc szövege). Bekapcsolva a
    látogató minden publikus címen a Hamarosan lapot kapja; a `/bekuldes` és az `/admin` változatlan (mérve); belépett admin a valódi lapot
    látja egy sárga sávval. **Alapból KI.**
  - 375 px-en: Rólunk, Borkert-ablak, mobil menü, hírlista, hír, Hamarosan — vízszintes görgetés nincs. Közben javítva: a nyitott mobil
    menü alatt eltűnt a fejléc (`overflow-x` csapda, `CLAUDE.md`). **137 teszt zöld.**
  - Döntések (konzervatív): a publikus oldal **sötét-only** marad (mint 2026-ban); a **GTM még nincs** bekötve (a próbaoldal ne mérjen a
    valódi fiókba); a Rólunk görgetésre rajzolódó „fonala” és a felúszás kimaradt (dekor — a kezdőlap körében jön).

- **Programkereső — 1. rész** (`PROMPT-11`, 2026-10-30) — **kész HELYBEN, még NINCS élesben** (lásd „MOST folyik”). **A kör KÉT részre
  vágva** (a felhasználó engedélyével: „inkább két kör, mint egy félkész”): ez a rész a kereső + napi nézet + program lapja; a **2. rész a
  térkép, a helyszín- és a kategórialapok** (lent, „Ami ezután jön”).
  - **`/programok`** — szűrés nap · „Most zajlik” · **napszak** · helyszín · kategória · szabadszavas keresés szerint, **valódi
    adatbázis-lekérdezéssel**. **A szűrés a címsorban** (`/programok?nap=2027-08-27&napszak=este&kategoria=koncertforgatag&q=jazz`;
    több érték = ismételt kulcs). Egy szűrésnek egy címe van: a JS nélküli űrlap üres mezői, az ismeretlen érték és a rossz nap a tiszta
    címre ugrik (302). **htmx-szel csak a találati rész cserélődik**; a kattintás azonnal kér, a gomb kiemelése még a válasz előtt
    történik; a kereső ~280 ms-ot vár. A „vissza” gomb a szűrt állapotot hozza vissza. **JavaScript nélkül is működik**.
  - **A 2026-os viselkedés visszaállítva (CLAUDE.md 2/e, 2026-10-30 — a tulajdonos visszakérte):**
    **felugró ablak** a listából (a címsorban `?program=<slug>`, megosztható; Esc/háttér/× bezárja, a „vissza” gomb is; a program
    lapja megmaradt a keresőknek és JS nélkül) · **„Napszak” szűrő** (délelőtt/délután/este; az „egész nap nyitva” mindig átmegy; a
    „Most zajlik”-kal kizárják egymást) · **napi kiemelt sáv** csúszkával (új adatmező: `program_highlights`; az admin program-lapján
    **„Napi kiemelés”** gomb — napok + sorrend, csak jóváhagyó). Továbbá, amit közben a 2026-os kódból összevetve találtam:
    **kezdőállapot** (fesztivál előtt a nyitónap, alatta „Most zajlik”, utána az utolsó nap) · a kiválasztott nap **újbóli megnyomása
    kikapcsolja** (nincs „Minden nap” gomb) · a **keresés minden napra vált**, kiürítve vissza a kezdőnapra · **minden találat egy lapon**,
    nap → napszak csoportokkal (2026-ban nem volt lapozás — a PROMPT-11 lapozását ezért kivettem) · az **aktív szűrők sávja üresen is ott
    van** · **üres találatnál javaslat** („Ha elengeded a napszakot, 3 program marad…”) · **korhatár-jelzés** a sorban · a „Változott/
    Elmarad” kék jelzés · **kép nélküli sorban nincs üres csempe** · a keresés a **címben, szervezőben, fellépőben, helyszín- és
    kategórianévben** keres, a leírásban nem · a szűrőpanel 2026-ban szerkeszthető feliratai (Szűrés, Napszak, Helyszín, Kiemelt programok,
    a felugró ablak feliratai…) **szerkeszthetők** (Aloldalak → Programkereső).
  - **Még NINCS meg a 2026-os keresőből — mind egy KÉSŐBBI körben épül (2/e: nem maradhat el szó nélkül):**
    · **térkép-kör (2. rész):** a térkép, a felugró ablak „Mutasd a térképen” gombja, a helyszínlista térképszámai, a mobil „Térkép” gomb;
    · **kedvencek-kör:** a szív a sorokon/kártyákon és a felugró ablak „Mentés” gombja;
    · **naptár-kör:** a felugró ablak „Naptárba” gombja;
    · **mozgás-/dekor-kör:** a görgetéskor beúszó sáv (oldalnév + „Szűrés” + találatszám), a ragadó panelfej összehúzódása, a mobil
      eszközsáv beúszása, a háttér-atmoszféra és a firkák, a kártyák felúszása;
    · **ellenőrizendő a 2. részben:** a napi nézet (`/nap/…`) és a program lapja kinézete a 2026-os `DailyProgramView`/`ProgramDetailView`
      mellé téve — ezeket ebben a körben nem vetettem össze sorról sorra.
  - **Napi nézet** `/nap/<dátum>`, **„Mi van ma?”** `/ma` (ha ma nincs fesztivál: a **nyitónap**), **program lapja** `/program/<slug>`.
  - **A fesztiválnap hajnali 3-ig tart** (a záró óra a Beállításokból): a 23:00–00:30 a kezdés napján, a nap végén; a 00:30-as kezdés
    „(éjjel)” jelzéssel a nap végén. A „Most zajlik” is így számol (szombat 01:00 = még péntek).
  - **A logó-csapda:** a találatszám sora a szűrők FÖLÖTT van — a logó erre a nem kattintható szövegre lóg; a ragadó szűrőpanel a belógás
    alatt áll meg. Mobilon a szűrő alulról felcsúszó panel, alul a „Szűrés” gomb a szűrők számával.
  - **A térkép helye előkészítve:** a csere célterülete (`#pk-talalatok`) és a „vissza” gomb újratöltése (`#pk-tartalom`) is a térkép
    helyén KÍVÜL van — a térkép eseménykezelőit egyik sem bántja. (Kipróbálni a 2. részben lehet, amikor a térkép megvan.)
  - **Románul** végigpróbálva: approved → reviewed → gépi fordítás; az elavult helyett és fordítás nélkül a magyar; a program lapján
    apró jelzés, ha gépi fordítás látszik. A nyelvváltó megtartja a szűrést.
  - **Tesztadat:** a 2026-os programok TESZTADATKÉNT (`is_test`, dátum +364 nap, hogy a hét napja egyezzen; személyes adat nélkül;
    borító a „Programok 2026”-ból) — `forgatag:program-teszt-import`, a telepítés része, csak egyszer. Beállítások → Tesztadatok törli
    (a megmaradt 2026-os képek a médiatárban maradnak). **A helyszín nélküli 61 program NEM jön, és a borítójuk a médiatárból is
    törlődik** (tulajdonosi döntés, 2026-10-30, „a képeikkel együtt”) → **180 tesztprogram**. Helyben már törölve (61 program + 61 kép a
    fájlokkal); a szerveren a behozatal maga hagyja ki őket és törli a képeiket (a médiatár-behozatal lezárt, ezért ez nem hiba).
  - 375 px-en: kereső, felcsúszó panel, lista, felugró ablak, napi nézet, program lapja — vízszintes görgetés nincs. **156 teszt zöld**.
  - Közben javítva: a lapok `@section('title', …)` értéke kétszer escape-elődött (egy „&” a címben `&amp;amp;` lett, a megosztási kép
    címe eltört) — a keret most visszafejti.

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
4. **A publikus váz** — élesben kész (2026-10-29). Hátra (a felhasználóval): a Beállításokban a „Publikus oldal” blokk
   megnézése belépve, és egyszer a Hamarosan kapcsoló ki-be próbája.
5. **A programkereső (1. rész) élesítése** — helyben kész, **még nincs feltolva és telepítve.** Telepítés után a naplóban:
   `LAPSZÖVEG: programok: a rendszerlap létrehozva` és `PROGRAM-TESZT: 180 program, … 61 kihagyva (helyszín nélkül), 61 törölt kép`, és egy új migráció (`program_highlights`).
   A médiatár ezután 61 képpel kevesebbet mutat (970 a 1031-ből) — szándékos. Élesben megnézendő:
   `/programok` szűrés kattintással és gépeléssel, egy szűrt cím új ablakban, `/nap/2027-08-27`, egy program lapja, románul, 375 px.
   ⚠ **A válaszidőt élesben kell mérni:** helyben minden lap (a hírek is) ~6,5 mp — ez a Windows-os Docker mappamegosztása, nem a kód.

## Ami ezután jön, ebben a sorrendben

1. ~~**A publikus váz**~~ — **kész (2026-10-29)**, lásd fent. Ami tőle örökölve a következő lapokra: a `LapSablon` (minden szöveg kulcs),
   a `layouts/app` keret, a `publikus.css` közös része. ⚠ A menü ma olyan lapokra is visz, amelyek még nem épültek meg (404).
   ⚠ A hír- és blokkszöveg HTML: a publikus sablon **szűrve** (engedélyezett címkék listájával) jelenítse meg, ne nyersen.
   *Itt dől el, hogy a Blade + htmx + az áthozott CSS együtt működik-e. Ma bizonyítatlan.*
   **A tartalom már kész hozzá:** menü (`Menu`/`MenuItem::target()`, `show_hu`/`show_ro` szerint szűrve), lapok
   (`Page` + `PageText` kulcsok + `PageBlock`), hírek (`NewsItem`, csak `published`). ⚠ A publikus lapon a
   TESZT-hír is látszik (szándékosan, hogy tesztelni lehessen) — élesítés előtt a Tesztadatok törlése kötelező.
2. **A beégetett szövegek pótlása a lapokon** (9. adósság) — a publikus sablonok megépítésével együtt. **A Rólunk és a hírlista kész;**
   a többi lap a saját sablonjával (minta: `LapSablon::SABLONOK`).
3. **A programkereső** — a legnagyobb és legösszetettebb darab. **1. rész kész (2026-10-30, fent).** **Hátra a 2. rész:**
   **a térkép** (beágyazott SVG, kattintható helyszínek — a `#pk-tartalom`-on KÍVÜL, lásd `CLAUDE.md` „Programkereső”), **a helyszín- és a
   kategórialapok** (ma a program lapja ezek helyett a szűrt keresőre visz: `/programok?helyszin=…`). Mit kell tudni hozzá:
   - **A térkép-pozíció a 2027-es adatbázisban még NINCS** (a `venues` tábla `map_*` mezői mind üresek) — a 2026-os mentésből kell
     áthozni (`venue.map_zone/map_x/map_y/map_no/map_edge/map_bearing`, 41 helyszínnél van). GPS egyiknél sincs (46/46).
   - **Az 5 helyszín térkép-pozíció NÉLKÜL** (a 2026-os mentésben; zárójelben: a térkép szélén jelölve-e): **Vár, Várkert** (nem) ·
     **Állatkert** (igen) · **Kultúrpalota, nagyterem** (nem) · **Marosvásárhelyi vikendtelep** (igen) · **Fociakadémia** (igen).
   - **8 helyszín a 2026-os adatbázisban van, a 2027-es kezdőlistában NINCS** (a tesztprogramok közül emiatt 61 nem jött át — a 2026-ban is helyszín nélküliekkel együtt):
     `var-csicsergo-1`, `var-csicsergo-2`, `bernady-haz`, `var-pulzus-by-rmdsz-maros`, `studium-hub-eloadoterem`, `fociakademia`,
     `bolyai-szabadulo-szoba`, `uat-galeria`. Ha a tulajdonos igent mond (Nyitott kérdések), a 2. rész első lépése a pótlásuk.
   - A fejléc keresője (téma: `header.showSearch`) még nincs bekötve — a 2. részben a `/programok?q=`-ra vihet.
   - A térkép a szűrésre CÉLZOTT frissítéssel reagáljon (pl. a válaszban egy adat-elem, nem a térkép újrarajzolása); a „szűrj, aztán
     kattints a térképre” próbát ott kell elvégezni.

   ⚠️ **FONTOS: EGYETLEN TARTALMI LAP SEM VÉGLEGES KINÉZETŰ.** Ami eddig elkészült (Rólunk,
   hírlista, hír), az **szerkezetileg kész, de dekor nélkül**. Hiányzik: a görgetésre kirajzolódó
   „fonál" és a mentén szórt firkák, a szakaszok felúszása, a kártyák fényes kiemelése, és a
   háttér egyik színfoltja. **Ez nem hiba, hanem szándékos halasztás** — lásd a 4. pontot.

4. **A mozgás- és dekor-réteg** — ÖNÁLLÓ LÉPÉS, nem a kezdőlap része.
   *Indok: ez nem laponkénti munka, hanem egy réteg, amit az Arculat „Mozgás" és „Firkák"
   beállításai vezérelnek — ahogy 2026-ban is. Ha laponként készülne, elcsúsznának egymástól, és a
   `/tema` kapcsolói nem hatnának rájuk egységesen.*
   **Visszamenőleg hozzányúl minden addig elkészült laphoz** (Rólunk, hírlista, hír, programkereső),
   ezért nem szabad a kezdőlap körébe ágyazni — ott váratlanul nagyra nőne és félkészen állna meg.

5. **A kezdőlap** — a legösszetettebb lap; a dekor-rétegre épül.
6. **A két hátralévő egyedi szerkesztő** — `/szerkeszto`, térkép-szerkesztő (a `/tema` kész).
7. **A fordítási lánc.**
8. **A maradék publikus lapok** — fesztiválrend, galéria, videó-archívum, támogatók, kézművesek, csapat.
9. **Élesbe tétel** — **a legutolsó lépés**, lásd lentebb. Addig nem téma.

**A három hátralévő űrlap** (szabadulószoba `PROMPT-03`, olimpia, önkéntes) **levél a fán**: semmi
nem épül rájuk, bárhova beilleszthetők — például egy nagyobb darab után rövidebb körként.

## Az élesbe tétel

⛔ **A LEGUTOLSÓ LÉPÉS. Addig nem foglalkozunk vele, és nem is hozzuk szóba.**
A `forgatag.ro` és a 2026-os oldal változatlanul él tovább; **a fejlesztés alatt a 2026-os oldalhoz
nem nyúlunk.**

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
   **a többi a publikus sablonok körében pótlandó** *(2026-10-29: a Rólunk és a hírlista TÖRLESZTVE — `App\Support\LapSablon`)* (forrás: `..\forgatag-os\frontend\src\rolunk\`, `fesztivalrend\`,
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

- ~~Felugró ablak vagy program-lap?~~ — **eldöntve (2026-10-30): a listából a felugró ablak nyílik**, a program lapja megmarad (SEO).
- **ÚJ (PROMPT-11) — a 8 hiányzó helyszín** (lásd „Ami ezután jön”, 3. pont): felvegyük-e őket a 2027-es helyszínlistába?
- ~~A tesztadat törlése~~ — **eldöntve (2026-10-30): a 61 helyszín nélküli tesztprogram a képeivel együtt törölve** (lásd fent, Tesztadat).

- **ÚJ — a Rólunk román zárómondata hibás adat** (a 2026-os behozatalból): a „záró bekezdés” román szövege így végződik:
  „…ne unește.**összeköt minket.**” — magyar szó maradt benne (a heurisztika: a román szövegben nincs ő/ű, de itt ö van). Javítani az
  adminban kell (Aloldalak → Rólunk → Szerkeszthető szövegek → „Történet — záró bekezdés”). A külön „záró kiemelés” kulcs megszűnt:
  2026-ban sem látszott (a bekezdés felülírta), és a román mezőjében az egész bekezdés állt.
- **ÚJ — a logón még „13.” áll** (a 2026-os logó). A 2027-es logót az Arculatban kell feltölteni (logó + fejléc-logó).
- **ÚJ — világos mód a publikus oldalon?** Most sötét-only (mint 2026-ban). Ha kell váltó, szólj.

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
