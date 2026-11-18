# HOL TARTUNK

> **Ezt olvasd el először, minden új beszélgetés elején.** Utána a `CLAUDE.md`-t.
> **Ezt a fájlt minden kör VÉGÉN frissíteni kell** — a 2026-os `HANDOFF.md` azért vált
> használhatatlanná, mert senki nem frissítette.

**Utolsó frissítés:** 2026-11-18 (PROMPT-19: a fordítási lánc kész helyben, 226 teszt zöld; a kulcs beírása, a cron és az élő próba hátra)

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
  - **A 2026-os JELÖLÉS ÉS CSS BETŰRE ÁTVÉVE (2026-10-30, a tulajdonos második visszajelzése: „ne értelmezd — emeld át”):**
    a stíluslap a 2026-os `style.css` változatlan másolata (`public/css/programkereso.css`), a jelölés a 2026-os `body.html` +
    a JS-ben épített részek (kártya, csoportok, aktív szűrők, felugró ablak) ugyanazokkal az osztályokkal. Ezzel visszajött:
    **a szűrők VÍZSZINTES sávban a lap tetején** (asztalon, ≥1041 px; a találatszám a sáv első sora, a logó-behúzás mérve, mint
    2026-ban) · **a térkép HELYE a lista mellett jobbra** (440 px-es hasáb, most üres — a 2. rész tölti meg; mobilon a térképlap
    helye is megvan) · **a 2026-os tömör sorok** · **a felugró ablak mind a négy gombja** · a görgetéskor beúszó sáv, a ragadó
    panelfej, a mobil eszközsávok (inline + alul beúszó) · a 2026-os linkfelirat-szabály (domain vagy „Jegyvásárlás”).
    A korábbi, értelmezésből épült saját elrendezés (bal oldali szűrőoszlop, a „találatszám-sor a szűrők fölött”) megszűnt.
  - **Javítások a tulajdonos negyedik visszajelzése után (2026-11-01):**
    · **Bizonyítva, hogy a 2026-os kódból jön** (`../forgatag-os/frontend/src/programkereso/script.js`): a kezdőállapot a 86–99.,
      622. és 630. sor (`isFestival`, `startDayIdx`, `S.now = true`, `S.day = startDayIdx()`), a keresés minden napra váltása a
      690–692. sor. Az összevetéskor két apró eltérést javítottam az átiratban: a „Most zajlik” csak a fesztivál ideje alatt
      (nyitónap–záró nap) kezdőállapot, az előkészítő napon nem; a kereső kiürítése a kezdő NAPRA áll vissza (nem a „Most”-ra).
    · **A fejléc (és a lábléc) színe:** 2026-ban a `chrome.css`-be égetett rgba volt (fejléc `rgba(24,19,52,.78)`, lábléc
      `rgba(16,12,36,.7)`), a 2027-es keret a `--bg`-ből kevert. Most **két téma-token**: `colors.headerBg` / `colors.footerBg`
      (`--header-bg`, `--footer-bg`) a 2026-os értékkel — az Arculat „Színek — Alapok” részében állítható; a kint lévő témát egy
      migráció igazítja (új verzió).
    · **A rés a fejléc és a szűrősáv között:** a 2027-es keret `<main>`-je minden lapon 40–84 px felső térközt ad (a cikk-
      lapokhoz); a programkereső most a saját szekcióival adja a térközt (`@section('ter', 'sajat')`), mint 2026-ban.
    · **Sebesség — mérve:** élesben (proba) egy nap 0,48–0,66 s, minden nap 0,68 s volt az első bájtig (a /hirek 0,31 s); helyben
      a meleg kérés 763 ms, ebből az adatbázis **csak ~16 ms** (29 lekérdezés) — **indexelés nem kell**. Az időt a kirajzolás vitte:
      kártyánként és szűrő-soronként külön beillesztett sablon (73 kártya ~420 ms). Most egy-egy sablon rajzolja az egész listát,
      a jelzéseket PHP írja: **a meleg kérés 763 → 169 ms** (minden nap: 348 ms). A tömörítés élesben megy (brotli: 502 → 83 kB).
      ⚠ Élesben újramérendő a telepítés után.
  - **Javítások a tulajdonos ötödik visszajelzése után (2026-11-01):**
    · **Gyorsítótár-törés minden saját CSS/JS-re, EGY helyen:** a szerver a statikus fájlokat 7 napig gyorsítótárazza; eddig a
      lapok fájlonként, a módosítás IDEJÉBŐL kaptak `?v=`-t (a két vendor-szkript semmit). Most mindegyik a `@eszkoz(...)`
      direktívával, a fájl TARTALMÁNAK lenyomatával (`App\Support\Eszkoz`; az admin témája is). Teszt őrzi, hogy sablonban ne
      maradjon más hivatkozás.
    · **A lábléc eltűnt a programkeresőn:** nem a magasság nyelte el — a lábléc ott volt a lap alján, de a 2026-os rögzített
      háttérréteg (`.fgl-atmo`) fölé rajzolódott, mert a 2027-es lábléc nem kapott saját réteget (2026-ban `z-index:5`-tel
      kapott). Most kap (a közös rétegskálából). Asztalon és 375 px-en a lista aljára görgetve ellenőrizve.
  - **A gombok helye megvan, a működésük a saját körükben kapcsol be (addig tiltva, halványan):**
    · ~~**térkép-kör (2. rész):** a térkép maga, a felugró ablak „Mutasd a térképen” gombja, a mobil „Térkép” gomb, a helyszínlista
      térképszámai~~ — **kész (PROMPT-12, 2/a rész)**;
    · **kedvencek-kör:** a szív a sorokon/kártyákon és a felugró ablak „Mentés” gombja;
    · **naptár-kör:** a felugró ablak „Naptárba” gombja;
    · ~~**mozgás-/dekor-kör:** a lapon szórt firkák és a kártyák felúszása~~ — **kész (PROMPT-13)**; a kereső kártyái 2026-ban sem úsztak fel;
    · ~~**ellenőrizendő a 2. részben:** a napi nézet és a program lapja a 2026-os `DailyProgramView`/`ProgramDetailView` mellé téve~~ —
      **megtörtént (PROMPT-15)**: mindkettő a 2026-os jelöléssel újraépítve és mérve (lent).
  - **Napi nézet** `/nap/<dátum>`, **„Mi van ma?”** `/ma` (ha ma nincs fesztivál: a **nyitónap**), **program lapja** `/program/<slug>`.
  - **A fesztiválnap hajnali 3-ig tart** (a záró óra a Beállításokból): a 23:00–00:30 a kezdés napján, a nap végén; a 00:30-as kezdés
    „(éjjel)” jelzéssel a nap végén. A „Most zajlik” is így számol (szombat 01:00 = még péntek).
  - **A térkép eseménykezelői biztonságban:** a htmx csak a listát (`#pkResults`) és id szerint néhány számlálót/sávot cserél; a
    térkép két helye (`#pkMapCol`, `#pkMapSheetIn`) egyik csere célpontja sem. (Kipróbálni a 2. részben lehet, amikor a térkép megvan.)
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

