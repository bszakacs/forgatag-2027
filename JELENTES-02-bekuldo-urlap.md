# Jelentés — 02. A programbeküldő űrlap

> A `PROMPT-02-bekuldo-urlap.md` végrehajtásáról. Dátum: 2026-10-12.
> Állapot: **kész, élesben mérve** a `proba.forgatag.ro`-n. Utolsó commit: `152a2b46`.

---

## 1. Mi készült el

A programbeküldő űrlap Laravelben (`/bekuldes`), a 2026-os `submit-api` kiváltására:

- **Külön beküldői belépés** (`/bekuldes/belepes`), saját őrrel és jelszótárral (`bekuldo` őr, `program_managers` tábla).
- **Kategóriaválasztó:** csak a beküldő saját kategóriái látszanak. Ha egyetlen kategóriája van, egyből az űrlap jön.
- **Egy hosszú űrlap**, kategóriánként adaptív extra mezőkkel. A hibák egy listában jelennek meg, a lap tetején.
- **„Saját beküldéseim” lap:** a beküldő a programját a jóváhagyásig javíthatja.
- **Levelek:** beküldéskor és javításkor a koordinátor kap értesítést, jóváhagyáskor a beküldő.

A mezők, a feliratok, a validációs szabályok és a hibaüzenetek **szó szerint a 2026-os kódból** jöttek. A szerkezet újraépült:

| Mi | Hol |
|---|---|
| Az űrlap szerkezete (alapmezők, extra mezők kategóriánként, fotó- és szövegszabályok) — **egy forrás** az űrlapnak és a szervernek | `app/Support/SubmissionForm.php` |
| Validáció + mentés + időpont-összehangolás | `app/Services/ProgramSubmission.php` |
| Szövegtisztítás (emoji, szóköz, CSUPA NAGYBETŰ) | `app/Support/TextCleaner.php` — böngészős párja: `public/js/bekuldes.js` |
| Vezérlők, kapu | `app/Http/Controllers/Bekuldes/*`, `app/Http/Middleware/BekuldoBelepve.php` |
| Levelek | `app/Support/BekuldesLevelek.php` → `app/Support/Notifier.php` |
| Lapok | `resources/views/bekuldes/*` (Blade), `public/css/bekuldes.css` |
| Tesztek (26 db) | `tests/Feature/ProgramBekuldesTest.php` |

## 2. A hét javítás — mind elkészült

| # | Javítás | Megvalósítás |
|---|---|---|
| 2.1 | Csak a saját kategóriába | A szerver ellenőrzi (403) beküldésnél és javításnál is. Más programgazda programjára 404 jön. |
| 2.2 | Saját beküldéseim + javítás jóváhagyásig | `ProgramStatus::isSubmitterEditable()` (draft, pending_review). Jóváhagyás után 409 a válasz, és a javítás gombja eltűnik. |
| 2.3 | Minden a Beállítások lapról | Nyitva/zárva, zárt üzenet, határidő, értesítési cím, fesztiválnapok és záró óra. Nincs `.env` és nincs újraindítás. |
| 2.4 | A záró óra egy helyen | A szerver a kész határt és a kész hibaszöveget adja a böngészőnek (`SubmissionForm::dayCutTime/Message`). |
| 2.5 | Nincs néma képhiba | A fotó kerül tárolásra **először**: ha nem sikerül, a program nem jön létre, a beküldő hibaüzenetet lát, és a koordinátor levelet kap. Ugyanígy kezelt a PHP feltöltési hibája és a kéréskorlát túllépése (413) is. |
| 2.6 | Végpontvédelem | Beküldésre és javításra 30/óra/IP korlát. Belépésnél 5 hibás próba után 15 perc zár (IP + e-mail), mellette IP-szintű korlát. |
| 2.7 | A belépés elválik az admintól | Külön őr és tábla. A Filament kifejezetten `authGuard('web')`-et használ. Teszt bizonyítja, hogy a programgazda nem jut be az adminba. |

A 3. pont (beküldés után) is teljesült:
- a program draft állapotban, `source=public_form` jelöléssel jön létre;
- az időpont-sorok helyben frissülnek, az azonosítójuk megmarad (a kivett nap sora törlődik, az új nap sora beszúródik);
- **fordítást nem hív a kód**; a fordítandó mezők köre az `App\Enums\TranslatableField`.

## 3. Ellenőrzés

**Helyben (Docker, 375 px):** végigpróbálva három kategóriával:
- Borkert: extra mezők nélkül;
- Koncertforgatag: kötelező extra mezővel;
- TékaForgatag: a könyvbemutató feltételes mezőivel.

Kipróbálva az is, hogy:
- a 23:00 → 00:30 átmegy, a 23:00 → 04:00 elbukik, a böngészőben és a szerveren is;
- más kategóriájába nem lehet beküldeni;
- zárt állapotban a végpont is visszautasít;
- a fotóhiba látszik a felületen, és a koordinátor levelet kap róla.

