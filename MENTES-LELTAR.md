# Leltár — a 2026-os adatok mentése (2026-10-21)

**A mentés helye:** `E:\Work\mysites\forgatag.ro\MENTES-2026\2026-10-21\` (688 MB, nem git-tároló).
Benne: `OLVASS-EL.txt`, `SHA256SUMS.txt` (1097 fájl). **Személyes adatot tartalmaz.**
**Második példány: ✅ elkészült** (a felhasználó, 2026-10-21; `sha256sum -c` hibátlan). Helyét a felhasználó tudja.

## Mi lett kimentve, és honnan tudjuk, hogy ép

| Mi | Hogyan | Ellenőrzés |
|---|---|---|
| Adatbázis (PostgreSQL) | a NAS saját napi mentése, 2026-10-21 14:29 (32 MB tömörítve, 231 MB SQL) | a „dump complete” zárójel megvan · sha256 egyezik a NAS-on lévővel · **üres PostgreSQL 16-ba visszatöltve, 0 hiba** |
| Fájltár (Directus) | a Directus `/assets/{id}?download` végpontja (eredeti fájl, átméretezés nélkül), a NAS-on futtatva | **1091 / 1091 fájl**, mindegyik mérete egyezik a nyilvántartottal · sha256 fájlonként egyezik a NAS és a gép között |
| Téma-JSON | a visszatöltött adatbázisból | `tema/theme-data.json` (32 KB) |

## Sorok gyűjteményenként

| Gyűjtemény | Sor | Megjegyzés |
|---|---:|---|
| Programok (`program_item`) | 241 | mind `published`; időpont mindegyiknél van (372 időpont, 2026-08-24 … 08-30); borítókép mindegyiknél |
| Fordítások (`translation`) | 487 | csak programokhoz (cím 241, leírás 241, jegyinfó 5) |
| Kategóriák | 23 | |
| Helyszínek | 46 | 18 fő + 28 alhelyszín |
| Hírek | 12 | mind `published` |
| Lapok / lapblokkok | 13 / 9 | |
| Menük / menüpontok | 2 / 21 | |
| Támogatók / szponzorszintek | 112 / 11 | logó mindegyiknél van |
| Galériák / galéria-képek | 12 / 467 | 2013–2025 |
| Videók / videócsoportok | 26 / 3 | |
| Csapattagok | 56 | |
| Kézművesek (`craftsperson`) | 67 | |
| Médiaelemek (`media_asset`) | 289 | |
| Programgazdák / kategória-hozzárendelés | 14 / 49 | |
| Önkéntes-jelentkezések | 69 | személyes adat |
| Szabadulószoba-foglalások | 18 | személyes adat |
| Kedvenclisták | 15 | |
| FB-posztok / poszt–program | 30 / 474 | |
| Feladatok (`task`) | 7 | |
| Olimpia: csapatok / színek | 6 / 8 | |
| Téma | 1 | |
| Üres: `approval_log`, `change_log`, `program_highlight`, `program_item_category`, `sponsor_obligation` | 0 | |
| Directus: felhasználók / szerepkörök / flow-k | 20 / 13 / 19 | |
| Directus: napló / revíziók | 208 153 / 202 664 | az adatbázis nagyobb része ez |

**Fájlok:** 1091 db, 657 MB — JPEG 439 (480 MB), WebP 521 (87 MB), PNG 120 (83 MB), PDF 9, egyéb 2.

## Ami hiányzik vagy gyanús

**Eltérés a kezdő adatoktól (a CLAUDE.md-k ezekkel számolnak):**
1. **Kategóriák: 23, nem 24.** A seedhez képest **hiányzik 5**: `erdelyi-neptanc-antologia`, `ifi-setany`,
   `kandake-a-forgatagon`, `kerekasztal-beszelgetesek`, `verbunk`. **Új 4**: `folk-forgo`, `kezmuves-vasar`,
   `pulzus-by-rmdsz-maros`, `szenior-sziget`. (A kategóriák törlése 2026-ban szabad volt — de a 2027-es
   `SubmissionForm` extra mezői még a `kerekasztal-beszelgetesek` slugra is hivatkoznak.)
2. **Helyszínek: 46, nem 59** (a seedben 52 sor van, a CLAUDE.md 59-et ír — a három szám nem egyezik).
3. **Egyik helyszínnek sincs GPS-koordinátája** (46/46 üres `gps`). A térkép-pozíció (`map_x`) 41-nél van, 5-nél nincs.

**Román párok:**
4. **A 12 hír egyikének sincs román szövege** (cím, kivonat, törzs — 12/12 üres).
5. Csapattagok szerepköre: 45/55 román nélkül · videócímek: 18/26 · menüpontok: 9/21 · lapblokk: 1 cím + 1 törzs.
6. Programok: 1 programnak nincs érvényes román címe (a sora `outdated`), és 1 leírás `outdated`. Gépi
   fordítás (át nem nézett): 140 cím, 105 leírás, 5 jegyinfó.
7. **A program román címe KÉT helyen van** (`program_item.ro_title` tükör-oszlop — mind a 241 kitöltve — és a
   `translation` tábla), és **3 programnál a kettő eltér.** Melyik volt kint, azt a 2026-os frontend kódja
   dönti el; importnál ezt tisztázni kell.

**Fájlok:**
8. **52 fájlra semmi nem hivatkozik** (se kép-mező, se galéria, se lap, se téma) — lehet maradék, lehet elveszett kapcsolat.
9. 2 fájl típusa `application/octet-stream`, a neve „… másolata” (`FomcoGroup.jpeg másolata`, `1.edunium logo (1).png másolata`) — kép, rossz típussal.
10. A téma favicon-ja **teljes címmel** a NAS-ra mutat (`https://admin.forgatag.ro/assets/…`) — a NAS nélkül eltörik.
    A téma `logo` és `logoHeader` mezője üres (a kódbeli alapértelmezés ment ki).