- **Programkereső — 2/a rész: a TÉRKÉP** (`PROMPT-12`, 2026-11-01) — **kész HELYBEN, még NINCS élesben.** A PROMPT-12 két
  körre vágva: **12/a** = térkép-adat + térkép + térkép-gombok + „szűrj, aztán kattints” próba + élő sebesség; **12/b** = helyszín- és
  kategórialapok + a napi nézet és a program lapja összevetése a 2026-os kóddal (lent, „Ami ezután jön”).
  - **Térkép-pozíció a 2026-os mentésből** (`forgatag:helyszin-terkep-import`, a telepítés része, ismételhető): csomag
    `database/import/helyszin-terkep-2026.json` (46 helyszín, 41 pozícióval, 3 a térkép szélén). CSAK az érintetlen helyszínt tölti
    (amit a térkép-szerkesztőben később beállítanak, ahhoz nem nyúl). Helyben 36 helyszín töltődött. **Nem találtam ki pozíciót:**
    · 2026-ban sem volt pozíciója, és **terem** → a szülő jelölője képviseli (mint 2026-ban): **Vár, Várkert** · **Kultúrpalota, nagyterem**;
    · 2026-ban a **térkép szélén, irányjelvénnyel** volt → ugyanúgy: **Állatkert** · **Marosvásárhelyi vikendtelep**;
    · **Fociakadémia** — 2026-ban szélső jelvény volt, de a 2027-es listában nincs (a 8 hiányzó egyike, Nyitott kérdések).
  - **A térkép BETŰRE a 2026-osból** (`lib/finder-map.ts`, `lib/offmap.ts`, `programkereso/script.js` térkép-részei):
    `programok/_terkep.blade.php` (jelölés), `public/css/terkep.css` (a 2026-os CSS, csak a címbetű súlya a témából),
    `public/js/terkep.js`. Vár/Város fül, pásztázás-nagyítás (húzás, csippentés, görgő, +/−/⤢), számozott jelölők, **pakli** a
    többtermes helyszínen („Az egész helyszín” + termek), szélső irányjelvények, buborék (név + programszám), **jelmagyarázat**
    sorszámmal és programszámmal (kattintva szűr), „Közérdekű” sáv. Asztalon a lista mellett jobbra, mobilon a „Térkép” gomb lapján.
  - **A térkép EGYSZER rajzolódik, a htmx nem cseréli:** a jelölő az űrlap helyszín-pipáját állítja (ugyanaz a halmaz, mint a
    szűrőlistában), a válaszban egy kis adat-elem (`#pkTerkepAdat`: helyszín → programszám, kiválasztottak) jön, abból frissül.
    A szűrő-válasz a térképet el sem készíti. **Próba („szűrj, aztán kattints”):** kategória-szűrés → valódi egérkattintás a jelölőre →
    a lista szűkült (7 → 4), a cím `…&helyszin=…`; újabb kattintás két csere után is működik; pakli, jelmagyarázat, fülváltás jó.
  - **Bekapcsolt gombok:** a mobil „Térkép” (mindkettő), a felugró ablak **„Mutasd a térképen”** (bezárja az ablakot, a jó fülre vált,
    odagörget / mobilon a térképlapot nyitja, vízgyűrűvel kiemel; csak ha a helyszín a térképen van — mint 2026-ban).
  - Románul (Cetate/Oraș, feliratok) és 375 px-en ellenőrizve: vízszintes görgetés nincs, a lábléc látszik. **161 teszt zöld.**
  - **Tulajdonosi döntés (2026-11-01), eltérés a 2026-ostól:** a jobb oldali térkép-sáv görgetősávja nem látszik (rávitelkor sem);
    a sáv görgethető marad (`programkereso-szerver.css`). A helyszínlista saját belső sávja marad.
  - **Sebesség (helyben):** a térkép adata ~30 ms, csak a teljes lapbetöltéskor; a szűrő-válasz nem nőtt. ⚠ Élesben mérendő.

- **A mozgás- és dekor-réteg** (`PROMPT-13`, 2026-11-02) — **kész, élesben telepítve és megnézve (2026-11-02)**; a valódi telefonos próba hátra van (MOST folyik, 6.). Egy réteg minden publikus lapon
  (`public/js/mozgas.js` a keretben), a téma Mozgás és Firkák beállításai vezérlik. Részletek: `CLAUDE.md` 8. pont „Mozgás- és dekor-réteg”.
  - **Mi került be a 2026-os kódból:** a Rólunk szakaszainak **felúszása**, a görgetésre rajzolódó **fonál** a képek között a rajta haladó
    **karikával**; a hírkártyák **fényes kiemelése** (sorszám szerinti színnel, mint 2026-ban); a **lapon szórt firkák** a programkeresőn
    (`#pageDecor`); a napi nézet kártyáinak lépcsőzött felúszása; a háttér **kék színfoltja** (minden lapon).
  - **Amit a 2026-os kód mutatott, nem a feladatleírás** (2/e): a 2026-os élő Rólunkon a **fonál mentén nem volt firka** (csak a makettben) — nincs
    (tulajdonosi döntés); a **programkereső kártyái 2026-ban nem úsztak fel**, és a **hírlista kártyái sem** (a 2026-os kód rossz osztálynevet
    keresett) — ezért ott nincs felúszás. Ez a teljesítménynek is jó: a több száz kártyás keresőn nincs kártyánkénti figyelő.
  - **12 firka-motívum az Arculatban** (tulajdonosi döntés): 2026-ban a lapok 12 motívumból válogattak, de csak 6 hely volt (a másik 6 kódba
    égetve). Most 12 hely, mind feltöltött fájl; a 7–12. a 2026-os színleképezéssel (a firka-színek rájuk is hatnak). Egy migráció a kint lévő
    témát új verzióként kiegészíti (a meglévő 6-hoz nem nyúl).
  - **Minden kapcsoló hat** — böngészőben a programkeresőn mérve: sűrűség (52 → 26 → 0 firka), átlátszóság, firka-szín, lebegés ki, „MA”
    pulzálás, mikrointerakció; a Rólunkon és a napi nézeten a reveal-sebesség. Közben javítva: a napi nézet kártyáinál a kártya saját átmenete
    elnyomta a beúszásét (a csúszka nem hatott volna). A szerkesztő súgói most megmondják, melyik kapcsoló hol hat.
  - **„Kevesebb mozgás” (rendszerbeállítás) mindent felülír:** nincs felúszás (minden azonnal látszik), a firkák fel sem épülnek, minden
    animáció áll. JavaScript nélkül is minden látszik (a rejtés csak mozgás-engedéllyel indul). Böngészőben a szkript-ág kipróbálva; a CSS-ágat
    teszt őrzi (a panel ezt a beállítást nem tudja utánozni).
  - **Sebesség (helyben, mobilnézet, 272 kártya):** 52 firka felépítése átlag 8 ms; a 12 motívum egyszer van a lapon (közös készlet, a firkák
    csak hivatkoznak rá); görgetés-figyelő nincs; a firkák csak szélesség-változásra szóródnak újra (2026-ban telefonon a címsor
    be-/kicsúszására is — ettől ugráltak). ⚠ **Valódi telefonon még NINCS mérve** (lásd „MOST folyik”, 6.).
  - Döntések (egy-egy sor): a Rólunk felúszása a téma reveal-idejét kapja (2026: beégetett 0,7 mp) · a fonál halvány vonalának színe és a
    hírkártya 6. kiemelő-színe nincs a témában → a legközelebbi téma-színből kevertem · a Rólunk görgetés-számolása képkockánként egyszer fut.
  - 375 px-en: Rólunk, napi nézet, programkereső — vízszintes görgetés nincs. **168 teszt zöld** (új: `MozgasDekorTest`, 7).

- **A kezdőlap** (`PROMPT-14`, 2026-11-04) — **kész, élesben telepítve és megnézve (2026-11-04); telefonon, mobilneten a nyitókép 1–2 mp („MOST folyik”, 7.).** A gyökér (`/`, `/ro`) most a
  kezdőlap (a Hamarosan lap a `/hamarosan` címen marad). Részletek: `CLAUDE.md` 8. pont „Kezdőlap”.
  - **Betűre a 2026-osból (2/e):** a jelölés a `home-v7/body.html` (osztályok, sorrend, a `data-hs` kulcsok), a stílus a
    `home-v7/style.css` másolata (`public/css/kezdolap.css`), a közös modulok a 2026-os `lib/*.ts` kiírt CSS-e (galéria, videó,
    támogatói csempe + sáv, kártya-fény), a JS a `home-v7/script.js` és a `lib/gallery|video|rail` átirata. **Helyben a 2026-os élő
    lap mellé téve, szakaszonként mérve (1024 és 375 px): a nyitókép, a számok, a média és a „mögött állnak” szakasz pixelre
    egyezik; a hírek szakasza csak a tartalom (más hírek) miatt tér el — a kártya minden mérete azonos.**
  - **KÉT ÁLLAPOT, EGY KÓD (tulajdonosi döntés):** Beállítások → Publikus oldal → **„A kezdőlap állapota”**: *Fesztivál előtt / alatt*
    (a nyitóképen visszaszámláló, alatta a „Kiemelt programok” sáv) vagy *Fesztivál után* (**ma ez — a mai 2026-os lap**). A
    visszaszámláló a 2026-08-26 előtti jelölés/CSS/JS (git `122d8b3^`), a cél a **nyitónap** + a Beállításokban megadott óra
    (alapból 10:00, mint 2026-ban), a fesztivál időzónájával. Helyben oda-vissza kapcsolva, 375 px-en és asztalon is megnézve.
    A Kiemelt sáv 6 kártyája szerkeszthető cím + kép (mint 2026-ban: nem programokból jön).
  - **Minden szöveg és kép szerkeszthető:** Tartalom → Aloldalak → **Kezdőlap** (57 kulcs, a 2026-os nevekkel — a 2026-os tartalom
    rájuk jött át). Ami 2026-ban beégetett volt, az is kulcs lett: a „számokban” öt **értéke** (pl. „110+”), a két szervező
    neve és logója, a visszaszámláló négy felirata.
  - **A firkák** a téma 12 motívumából, `[data-decor]` jelöléssel → a sűrűség, a lebegés és a **„Mozgás-mennyiség” a kezdőlapon is hat**
    (2026-ban nem hatott). A felúszás a közös rétegé. „Kevesebb mozgás”: a firkák állnak, a számláló rögtön a végértéket mutatja,
    a partner-sáv nem léptet.
  - **A négy kivezetett elem** (fény-részecskék, „Pörgess egy programot!”, futó névsor, aurora) **nincs** — teszt őrzi (a lap, a CSS/JS,
    a téma-szerkesztő és a téma-adat).
  - **Teljesítmény:** az első dia `<picture>`: telefonon a kijelző arányára vágott 750×1400-as kép (asztalon 1920 széles), a fejben
    előre kérve; a 2–5. dia csak a lap betöltése után; minden más kép lusta (`loading="lazy"`), a megjelenés arányára vágva; a
    galéria ~470 képének címe NEM a lapban, hanem külön kérésben (`/galeria-adat.json`, csak az első megnyitáskor). ⚠ **Valódi
    telefonon, mobilhálózaton még NINCS mérve** (MOST folyik, 7.).
  - **Románul** a hírek nem látszanak (2026-os tulajdonosi döntés: nincsenek románul); a román szövegek a 2026-os behozatalból.
  - **12 új teszt** (`KezdolapTest`), **180 teszt zöld**.
  - Döntések (egy-egy sor): a partner-sáv a **legutóbbi év** támogatóit mutatja (ma a 2026-osakat — 2027-es még nincs) · az
    „Aftermoviek” sáv a **Videó archívum lap első videós blokkja** (a feliratán jelölve) · a fesztivál előtti címet a 2026-os
    `nowrap` helyett tördelhetőre hagytam (rövid címnél ugyanaz, hosszúnál nem lóg ki), és telefonon megkapta a 2026-09-01-i
    oldalsó levegőt · a szervezői logó 2026-ban a forgatag2025 oldalról jött; most a Rólunk lap kezdőképe (ugyanaz a logó, a
    médiatárban 2 új sorként) · a sorrend a 2026-os CSS-betöltési sorrend (a csúszka térköze ezen múlt).