**Élesben (proba.forgatag.ro):**

| Mérés | Eredmény |
|---|---|
| Levél a tárhely levelezőjén | ✅ megérkezett (SMTP, `noreply@forgatag.ro`, `mail.forgatag.ro:465`, smtps) |
| Nagy fotó | ✅ 9,99 MB, 2100×1450, 2,5 mp; a szerverről visszaolvasva ép (PHP: 12M / 16M) |
| Gyakorisági korlát | ✅ a belépés a 6. próbálkozásra zárol |
| Jóváhagyási levél | ⏳ **nem mérve**: nincs még program-lista az adminban (4. fázis) |

## 4. Menet közben talált és javított hibák

1. **A mentés utáni levélhiba sikertelen beküldésnek látszott.** A program elmentődött, a beküldő mégis hibát látott, így duplán küldte volna be. Helyben mérve, javítva: a `Notifier` soha nem dob kivételt. Teszt is van rá.
2. **A címke-mezőbe Enter nélkül beírt cím mentéskor némán elveszett** (Filament `TagsInput`, élesben derült ki). Most sima szövegmező (soronként vagy vesszővel), ellenőrzött címekkel.
3. **Az ideiglenes telepítési parancs megakasztotta a telepítést**, ha a `.env` kitöltetlen volt: az útvonal-gyorsítótár nem frissült, a `/bekuldes` 404 maradt. Most hibánál is 0-val lép ki.
4. **Túl sok címmező volt** (feladó cím, feladó neve, kapcsolattartó cím, koordinátori cím). A tulajdonos kérésére összevonva: **egy funkció = egy cím**. A feladó a szerver SMTP-fiókja (`.env`). A beküldő válasza (Reply-To) a koordinátorhoz megy, a koordinátorinak szóló levélé pedig a beküldőhöz. A tárolt értékek is törölve, migrációval.
5. **A beküldési határidő mezőben óra is volt.** Most csak dátum: azon a napon még nyitva, éjfélkor zár.

## 5. Döntések (konzervatív irányba)

- Zárt állapotban a javítás is zárva van; a „Saját beküldéseim” lista megnézhető.
- A 2026-os logót nem hoztam át, mert „13.” szerepel rajta. A helyén szöveges márkanév áll.
- A fotó **nem nyilvános** tárba kerül (`storage/app/private/bekuldesek/`, `program_items.photo_path`), amíg a médiatárról nincs döntés.
- Az űrlap fetch + FormData-val küld (a 2026-os viselkedés szerint), így hiba esetén a kiválasztott fotó nem vész el.
- Csak sötét téma van (lásd az adósságoknál).

## 6. Adósságok — tulajdonosi rögzítés, később szabályt sértenének

1. **`public/css/tema.css` IDEIGLENES.** Amikor a /tema elkészül, ennek nyomtalanul el kell tűnnie: minden szín a publikált témából jön (token-szabály).
2. **Hiányzik a világos/sötét váltó** (2026-ban volt). Ez visszalépés; a /tema fázisában pótolni kell.
3. **IDEIGLENES `forgatag:programgazda`** (`routes/console.php` + `.cpanel.yml` + a szerveri `.env` `PROGRAMGAZDA_*` sorai). A Programgazdák lap elkészültével mind a négy helyről törlendő.

## 7. Tanulságok a tárhelyről (a CLAUDE.md-ben is)

- A szerveri `.env`-ben a `MAIL_MAILER` **csak egyszer** szerepeljen: a Laravel az **első** előfordulást veszi figyelembe.
- PHP-korlát a cPanelben („Select PHP Version → Options”): `upload_max_filesize=12M`, `post_max_size=16M`. Ha kevesebb, a Beállítások lap pirosan figyelmeztet.
- Ha a `.cpanel.yml` bármely lépése hibával lép ki, a telepítés megáll a gyorsítótár-frissítés előtt. Ilyenkor az új kód részben él, az útvonalak a régiek.
- A tesztekhez dev-csomagok kellenek. A `vendor/` ezek nélkül van a tárolóban, ezért a tesztek a konténerben egy másolaton futnak (`/tmp/proj`).

## 8. Következő lépés

**„Felhasználók” menücsoport** két lappal: admin-felhasználók és programgazdák (név, e-mail, jelszó, aktív, kategóriák). Tulajdonosi döntés: rokon dolgok menücsoportba kerülnek, nem külön menüpontokba. Ezzel az ideiglenes `forgatag:programgazda` kivezethető.

Utána, ütemterv szerint: a többi beküldő űrlap (szabadulószoba, olimpia, önkéntes) erre az alapra épül.

**Nyitott kérdések** (változatlanok): a koordinátorok és jóváhagyók személye, a kategóriagazdák névsora, a médiatár-megoldás.