11. 48 médiaelemhez nem tartozik program. Licenc: 265 „minden jog fenntartva”, 24 „közkincs” — „ismeretlen” nincs.
12. 1 csapattagnak nincs fotója. 2020-as galéria nincs (2019 után 2021 jön).

**Napló, működés:**
13. A **jóváhagyási és változási napló üres** (`approval_log`, `change_log` = 0) — 2026-ban nem írt bele semmi;
    ki-mikor-hagyta-jóvá adat nincs.
14. A téma utolsó KÉT publikálása **gépi próba** (`schema-bot`, 2026-09-02 12:50 és 13:04, „nyelvváltó —
    végpontok közötti próba”). **Mezőnként összevetve (2026-10-21):** az öt megőrzött verzió (08-26 22:14,
    08-27 09:56, 09-02 11:56, 12:50, 13:04 — mind 88 mező) között **csak a `version` számláló változott**
    (7 → 11); minden más érték betűre azonos. A `header.showLangSwitch` mind az ötben `true` — a gépi
    próba a nyelvváltót sem állította át, semmit nem változtatott. **Az aktuális verzió átvehető.**
    Ami a 08-26-i előtt volt, az nincs meg (az előzmény csak 4 verziót őriz).
15. **A NAS napi mentésében rejtett hiba:** `pg_dump | gzip` — a sikert a gzip adja vissza, nem a pg_dump, ezért
    egy félbeszakadt mentés is „kész”-nek látszhat. A mostani mentés ép (zárójel + visszatöltés), de a régiekre ez nem garancia.
16. A Portik SSH-felhasználó a NAS-on **már nem futtathat Docker-parancsot** (júliusban még tudott) —
    ezért a fájltár a Directus API-n jött le, nem a kötetből.

**Személyes adat a mentésben:** programok kapcsolattartói (241 e-mail), 14 programgazda, 69 önkéntes
(szülői hozzájárulás-fájlokkal), 18 szabadulószoba-foglalás, 20 admin-fiók (jelszó-hash, statikus tokenek
nyílt szövegben a `directus_users.token`-ben), a Directus-napló (IP-címek).