- **Helyszín- és kategórialapok, `/terkep`, napi nézet, program lapja** (`PROMPT-15` = a korábbi 12/b, 2026-11-04) — **kész helyben,
  ÉLESBEN telepítve és megmérve (2026-11-04) — „MOST folyik”, 8.** Részletek: `CLAUDE.md` 8. pont „Base-lapok”.
  - **A 8 hiányzó helyszín pótolva** (tulajdonosi döntés, 2026-11-04) a 2026-os nevükkel, címükkel, szülőjükkel és térkép-adatukkal
    (`seed/venues.csv` + `forgatag:helyszin-terkep-import`, ismételhető). Közben **mind a 46 2026-os helyszín megkapta a 2026-os román
    nevét** (2027-ben egyiknek sem volt — a román lapokon magyar helyszínnév állt volna; csak üres mezőbe ír). A **tesztprogram-behozatal
    egyszeri pótló lépése** a 8 helyszín programjait hozta: **60 program** (a 61-ből egynek 2026-ban sem volt helyszíne), mind TESZT
    jelöléssel, **mind fotó nélkül** — a borítójukat 2026-10-30-án a képeikkel együtt törölték (így épp ezeken látszik a fotó nélküli kártya).
  - **BETŰRE a 2026-osból (2/e):** a 2026-os `global.css` (`public/css/alap.css`, a Base-lapok közös stílusa), a `ProgramCard`
    (`partials/programkartya`), a `TaxonListView` (`/helyszinek`, `/kategoriak`), a `VenueDetailView` (`/helyszin/<slug>`: a helyszín ÉS a
    termei programjai), a `TaxonDetailView` (`/kategoria/<slug>`: elsődleges + további kategória), a `DailyProgramView` (`/nap/<dátum>` és
    **`/nap/allando`**: napfülek, helyszín-választó + kereső a lapon belül, élő MOST ZAJLIK / HAMAROSAN jelzés, óra) és a `ProgramDetailView`
    (morzsa, kategória-pirulák, MIKOR/HOL dobozok, extra mezők, megosztás). **Mérve a 2026-os élő lap mellett (1280 px):** a helyszínlap
    szakaszai és a kártya minden belső mérete pixelre egyezik; a napi nézet első sora pixelre. A korábbi, a programkereső kártyáiból
    értelmezve épített napi nézet és programlap megszűnt (a `programok.css` törölve).
  - **„Fotó nélküli kártyákon” — most VAN eleme:** a kártya sarok-firkája (`.pc-doodle`); mérve: 19 fotó nélküli kártyán 19 látszik, a
    kapcsoló kikapcsolt állapotában 0.
  - **`/terkep` és térképszámok — TULAJDONOSI DÖNTÉS (2026-11-04), tudatos eltérés:** a 2026-os `/terkep` OpenStreetMap-térkép volt GPS-es
    jelölőkkel — GPS egyiknek sem volt, élesben 0 jelölővel állt. A rendszer valódi mechanizmusa a rajzolt Vár/Város térkép (a
    térkép-szerkesztőben kézzel húzott pozíciók, a távoliak a SZÉLÉN irányjelvénnyel) — a `/terkep` ezt mutatja, a programkereső 2026-os
    térkép-jelölésével, „lap” módban: a jelölő, a pakli terme, a jelmagyarázat sora és a szélső jelvény a **helyszín lapjára** visz. A
    helyszínlapon **térképszám + „Mutasd a térképen”** (a `/terkep?helyszin=` a jelölőt kiemeli; a saját pozíció nélküli terem a szülőjéét
    kapja). A rajzok `public/terkeprajz/` alatt (a `public/terkep` mappa elnyelte volna a `/terkep` címet — mérve: 301). A programkeresőben
    a „szűrj, aztán kattints” változatlanul megy (mérve: 31 → 14, egy pótolt helyszínnel).
  - **A fejléc keresője** (Arculat → Fejléc → „Kereső a fejlécben”): a 2026-os jelöléssel, űrlapként (JS nélkül is) a `/programok?q=` címre.
  - **Románul** és **375 px-en** végignézve (10 lap): vízszintes görgetés nincs; a román lapokon a feliratok a 2026-os román szövegek.
  - **10 új teszt** (`HelyszinKategoriaTest`), **191 teszt zöld**. Két régi teszt a régi napi nézet / programlap formáit várta („23.00”,
    „(éjjel)”, szűrt kereső-link) — a 2026-os jelöléshez igazítva; a programkereső saját elvárásai változatlanok.
  - Döntések (egy-egy sor): a 2026-os `--grp-*` csoportszínek helyén a velük betűre egyező firka-szín · a kedvenc-szív és a „Naptárba” a
    saját körükig tiltott (mint a keresőben) · a `/terkep` bevezetőjéből kimaradt a „koordináták közelítők” mondat (nincs koordináta) ·
    a 2027-es keret címsor-/bekezdés-margó nullázását a Base-lapokon visszaállítottam a böngésző alapjára (a 2026-os lapokon az élt — mérve).

- **FIRKA-HIBA, javítva (2026-11-04, `5a6345f5`) — a tulajdonos jelezte: a kezdőlapon egy sodródó firka sem látszott.**
  **Ok:** a szórt firkák a közös motívumkészletre hivatkoznak (`<use href="#fgm-N">`), a keret viszont a motívum SAJÁT viewBox-át
  kapta → a `<use>` a rajzot a keret 0,0 pontjára teszi, a látható részen kívülre. Az elemek ott voltak, ezért a PROMPT-13 és -14
  mérése (elemszám) zöld volt — **a programkereső firkái is a PROMPT-13 óta láthatatlanok voltak.** Javítva mindkét építőben
  (`mozgas.js`, `kezdolap.js`), teszt őrzi. ⚠ **Tanulság: dekort KIRAJZOLÁS szerint kell mérni** (a rajz a keretén belül van-e —
  `getBBox` a viewBox-hoz), nem elemszám szerint.
  **Firka-leltár, oldalanként (1600 px, kirajzolódó firka / összes):**

  | Lap | 2026 (forgatag.ro) | 2027 a javítás előtt | 2027 a javítás után (helyben) |
  |---|---|---|---|
  | Kezdőlap (HU és RO) | 92 (nyitókép 10, lap 52, média 16, szervezők 14) | 0 / 92 | **92 / 92** |
  | Programkereső | 52 (a lap hosszától függ) | 0 | **mind** (helyben 32/32 — rövidebb lista) |
  | Fesztiválrend | 14 (nyitókép) | — a lap még nincs (8. pont) | **14 / 14** (PROMPT-17) |
  | Kézművesek | 37 | — a lap még nincs (8. pont) | **34 / 34** (PROMPT-17; a lap hosszától függ) |
  | Videó archívum | **16** (nyitókép, „szél” — a korábbi leltár itt tévesen 0-t írt) | — a lap még nincs | **16 / 16** (PROMPT-17) |
  | Kedvencek | 20 | — a funkció nyitott kérdés | — |
  | Rólunk, hírlista, hír, napi nézet, program lapja, helyszín-, kategória-, galéria-, támogatók-, térkép-lap, 404 | 0 | 0 | 0 (egyezik) |
  | Hamarosan (2027-es lap) | — | 2 (beágyazott, mindig látszott) | 2 |

  **A téma firka-kapcsolói a kezdőlapon (helyben, a téma által generált CSS-sel mérve):** sűrűség 92 → 51 („ritka”) → 0 („nincs”) ·
  „nyitókép szélein” ki: 92 → 82 · átlátszóság 0,67 → 0,19 · lebegés ki: áll · a 8 szín mind használt a motívumokban, és az átállítás
  a firkán látszik · a motívum-helyek (2026: 6, most 12) a közös készletet töltik. **„Fotó nélküli kártyákon”:** a 2026-os élő oldalon
  SEHOL nincs ilyen elem (minden kártyának volt képe vagy nem ProgramCard volt) — 2027-ben a 12/b kör ProgramCard-jával jön; addig
  a kapcsoló súgója kiírja, hogy nincs eleme (nem néma).
  **Élesben mérve a telepítés után (2026-11-04, proba.forgatag.ro, 1600 px, kirajzolás szerint):** kezdőlap HU és RO 92/92 (nyitókép
  10, lap 52, média 16, szervezők 14; „fesztivál előtt” állapotban +12 a Kiemelt sávban) · programkereső 23/23 (a mai lista hosszán) ·
  Hamarosan 2/2 · beküldő űrlap 2/2 (beágyazott SVG) · Rólunk, hírlista, hír, napi nézet, program lapja 0 — mint 2026-ban.

