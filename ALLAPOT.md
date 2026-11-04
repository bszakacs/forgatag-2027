# HOL TARTUNK

> **Ezt olvasd el először, minden új beszélgetés elején.** Utána a `CLAUDE.md`-t.
> **Ezt a fájlt minden kör VÉGÉN frissíteni kell** — a 2026-os `HANDOFF.md` azért vált
> használhatatlanná, mert senki nem frissítette.

**Utolsó frissítés:** 2026-11-04 (PROMPT-15, helyszín- és kategórialapok, `/terkep`, napi nézet, program lapja — kész, ÉLESBEN telepítve és megmérve)

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
  | Fesztiválrend | 14 (nyitókép) | — a lap még nincs (8. pont) | — |
  | Kézművesek | 37 | — a lap még nincs (8. pont) | — |
  | Kedvencek | 20 | — a funkció nyitott kérdés | — |
  | Rólunk, hírlista, hír, napi nézet, program lapja, helyszín-, kategória-, galéria-, videóarchívum-, támogatók-, térkép-lap, 404 | 0 | 0 | 0 (egyezik) |
  | Hamarosan (2027-es lap) | — | 2 (beágyazott, mindig látszott) | 2 |

  **A téma firka-kapcsolói a kezdőlapon (helyben, a téma által generált CSS-sel mérve):** sűrűség 92 → 51 („ritka”) → 0 („nincs”) ·
  „nyitókép szélein” ki: 92 → 82 · átlátszóság 0,67 → 0,19 · lebegés ki: áll · a 8 szín mind használt a motívumokban, és az átállítás
  a firkán látszik · a motívum-helyek (2026: 6, most 12) a közös készletet töltik. **„Fotó nélküli kártyákon”:** a 2026-os élő oldalon
  SEHOL nincs ilyen elem (minden kártyának volt képe vagy nem ProgramCard volt) — 2027-ben a 12/b kör ProgramCard-jával jön; addig
  a kapcsoló súgója kiírja, hogy nincs eleme (nem néma).
  **Élesben mérve a telepítés után (2026-11-04, proba.forgatag.ro, 1600 px, kirajzolás szerint):** kezdőlap HU és RO 92/92 (nyitókép
  10, lap 52, média 16, szervezők 14; „fesztivál előtt” állapotban +12 a Kiemelt sávban) · programkereső 23/23 (a mai lista hosszán) ·
  Hamarosan 2/2 · beküldő űrlap 2/2 (beágyazott SVG) · Rólunk, hírlista, hír, napi nézet, program lapja 0 — mint 2026-ban.

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
   **Hátra (a felhasználóval):** a kártyák szélessége a helyszín-/kategórialapon (Nyitott kérdések, PROMPT-15).

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
6. **A két hátralévő egyedi szerkesztő** — `/szerkeszto`, térkép-szerkesztő (a `/tema` kész).
   ⚠ **A térkép-szerkesztő első teendője (tulajdonosi rögzítés, 2026-11-04 — NEM a szervezők listája):** két helyszín valóban
   beállítatlan, mert 2026-ban sem volt pozíciója, pedig a város belsejében van: **Vár, Várkert** és **Kultúrpalota, nagyterem** —
   két húzás a szerkesztőben. (Az Állatkert, a vikendtelep és a Fociakadémia NEM hiányzik: a térkép szélén, irányjelvénnyel vannak.)
   A GPS nem kell: a rendszer a rajzolt térkép pozícióin működik.
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
- **ÚJ (PROMPT-15) — a 2026-os helyszín- és kategórialap kártyái TELJES szélességűek** (1280 px-en egy kártya 1265 × 990 px, mert a
  2026-os Base a kártyákat rács nélkül tette a lapra). Betűre átvettem (2/e); ha rácsba rendezzük (mint a „Állandó” fülön), az eltérés
  a 2026-ostól — szólj, ha kéred.
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