- **A maradék publikus lapok — 1. rész: fesztiválrend + kézművesek** (`PROMPT-17`, 2026-11-14) — **kész, ÉLESBEN telepítve és mérve
  (2026-11-14, `d6c9ee29`)** („MOST folyik”, 9.). **A kör kettévágva** (a tulajdonos javaslatára): ez a szöveges rész; a **2. rész a média-nehéz
  lapok** (galéria, videó-archívum, támogatók — „Ami ezután jön”, 6.). **A „Csapat” lap KIMARAD — tulajdonosi döntés (2026-11-14):** a
  2026-os élő oldalon nem volt ilyen lap, a menüben sincs, és a csapat-sáv 2026-07-27-én a kezdőlapról is kikerült (forgatag-os `1d2ffde`)
  — nincs mit átemelni. *(A csapat adatai — 56 fő — végül NEM jöttek be: lap nélkül holt adat lenne. Ha a tulajdonos lapot kér, azzal
  együtt jönnek, a képeik a médiatárban vannak — `Gyujtemeny::Csapat`.)*
  - **BETŰRE a 2026-osból (2/e):** a jelölés a 2026-os `fesztivalrend/body.html`, illetve `CraftspeopleView.astro` (osztályok, sorrend),
    a stílus a 2026-os stíluslap másolata (`fesztivalrend.css`, `kezmuvesek.css` — a gépies eltérések a fejükben, „2027:”-tel), a
    szkript az átirat (`fesztivalrend.js`, `kezmuvesek.js`). A 2027-es keret miatti kiegészítés külön (`fesztivalrend-szerver.css`: a
    böngésző címsor-/bekezdés-margójának visszaállítása, mint a Base-lapoknál). A kézművesek felugró ablakának görgetője a 2026-os közös
    `lib/rail` függőleges változata — most közös modul: `partials/vrail` + `vrail.css` + `vrail.js` (a 2. rész is használhatja).
    **Mérve a 2026-os élő lap mellett (beépített böngésző, mindkét lap ugyanabban):** a fesztiválrend szakaszai 1024 px-en
    681 / 856 / 1028 / 571 — pixelre; mind a 8 lenyíló NYITVA is pixelre (767 … 300); 375 px-en is (787 / 1880 / 10276 / 661). A
    kézművesek: a sáv, a nyitókép, a kártya és a képe 1024 és 375 px-en pixelre, magyarul és románul; ugyanaz a 67 név ugyanabban a
    sorrendben; lapozó (12 gomb, „19–36.”), felugró ablak (kép, cím, bekezdések, Esc, fókusz). Vízszintes görgetés nincs.
  - **A BEÉGETETT SZÖVEGEK (9. adósság) — mindkét lapon törlesztve:** a fesztiválrend **103 kulcs** (2026-ban 25 volt szerkeszthető, a
    többi beégetett magyar; a román lapon egy egyszer lefordított külön törzs — a román alapszöveg most onnan, kulcsonként); a kézművesek
    **8 kulcs** (2026: 5 — most a „Bővebben”, a számláló és az üres állapot is). Admin: Tartalom → Aloldalak → a lap → Szerkeszthető
    szövegek, emberi feliratokkal. **A próba teszt (`MaradekLapokTest`): az admin felől MINDEN szöveg kicserélve (a kézműves nevét és
    bemutatkozását is), magyarul és románul → a lapon nem marad változatlan szó.** Ellenpróbával: egy beírt szóra elbukik. A csak
    képernyőolvasónak szóló feliratok (lapozó, bezárás, görgető) a `lang/*/publikus.php` `cv_*` kulcsai — mint minden eddigi lapon.
  - **A kézművesek az adminban: Tartalom → Kézművesek** (tulajdonosi döntés, 2026-11-14 — nem új főmenüpont): név, kép (médiatár,
    „Kézművesek” gyűjtemény), bemutatkozás HU/RO, látható-kapcsoló, sorrend húzással. Új tábla: `craftspeople`. A használt kép nem
    törölhető (`Media::usages`). Jog: mint a támogatóknál (kategóriagazda 403, teszt).
  - **A 2026-os kézművesek behozva — számra: 67/67, mind képpel, mind román bemutatkozással; a 67 kép a médiatárból (`legacy_id`), NEM
    újrafeltöltve.** Csomag: `database/import/kezmuvesek-2026.json` (`forgatag:kezmuves-csomag`, fejlesztőgépen); a telepítés
    `forgatag:kezmuves-import` lépése tölti be — csak üres táblába, hiányzó képnél semmit nem ír.
  - **A dekor-réteg hat:** a fesztiválrend nyitóképén 14 lengő firka (2026: 14), a kézműveseken 34 szórt (2026: 34) — **kirajzolás szerint
    mérve** (a rajz a keretén belül: 14/14, 34/34). A rétegek `[data-decor]` jelölést kaptak, a motívum a téma 12 motívumából (`<use>`).
    A záró szekció „tűzijátéka” is a téma motívumaiból. „Kevesebb mozgás”: nincs firka, nincs tűzijáték.
  - **200 teszt zöld** (új: `MaradekLapokTest`, 8). ⚠ A teljes tesztsor a konténer alap 128 MB-os PHP-memóriáján a végén elfogy —
    `php -d memory_limit=512M vendor/bin/phpunit`.
  - Döntések (egy-egy sor): a fesztiválrend két dátum-kulcsa a 2027-es dátum (CLAUDE.md 1.: aug. 25–29.; 2026-ban „2026. augusztus 26–30.”
    — a román lapon is magyar formában állt), a román alakja „25–29 august 2027” · a fesztivál sorszáma („a 13. Vásárhelyi Forgatagon!”) a
    2026-os szöveg — nem találom ki (Nyitott kérdések) · a kézművesek román bevezetőjében a hibás „al Vâltoarei” → „al Vâltorii” (CLAUDE.md
    1.: a „Vâltoarei” mindig hibás) · a naptej-ikon „SPF” felirata a rajz része, nem szöveg · a firkák csak szélesség-változásra szóródnak
    újra (mint a mozgas.js és a kezdőlap; 2026-ban telefonon a címsor mozgására is ugráltak).

- **A maradék publikus lapok — 2. rész: galéria, videó-archívum, támogatók** (`PROMPT-17`, 2026-11-15) — **kész, ÉLESBEN telepítve
  és mérve (`e5f8ff7b`)** („MOST folyik”, 10.). **Ezzel a menü egyetlen pontja sem ad 404-et** (élesben mérve, HU és RO: a főoldal minden
  belső linkje 200, a `/programok/` 301 a perjel nélküli címre).
  - **BETŰRE a 2026-osból (2/e):** a jelölés a 2026-os `GalleryView`, `VideoArchiveView`, `SponsorsView` (osztályok, sorrend); a stílus a
    2026-os stíluslap másolata (`galeria-lap.css` = a GalleryView stílusblokkja, `videoarchivum.css`, `tamogatok-lap.css` — gépies eltérések
    a fejükben), a 2026-os betöltési sorrendben a közös modulokkal (galéria → `galeria.css`; videó → `rail.css` + `video.css`; támogatók →
    `tamogatok.css` előtte). A keret miatti kiegészítés külön: `videoarchivum-szerver.css` (a böngésző címsor-margója, mint a fesztiválrendnél).
    **Mérve a 2026-os élő lap mellett (beépített böngésző):** galéria 1024 px-en a fejléc, a rács első sora és az év-kártya pixelre (310×387);
    videó-archívum szakaszai 353 / 570 / 793 / 570, a fekvő kártya 308×239, az álló 227×464 — pixelre; támogatók 332 / 4690 / 242, mind a
    10 szint a nevével, csempeméretével (is-xl 420×200 … is-sm 200×120), darabszámával és magasságával pixelre. **375 px-en és románul**
    mindhárom lap betűre (videó románul csak az aftermovie-sáv — mint 2026-ban), vízszintes görgetés nincs.
  - **AMIT ÖRÖKÖLTEM, BEKÖTVE (nincs párhuzamos):** a galéria-ablak (`partials/galeria-ablak` + `galeria.js`, a képlista a közös
    `/galeria-adat.json`), az aftermovie-lejátszó (`video.js`), a csúszka (`partials/rail` + `rail.js`), a támogatói csempe (`tamogatok.css`).
    A kezdőlappal közös darabok most EGY helyen: `MediaLapok::videoKartya`, `MediaLapok::tamogatoCsempe`, `partials/video-ablak`,
    `Kezdolap::FENYEK` (a kártyák fény-palettája) — a kezdőlap ugyanezt használja (a kimenete betűre változatlan: az élő próbaoldallal
    összevetve a 8 videókártya, a 110 csempe és a lejátszó azonos — EGY szándékos kivétellel, ami utána jött: a 109 MP-es logó csempéje, lent). A képek a médiatárból (`legacy_id`), nem újrafeltöltve.
  - **A BEÉGETETT SZÖVEGEK — mindhárom lapon törlesztve:** galéria 3 kulcs (+ az üres állapot), videó-archívum 8 (a 2026-ban beírt román
    értékek megmaradtak), támogatók 6 (+ a 2026-ban beégetett „Köszönjük!”). A `MaradekLapokTest` „minden szöveg kicserélve” próbája most
    mind az öt lapra fut — a galéria, a videók és a támogatók adatával együtt; ellenpróbával (egy beírt szóra elbukik).
  - **A videó-archívum három sávja:** 2026-ban a kód a csoport slugja szerint rajzolt három rögzített sávot; most a lap **videós blokkjainak
    sorrendje** adja (1. = Aftermovie-k, világos · 2. = Vásárhely színe-java, álló · 3. = Mesterségem, narancs), a 2026-os horgonyokkal (a
    kezdőlap gombjai ezekre visznek) — `MediaLapok::SAVOK`. Ha valaki negyedik videós blokkot vesz fel, az nem látszik (2026-ban sem volt
    negyedik sáv) — **erről a nyitólap piros figyelmeztetést ad** (néma hiba tilos).
  - **A támogatók:** a legutóbbi év (ma a 2026-os 112) az adott év szintjei szerint; üres szint nem látszik (ma: „A Forgatag szállítója”);
    a csempeméret a szint helyéből (mint 2026-ban).
  - **⚠ A Balázs Ferenc Önképző Kör logója még mindig a 109 megapixeles (14765×7384) — JELZEM (a feladat kérte).** Méretváltozat nem
    készül belőle, ezért a lapon üres doboz lett volna. **Most a csempén — a kezdőlapi sávban is — a támogató NEVE látszik helyette**
    (`Media::tulNagy()`, a médiatár-behozatal 40 MP-es határával). **Élesben (proba) a logót már kicserélték** — ott a logó látszik
    (400×200, 0,44 s); a helyi adatbázisban még a régi van. A tartalék a jövőbeli óriásképekre marad.
  - **Firka-leltár javítva:** a 2026-os videó-archívum nyitóképén VANNAK „szélben” sodródó firkák (asztalon 16, mobilon 8 — a korábbi
    leltár 0-t írt, mert csak a `.decor` osztályt számolta). Átemelve a téma motívumaiból: 16/16 kirajzolva, `[data-decor="hero"]`.
    A galéria és a támogatók lapján 2026-ban sem volt firka.
  - **203 teszt zöld** (a `MaradekLapokTest` 11 teszt).
  - Döntések (egy-egy sor): a galéria képlistája külön kérésben jön (mint a kezdőlapon; 2026: a lapba ágyazva) · a videó-archívum román
    címe „Arhivă”+„video” szóköz nélkül áll — a 2026-ban beírt adat, 2026-ban is így látszott · a galéria román bevezetőjében a hibás
    „Vâltoarei” → „Vâltorii” · a kártya-fény `--grp-szinhaz` → `--doodle-purple` (betűre egyező, mint az alap.css-ben).

- **Térkép-szerkesztő** (`PROMPT-18`, 2026-11-16) — **kész HELYBEN**; élesítés: lásd „Ami MOST folyik”. `/admin/terkep-szerkeszto`,
  menü: Tartalom → Térkép-szerkesztő (tulajdonosi döntés 2026-11-16). Részletek: `CLAUDE.md` 8. pont „Térkép-szerkesztő”.
  - A 2026-os szerkesztő felülete, a Directus-belépés nélkül (a mentés a panel munkamenetén megy — 401-es hiba nem lehet).
  - **Húzás egérrel és ujjal** (Pointer Events, bővítmény nélkül, `getScreenCTM().inverse()`); a térkép körüli **szegélyre** ejtve a
    helyszín a **térkép szélére** kerül, irányjelvénnyel; telefonon **koppintás a helyszínre, aztán a térképre**.
  - **Helyben mérve (böngészőből, valódi egérhúzással):** egy helyszín a térképre, egy a szegélyre (jobbra → „jobbra (kelet)” jelvény) →
    Mentés → mindkettő megjelent a `/terkep`-en, a programkeresőben és a helyszínlapon (45-ös szám); visszavéve. Érintés (szimulált
    érintés-eseményekkel, 375 px): függőleges ujjmozdulat a listát görgeti, koppintás + koppintás elhelyez, jelölő ujjal a szegélyre → szélső jelvény.
    **Valódi tableten/telefonon még nem próbáltuk.**
  - **Kultúrpalota, nagyterem — elhelyezve HELYBEN** (a Kultúrpalota épületén, a kisterem túloldalán, 32,85% / 66,73%, **30-as szám** — a
    számsorban üresen állt). Élesben ugyanígy el kell helyezni (a helyi adatbázis nem megy ki).
  - **Vár, Várkert — a tulajdonos húzza be** (a pontos helyét nem lehet a rajzból kiolvasni): Tartalom → Térkép-szerkesztő → Vártérkép →
    a listában a piros pöttyös „Vár, Várkert” → húzd a helyére (vagy koppints rá, aztán a térképen a helyére) → **Mentés**.
  - Új mező: `venues.map_set_at` (a szerkesztőben beállított helyszínt a telepítés 2026-os térkép-behozatala nem írja felül). **211 teszt zöld.**
  - Döntések (egy-egy sor): a helyszínt koordinátor és szuperadmin szerkesztheti (mint a közös tartalmat) · a mentés minden-vagy-semmi ·
    a 2026-os helyi vázlat (böngészőben őrzött mentetlen munka) megmaradt (lejárt munkamenet ellen) · a szélső jelvény a szerkesztőben az
    admin színeivel, de a publikus elhelyezővel.
- **A fordítási lánc, HU → RO (PROMPT-19)** — **kész helyben (2026-11-18).** Részletek: CLAUDE.md 8. pont, „Fordítási lánc”.
  - **Gépi fordítás CSAK a programok címére, leírására, jegyinfójára** (a vázlatra is), a program mentése után magától (a válasz
    elküldése után — a beküldő nem vár), plusz pótló futás (`forgatag:forditas`, cron) és „Futtatás most” gomb a Beállításokban.
    Hírhez, laphoz, kategóriához, helyszínhez, menühöz nem nyúl (teszt is őrzi).
  - **Az emberi fordítás sérthetetlen** (reviewed / approved / outdated — teszttel). A magyar szöveg változásakor az átnézett sor
    AZONNAL „Elavult” lesz, a román oldalon a magyar látszik; a gépi sort a gép újrafordítja.
  - **Feltűnő ellensúly:** programlista „⚠ Elavult fordítás” fül piros számlálóval (a közzétett programoké is — a „Teendő” fülön azok
    nem lennének rajta), a program lapján pirosan keretezett sor, a nyitólapon piros figyelmeztetés.
  - **Költség:** a változatlant nem fordítja újra (lenyomat); üres futásnál API-hívás NINCS (teszt + a naplóban „API-hívás: 0”).
    **Helyben mérve a 2026-os tesztprogramokon (próbafutás):** 250 gépi sor naprakész, 233 emberi megőrizve, 2 elavult, **6 fordítandó**
    — tehát az első éles futás ~1 hívás (+1 ellenőrző), nem 180 programnyi.
  - **Védett nevek + ő/ű-ellenőrzés:** a lista a Beállítások új „Gépi fordítás” blokkjában (kezdőérték: `seed/do-not-translate.json`,
    69 név + 4 kötött alak). A futás után ellenőriz: kiesett védett név, hiányzó kötött alak, „Vâltoarei”, ő/ű a román szövegben (a védett
    nevek — Csicsergő, Szűcsök Bástyája — kivéve), tényadat-gyanú (második hívás, más modellel). **A jelzett gépi sor nem megy ki a román
    oldalra**, amíg ember át nem nézi.
  - **Fordító szerepkör:** csak a Programok lapot látja, a saját munkasorán nyit; a program lapján „Román fordítás” gomb: mezőnként a
    magyar, alatta a szerkeszthető román és az állapot. Helyben böngészőből végignézve (asztal + 375 px, vízszintes görgetés nincs).
  - **A Claude-hívás formája** álválasz-szerverrel ellenőrizve (helyes végpont, modell, JSON-séma, gyorsítótár, a válasz feldolgozása).
    **Valódi API-hívás még NEM volt** — ahhoz a kulcs kell (lent).
  - Új csomag a vendorban: `anthropic-ai/sdk` 1.10 (a hivatalos PHP SDK, + 4 függősége).
  - Döntések (egy-egy sor): modell `claude-sonnet-5`, ellenőrző `claude-opus-5` (a 2026-os páros; .env-ből felülírható) · a 2026-os
    tényadat-ellenőrző második hívás megmaradt · a jelzett gépi fordítás nem publikus · a modell és a kulcs .env-ben, a védett nevek a
    Beállításokban · a mentés utáni futás parancssorból NEM indul (a telepítés behozatala ne fordítson) · kézzel átírt szöveg nem lehet
    „gépi” · üresre törölt román = a sor törlődik · az admin programlapján a hibás `rgb(var(--danger-600))` színek javítva (eddig nem hatottak).

## Ami MOST folyik

0. **A fordítási lánc élesítése (PROMPT-19)** — **TULAJDONOSI TEENDŐ (3 lépés):**
   (1) **Az API-kulcs ELŐBB:** cPanel → Fájlkezelő → `public_html/forgatag2027/.env` → új sor: `ANTHROPIC_API_KEY=` + a kulcs (a
   console.anthropic.com-on: API Keys). ⚠ A beállításokat a telepítés gyorsítótárazza — ezért a kulcs a telepítés UTÁN beírva csak a
   következő telepítéskor hat (akkor elég a Deploy gombot még egyszer megnyomni).
   (2) **Telepítés** (Update from Remote → Deploy). A naplóban: `TÖRZSADATOK: beállítások / translation — betöltve (69 védett név, 4
   kötött alak)` és `FORDÍTÁS: próbafutás … fordítandó: N`.
   (3) **Cron** (cPanel → Cron Jobs, 15 percenként): `/opt/alt/php84/usr/bin/php /home/forgatag/public_html/forgatag2027/artisan forgatag:forditas >/dev/null 2>&1`
   **Élesben megnézendő (velem):** a Beállítások „Gépi fordítás” blokkja (be van-e kapcsolva) → „Futtatás most” (a 6 mező) → egy program
   lapján a román → egy átnézett sor magyarját átírva „Elavult” lesz, és a `/ro/program/…` lapon a magyar látszik → ismételt futás:
   „API-hívás: 0” → fordító fiókkal csak a Programok látszik.

0. **A térkép-szerkesztő élesítése (PROMPT-18)** — **telepítve (2026-11-16, `34eb079a`, a tulajdonos indította).** Belépés nélkül mérve:
   `/admin/terkep-szerkeszto` → 302 a belépésre (az útvonal él), a három új JS/CSS 200, a `/terkep` változatlan (41 jelölő + 3 szélső
   jelvény: Fociakadémia ↑, Víkendtelep ↑, Állatkert →), az új közös elhelyezőt (`terkep-szel.js`) a `/terkep` és a `/programok` is
   betölti; `/terkep` 0,47–0,50 s. **Belépéssel még NEM mérve** (a Chrome-bővítmény lekapcsolódott).
   **Élesben (2026-11-16, a tulajdonos húzta be a szerkesztővel):** a Kultúrpalota, nagyterem a `/terkep`-en saját jelölővel (33,37% /
   66,08%), a helyszínlapon **47-es** számmal (a szerkesztő a következő szabad számot adta; a 30-as üresen maradt — ha kell, a
   „Sorszám a jelölőn” mezőben átírható). **Hátra:** a Várkert behúzása (tulajdonos) · arculat-szerkesztő fiókkal a közvetlen cím → 403. Teendő: telepítés; élesben a
   Kultúrpalota nagyterem behúzása (30); arculat-szerkesztő fiókkal a `/admin/terkep-szerkeszto` begépelve → 403; a Várkert (tulajdonos).

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
   **+ a térkép (PROMPT-12, 2/a):** a naplóban `… helyszín térkép-adata kitöltve a 2026-os mentésből`; élesben megnézendő a térkép
   (szűrés, aztán kattintás a jelölőre), a „Mutasd a térképen”, a mobil „Térkép” gomb, és **a válaszidő mérése** (lista, szűrés).
   ⚠ **A válaszidőt élesben kell mérni:** helyben minden lap (a hírek is) ~6,5 mp — ez a Windows-os Docker mappamegosztása, nem a kód.
6. **A mozgás- és dekor-réteg** (PROMPT-13) — **élesben telepítve (2026-11-02, a telepítés a programkereső 11. és 12/a körét is kivitte).**
   **Élesben mérve (proba.forgatag.ro, böngészőből):** minden lap 200 (Rólunk HU/RO, hírek, programkereső, napi nézet); a téma 12 motívuma
   kint (a programkeresőn 12 szimbólum, 52 firka 267 kártya mellett); a firka-kapcsolók hatnak (52 → 26 → 0, lebegés ki, átlátszóság,
   „MA” pulzálás); a Rólunkon fonál, karika, felúszás, három színfolt; 375 px-en vízszintes görgetés nincs. Válaszidő egyenként: hírek 0,27–0,34 s,
   Rólunk 0,27 s, napi nézet 0,32 s, programkereső 0,42 s, „Minden nap” 0,55–0,6 s (a telepítés utáni első, párhuzamos kérések ~12 s-ig
   sorban álltak — egyszeri, utána nem ismétlődött).
   **A tulajdonos telefonos jelzése (2026-11-02), javítva:** (1) görgetéskor alul sötét sáv csúszott fel — a háttér
   `background-attachment:fixed` volt, amit a telefonos Chrome a címsáv nyitott magasságára fest; most külön rögzített réteg
   (`body::before`, a programkeresőn a `.fgl-atmo`) a címsáv nélküli teljes magasságra (`100lvh`); (2) a Rólunk karikája mobilon induláskor
   a kép mögött volt. Első javítás (a képek fölötti réteg) ROSSZ volt: a karika a kép közepére került, a kép fölé (tulajdonosi jelzés).
   Most: a karika a képek alatt marad (mint 2026-ban), de ha a fonal eleje a képernyő közepe fölött van (mobil), a karika a fonal
   ELEJÉRŐL indul (a cím és az első kép között, teljesen látszik), és onnan halad. Asztalon ez az eltolás 0 → a 2026-os viselkedés.
   **Mérve (helyben, végiggörgetve, 40 px-enként):** mobilon (375×812) induláskor látszik, a minták 2/3-ában látszik, 1/3-ában a kép
   mögött jár; asztalon (1366×768) 40% / 60% — mert a 2026-os fonal minden kép KÖZEPÉN megy át, és a képek alatt fut. Ha ez zavar, az
   már eltérés a 2026-ostól (kérdés a tulajdonosnak). A sötét sáv javítása a telefonon bevált (tulajdonosi visszajelzés).
   **Hátra (a felhasználóval), VALÓDI TELEFONON:** a fenti kettő újranézése a telepítés után, és: a programkereső görgetése „Minden nap” nézetben (a lista aljáig) sima-e; a Rólunk fonala és
   felúszása; egy hírkártya kiemelése; és a telefon „Kevesebb mozgás” / „Animációk eltávolítása” beállításával a Rólunk: semmi nem úszik, a
   programkeresőn nincs firka.
7. **A kezdőlap (PROMPT-14)** — **élesben telepítve (2026-11-04).** **Élesben mérve (proba.forgatag.ro, böngészőből):** `/` és `/ro` 200,
   hibás kérés 0; asztalon (1024 px) mind az öt szakasz magassága **pixelre egyezik** a 2026-os élő forgatag.ro-val (691 / 876 / 621 /
   1270 / 902); 375 px-en vízszintes görgetés nincs; románul a hírek nélkül. Galéria: év → 34 kép hasábokban → nagy kép → Esc; az
   aftermovie lejátszó nyílik és bezárul. **Válaszidő** (az első bájtig + letöltés): `/` 0,35–0,53 s, `/ro` 0,31–0,43 s, a lap 34 kB
   (tömörítve); a galéria-adat (12 év, 467 kép) 0,33 s, csak megnyitáskor. **A nyitókép első képe telefonon 67 kB** (a vágott 750×1400-as;
   asztalon 123 kB — 2026-ban a telefon is az 1920-as képet kapta).
   **Valódi telefonon, mobilneten mérve (a tulajdonos, 2026-11-04): a nyitókép fotója 1–2 mp alatt jelenik meg.**
   ~~**Hátra:** a Beállításokban az állapot-kapcsoló ki-be próbája~~ — **a tulajdonos élesben kipróbálta (2026-11-04): működik.**
8. **Helyszín- és kategórialapok, `/terkep`, napi nézet, program lapja (PROMPT-15)** — **élesben telepítve és megnézve (2026-11-04).**
   **Élesben mérve (proba.forgatag.ro, böngészőből):** minden új lap 200 (HU és RO), a régi `/terkep/…svg` 404 (a mappa megszűnt);
   **a 8 pótolt helyszín lapján ott vannak a programjaik** (17 + 19 + 1 + 4 + 1 + 1 + 1 + 16 = 60, mind fotó nélküli kártya sarok-firkával);
   `/terkep`: 41 jelölő + 3 szélső jelvény + 69 soros jelmagyarázat, a `?helyszin=bernady-haz` a Város fülre vált és kiemel, a szélső
   jelvényre (Fociakadémia) kattintva a helyszínlap nyílik (41-es térképszámmal); a programkeresőben „szűrj, aztán kattints” → 31 → 14;
   375 px-en 8 lap, vízszintes görgetés nincs; hibás kérés 0.
   **Válaszidő (háromszor mérve, letöltéssel):** helyszínlap (Bernády) 0,33–0,35 s · napi nézet 0,32–0,38 s · `/terkep` 0,39–0,43 s ·
   `/nap/allando` (70 kártya) 0,53–0,54 s · **a Vár lapja (190 kártya, a termeivel) 0,62–0,65 s**, 244 kB tömörítés előtt.
   **Utána — TULAJDONOSI SZABÁLY (2026-11-04): BÁRHOL listázunk programot, a programkereső sorai listázzák, ugyanazzal a kóddal**
   (a közbülső rácsos megoldást felülírja). A napi nézet, a kategória- és a helyszínlap a kereső sorait mutatja (jobb oldali hasáb
   nélkül), fent és lent hangsúlyos „Vissza a programkeresőhöz” gombbal, a lap szűrésére; a sor a kereső felugró ablakát nyitja; a
   program lapjának morzsája és kategóriái is a keresőbe visznek. A képhely mindig ott van: fotó nélkül a sarok-firka (a keresőben is).
   Helyben ellenőrizve (asztal + 375 px + román; vízszintes görgetés nincs), 192 teszt zöld. **Élesben telepítve és mérve (2026-11-04):**
   minden lap 200 (HU és RO), a sorok a kereső sorai, a gomb a lap szűrésére visz, listáról kategória-/helyszín-/programlapra 0 link,
   a fotó nélküli sorokban ott a firka; 375 px-en vízszintes görgetés nincs. **Válaszidő (háromszor):** Vár 309 sor 0,54–0,56 s ·
   Bernády 0,32–0,33 s · napi nézet 0,40–0,44 s · `/nap/allando` 0,44–0,51 s · a legnagyobb kategória (Csicsergő, 105 sor) 0,39–0,45 s.
   (A keresőben a sor `href`-je JS nélkül továbbra is a program lapja — kattintásra a felugró ablak nyílik, mint eddig.)
   **Hiba, javítva és élesben ellenőrizve (2026-11-04, a tulajdonos jelezte):** 1041 px fölött a kereső asztali szabálya a listalapot is
   kéthasábos rácsba tette (jobbra 440 px-es üres hasáb). Most `.blk.pk-napi > .wrap{display:block}` (`programlista.css`); 1440 és 1920
   px-en mérve mindhárom laptípus egy hasábos. ⚠ Tanulság: a listalapokat SZÉLES asztalon is nézd meg, ne csak keskeny ablakban.

9. **A maradék lapok 1. része (PROMPT-17: fesztiválrend + kézművesek)** — **élesben telepítve (2026-11-14, `d6c9ee29`) és megnézve.**
   **Élesben mérve (proba.forgatag.ro):** `/fesztivalrend`, `/kezmuvesek` HU és RO 200; 8 lenyíló, 67 kézműves kártya, „1–18. a 67
   kézművesből” / „1–18. din 67 de meșteșugari”, a 2027-es dátum mindkét nyelven; minden CSS/JS és a kézművesek képei (webp, 560×420 és
   900×506) 200. **375 px-en:** vízszintes görgetés nincs; a fesztiválrend nyitott lenyílókkal betűre a 2026-os számokkal (787 / 1880 /
   10276 / 661; lenyílók 1027 … 523), a lenyíló kattintásra nyílik, a nyitókép 10/10 firkája kirajzolódik; a kézműveseken 52/52 firka
   kirajzolva, a felugró ablak a nagy képpel és a közös görgetővel nyílik. **Válaszidő (háromszor, tömörítve):** fesztiválrend 0,34–0,39 s
   (31 kB), kézművesek 0,35–0,40 s (49 kB), román kézművesek 0,36–0,40 s. **A menüből már CSAK a 2. rész lapjai 404-esek** (Galéria,
   Támogatók, Videó archívum — a két videós horgony is).
   **Hátra (a felhasználóval, az ő belépésével):** az adminban Tartalom → Kézművesek (lista, egy kézműves szerkesztése), és egy szöveg
   átírása Aloldalak → Fesztiválrend → Szerkeszthető szövegek alatt — megjelenik-e a lapon.
10. **A maradék lapok 2. része (PROMPT-17: galéria, videó-archívum, támogatók)** — **élesben telepítve (2026-11-15, `e5f8ff7b`) és megnézve.**
   **Élesben mérve (proba.forgatag.ro):** mindhárom lap HU és RO 200; galéria 13 év (élesben megvan a 2026-os is, 107 képpel — a 2026-os
   élő oldal ebből csak 100-at mutat: a már ismert 2026-os 100-as korlát), 26 videó (románul 8), 112 támogató 10 szintben; a menüből
   egyetlen hibalap sincs. **375 px-en:** vízszintes görgetés nincs; videó 307 / 540 / 637 / 570 (románul 307 / 540), támogatók 358 / 7742 /
   251 (románul 384 / 7762 / 251) — betűre a 2026-os számok; a videó nyitóképének 8/8 firkája kirajzolva; a galéria végig: 2026 → 107 kép →
   nagy kép („1 / 107”, 1600 px) → Esc; mind a 112 logó 200 és kép. **Válaszidő (háromszor, tömörítve):** galéria 0,40–0,43 s (5 kB) ·
   a képlista (`/galeria-adat.json`, csak az első megnyitáskor) 0,64–1,1 s (169 kB) · videó-archívum 0,58–0,64 s (23 kB; románul 0,36–0,41)
   · támogatók 0,36–0,41 s (12 kB) · kezdőlap 0,47–0,58 s.
   ⚠ **Mérési tanulság:** rejtett böngészőpanelben (és rejtett mérőablakban) a lusta képek NEM töltődnek — „nem töltődött be” mérést ott
   közvetlen kéréssel ellenőrizz (curl / `new Image()`), mielőtt hibának hiszed.

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
3. **A programkereső** — a legnagyobb és legösszetettebb darab. **1. rész kész (2026-10-30), a térkép (2/a) kész (2026-11-01), fent.**
   **A 12/b kör (PROMPT-15) is kész (2026-11-04)** — helyszín- és kategórialapok, `/terkep`, térképszámok, fejléc-kereső, a napi nézet
   és a program lapja a 2026-os jelöléssel; részletek fent („Ami KÉSZ”). A programkereső-csomag ezzel lezárult; a kedvencek és a naptár
   gombjai a saját körükben kapcsolnak be.

4. ~~**A mozgás- és dekor-réteg**~~ — **kész helyben (2026-11-02, PROMPT-13)**, lásd fent. ÖNÁLLÓ LÉPÉS volt, nem a kezdőlap része.
   *Indok: ez nem laponkénti munka, hanem egy réteg, amit az Arculat „Mozgás" és „Firkák"
   beállításai vezérelnek — ahogy 2026-ban is. Ha laponként készülne, elcsúsznának egymástól, és a
   `/tema` kapcsolói nem hatnának rájuk egységesen.*
   **Visszamenőleg hozzányúl minden addig elkészült laphoz** (Rólunk, hírlista, hír, programkereső),
   ezért nem szabad a kezdőlap körébe ágyazni — ott váratlanul nagyra nőne és félkészen állna meg.

5. ~~**A kezdőlap**~~ — **kész helyben (2026-11-04, PROMPT-14)**, lásd fent. *(Az alábbi jegyzet teljesült.)* A legösszetettebb lap; a dekor-rétegre épül. **Örökölt:** a `mozgas.js` szórt rétege (`data-szoras="oldal"`) és a
   közös motívumkészlet (`Theme::motifSprite()`) kész; a nyitókép lengő firkái (`[data-decor="hero"]`) és a kezdőlap saját szórt
   rétegei a 2026-os `home-v7/script.js` szerint ide kerülnek. ⚠ 2026-ban a „Mozgás-mennyiség” a kezdőlapon nem hatott — itt kell hatnia.
6. ~~**A maradék publikus lapok**~~ — **kész helyben** (1. rész élesben 2026-11-14, 2. rész helyben 2026-11-15, fent) · a csapat kimarad
   (tulajdonosi döntés 2026-11-14 — nem volt 2026-os lapja). A beégetett szövegek (9. adósság) mind az öt lapon törlesztve.

7. **A `/szerkeszto`** — csak a lapok elkészülte után.
   ⚠ **Nyitott kérdés, mielőtt elkezdjük (2026-11-14):** kell-e egyáltalán? 2026-ban azért kellett, mert a
   Directus nem tudta a laptartalmat a helyén megmutatni. A 2027-es adminban a szerkeszthető szövegek
   **már most szerkeszthetők**, emberi feliratokkal, a lapon elfoglalt sorrendben, képválasztóval.
   Amit a `/szerkeszto` ezen felül adna, az a **helyben szerkesztés** (látod a lapot, belekattintasz) —
   kényelmesebb, de a 2026-os változat egy több mint ezer soros külön alkalmazás volt.
   **A tulajdonos dönti el, amikor a lapok megvannak, és látja, mennyire kényelmes az admin.**

8. ~~**A térkép-szerkesztő**~~ — **kész helyben (2026-11-16, PROMPT-18)**, fent; a Várkert behúzása a tulajdonosé.
   ⚠ **Első teendője (tulajdonosi rögzítés, 2026-11-04 — NEM a szervezők listája):** két helyszín valóban
   beállítatlan, mert 2026-ban sem volt pozíciója, pedig a város belsejében van: **Vár, Várkert** és
   **Kultúrpalota, nagyterem** — két húzás a szerkesztőben. (Az Állatkert, a vikendtelep és a Fociakadémia
   NEM hiányzik: a térkép szélén, irányjelvénnyel vannak.) A GPS nem kell: a rendszer a rajzolt térkép
   pozícióin működik.

9. ~~**A fordítási lánc**~~ — **kész helyben (2026-11-18, PROMPT-19)**, fent; az élesítés a tulajdonos 3 lépése után.
10. **Élesbe tétel** — **a legutolsó lépés**, lásd lentebb. Addig nem téma.

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
8. **A Curator három részletét átvettük** (a panel „Fájlok hozzáadása” művelete, a képválasztó panel-indítója, és
   2026-11-14 óta a panel NÉZETE is: `resources/views/mediatar/curator-panel.blade.php` — a feltöltés utáni számláló sor
   és a gombsorrend miatt; a befejező gomb „Beillesztés a galériába (N kép)”, elsődleges; a másik „Csak a médiatárba”) —
   a csomag frissítésekor össze kell vetni a sajátunkat az újjal (`App\Livewire\MediaPanel`, `App\Filament\Forms\MediaPicker`).
9. **A 2026-os aloldalak szövegének nagy része BEÉGETETT magyar** (tulajdonosi rögzítés, 2026-10-28 — MOST NEM javítjuk).
   A 2026-os Astro-sablonokban a szöveg zöme nem szerkeszthető kulcson volt; a fesztiválrend lapján például a szövegnek
   csak **18%-a** szerkeszthető. Átjött, ami szerkeszthető volt (85 kulcs, az aloldal lapján „Szerkeszthető szövegek”);
   **a többi a publikus sablonok körében pótlandó** *(2026-10-29: a Rólunk és a hírlista TÖRLESZTVE — `App\Support\LapSablon`; 2026-11-04:
   a kezdőlap; 2026-11-14: a fesztiválrend (103 kulcs) és a kézművesek (8); 2026-11-15: a galéria, a videó-archívum és a támogatók — a megépült publikus lapokon MIND törlesztve)* (forrás: `..\forgatag-os\frontend\src\rolunk\`, `fesztivalrend\`,
   `videoarchivum\`, `home-v7\` stb. — csak olvasásra), úgy, hogy minden szöveg kulcsra kerüljön, HU/RO.

## Amit a mentés leltára felszínre hozott

- **Kategóriák: 23, nem 24.** Öt megszűnt, négy új jött. *(2026-10-28: a kezdőlista már ez a 23.)*
- **Egyetlen helyszínnek sincs GPS-koordinátája** (46/46 üres). *(Tulajdonosi döntés, 2026-11-04: nem is kell — a rendszer a rajzolt
  térkép pozícióin működik; a GPS nem nyitott kérdés.)*
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
- ~~A 8 hiányzó helyszín~~ — **eldöntve (2026-11-04): 2027-ben is helyszínek, felvéve** (PROMPT-15).
- **A HELYSZÍNLISTA EGÉSZE — a szervezőknek át kell nézniük a tavaszi beküldés előtt.** Három eltérő szám van rá a projektben:
  **59** (a 2026-os CLAUDE.md), **52** (a 2026-os `seed/venues.csv` sorai), **46** (a 2026-os adatbázis) — `MENTES-LELTAR.md` 2. pont;
  a 2027-es adatbázisban most **69** helyszín van (a 2027-es kezdőlista 61 + a 8 pótolt). Nem találom ki, melyik a helyes lista.
  Ugyanide tartozik: **23 helyszínnek nincs román neve** — mind olyan, ami csak a 2027-es kezdőlistában van, 2026-ban nem
  (pl. Vár, Csicsergő · Vár, Szűcsök Bástyája · Somostető, szabadtéri amfiteátrum · Spectrum Színház); a román lapon a magyar látszik.
  Kézi fordítás kell (Helyszínek → román név) — nem fordítottam gépen (saját tartalom).
- ~~A helyszín- és kategórialap kártyáinak szélessége~~ — **eldöntve (2026-11-04): előbb rács, aztán SZABÁLY: bárhol listázunk
  programot, a programkereső sorai listázzák, ugyanaz a kód** („MINDEN a keresőre vezet vissza”). Tudatos eltérés a 2026-ostól
  (ott a napi nézet, a kategória- és a helyszínlap a `ProgramCard` kártyáit mutatta). Részletek: CLAUDE.md, Base-lapok.
- ~~A tesztadat törlése~~ — **eldöntve (2026-10-30): a 61 helyszín nélküli tesztprogram a képeivel együtt törölve** (lásd fent, Tesztadat).

- **ÚJ — a Rólunk román zárómondata hibás adat** (a 2026-os behozatalból): a „záró bekezdés” román szövege így végződik:
  „…ne unește.**összeköt minket.**” — magyar szó maradt benne (a heurisztika: a román szövegben nincs ő/ű, de itt ö van). Javítani az
  adminban kell (Aloldalak → Rólunk → Szerkeszthető szövegek → „Történet — záró bekezdés”). A külön „záró kiemelés” kulcs megszűnt:
  2026-ban sem látszott (a bekezdés felülírta), és a román mezőjében az egész bekezdés állt.
- **ÚJ (PROMPT-14) — a visszaszámláló román feliratai:** a „Még / nap / óra / perc” román változata 2026-08-26-án a kulcsokkal
  együtt törlődött (a „Ott találkozunk!” megmaradt: „Ne vedem acolo!”). Most a román lapon a magyar látszana — **a fesztivál előtti
  állapot bekapcsolása ELŐTT** be kell írni (Aloldalak → Kezdőlap → Visszaszámláló). Nem fordítottam magam (saját tartalom = kézi fordítás).
- **ÚJ (PROMPT-14) — a kezdőlap két „Videó archívum” gombja** (Mesterségem Vásárhelyi, Vásárhely színe-java) a `/videoarchivum` lapra
  visz, ami a 8. pont köréig még nem épült meg (ma 404 — mint a menü több pontja).
- **ÚJ (PROMPT-17) — a fesztiválrend záró ecsetírása:** „a **13.** Vásárhelyi Forgatagon!” (románul „la a 13-a ediție…”) — a 2026-os
  szöveg, a sorszámot nem találtam ki. Ha 2027-ben a 14., az adminban kell átírni (Aloldalak → Fesztiválrend → „Köszönjük — ecsetírás,
  3. sor”). A két dátum-kulcs már a 2027-es dátumot mutatja (aug. 25–29.).
- **ÚJ (PROMPT-17, 2. rész) — a támogatók bevezetőjében „tizenhárom éve” / „de treisprezece ani” áll** (a 2026-os szöveg). 2027-ben
  átírandó az adminban (Aloldalak → Támogatóink → Bevezető), mint a fesztiválrend „13.” sorszáma — nem írtam át magamtól.
- **ÚJ (PROMPT-17) — a kézművesek listája a 2026-os 67 kézműves** (mind látható). A 2026-os modellnek nem volt éve; ha a 2027-es vásáron
  mások lesznek, az adminban rejthetők/törölhetők (Tartalom → Kézművesek). Évhez kötni (mint a támogatókat) új mező lenne — szólj, ha kell.
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
