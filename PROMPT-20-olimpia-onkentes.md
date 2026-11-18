# Prompt Code-nak — 20. Az olimpia- és az önkéntes-űrlap

> **Használat:** másold be Code-nak.

---

## Hatókör

Két publikus űrlap: a **Családi Olimpia** jelentkező és az **önkéntes** jelentkező.
*(A szabadulószoba egyelőre NEM kell — tulajdonosi döntés, 2026-11-18.)*

Mindkettő a programbeküldő űrlap alapjaira épül, de **belépés nélküli, publikus** űrlap — tehát a
védelem itt nem opcionális: **méz-bödön + IP-alapú gyakorisági korlát**, és a nyitva/zárva
kapcsoló **a végponton is** érvényesüljön, ne csak az űrlap tűnjön el.

## Olvasd el ELŐSZÖR

- `ALLAPOT.md`, `CLAUDE.md` — **különösen a 2/e pontot** (1:1) és a Beállítások-szabályt (2/b).
- A 2026-os megvalósítás **csak olvasásra**: `..\forgatag-os\olympics-api\server.mjs`,
  `..\forgatag-os\volunteer-api\server.mjs`.

**A mezőket, feliratokat, hibaüzeneteket és a nyilatkozatok szó szerinti szövegét vedd át
egy az egyben.** A nyilatkozatokat azért, mert azok **bizonyítékként tárolódnak** — ha
átfogalmazod, a korábbi elfogadások értelmüket vesztik.

---

## 1. Olimpia — a színfoglalás a lényeg

🔴 **A csapatszín atomi foglalás.** Az egyediség **az adatbázis megszorítása**, nem a felület
állapota. Két csapat egyszerre is kattinthat; ilyenkor az adatbázis utasítja vissza a másodikat.

**Ütközéskor** emberi üzenet, **friss színlista**, és **a beírt adatok maradjanak meg.** A 2026-os
szöveg: „A(z) „X" színt az előbb elvitte egy másik csapat. Válassz másik színt — a beírt adatok
megmaradnak."

**Ezt élesben is próbáld ki**, két böngészőablakból, ugyanarra a színre.

**Létszámkorlát és várólista:** a korlátig `applied`, utána `waitlist`, a jelentkezés sorrendje
szerint. **Ha két jelentkezés versenyez, a sorrendet utólag is helyre kell tenni.**
**Visszalépéskor a szín felszabadul.**

**Csapattagok:** 7 fő, **1–5 kötelező**, 6–7 opcionális. **Félig kitöltött tag hiba.**

**Visszaigazolás megy a jelentkezőnek** — itt azonnal, a programbeküldővel ellentétben —, és
értesítés a koordinátornak.

## 2. Önkéntes — itt kiskorúak adatai vannak

🔴 **Ez a legérzékenyebb űrlap a rendszerben.** Kiskorúak neve, életkora, egészségügyi
megjegyzése és **szülői hozzájárulás-fájlok** érkeznek rajta.

**A feltöltött fájlok a ZÁRT tárba menjenek**, soha ne a nyilvános médiatárba. *(A 2026-os
behozatalkor 34 ilyen mellékletet szándékosan kihagytunk — ugyanez a logika.)*

**A 16 év alatti szabály:** ha a jelentkező 16 alatti, **a szülői hozzájárulás ÉS a motivációs
levél is kötelező.** 16 felett opcionális, de a formátumot akkor is ellenőrizni kell.

**Egészségügyi megjegyzés kötelező** („ha nincs, írd: »nincs«").

⚠️ **A napok listája MÁS, mint a programoknál!** Az önkéntes-űrlapon szerepel egy **nulladik nap** és
egy **utómunkálatok nap** is, ami a programnapok közt nincs. 2026-ban ez beégetve volt a fájlban.
**Most a Beállításokból jöjjön, saját listaként** — ne a programnapokat használd.

**Értesítés:** az önkéntes-koordinátor szerepkörű felhasználóknak.

## 3. Beállítások — új blokkok, NEM új menüpontok

`CLAUDE.md` 2/b: a meglévő Beállítások lapra **két új blokk**.

| Blokk | Tartalma |
|---|---|
| Olimpia | nyitva/zárva + üzenet · a választható színek · létszámkorlát · koordinátor címe |
| Önkéntes | nyitva/zárva + üzenet · **az önkéntes-napok saját listája** · korhatár (ma 16) · koordinátor címe |

🔴 **Ha a koordinátor címe üres, a lap jelezze pirossal** — ahogy a programbeküldésnél. 2026-ban
pont ez okozott bajt: értesítések mentek nem létező címre.

## 4. Admin

A jelentkezések listája, a programokhoz hasonló módon. **Új felület épül, tehát
kategóriagazda-fiókkal is próbáld végig**, a közvetlen címet is begépelve (4. adósság).

⚠️ **Az önkéntes-jelentkezések személyes adatot tartalmaznak** — csak az férjen hozzá, akinek
tényleg kell.

## 5. Ellenőrzés, mielőtt készre jelented

- **375 px-en** mindkét űrlap végig.
- **Két ablakból ugyanarra a színre, élesben** → a második emberi üzenetet kap, friss listát, és nem
  veszti el a beírt adatait.
- A **létszámkorlát feletti** jelentkezés várólistára kerül.
- **16 év alatti** jelentkezőnél a két fájl **kötelező**.
- A feltöltött fájlok **nem érhetők el a webről** — próbáld meg közvetlen címmel.
- **Félig kitöltött csapattag** hibát ad.
- Zárt állapotban **a végpont is nemet mond.**
- A **méz-bödön** kitöltve → látszólagos siker, de nincs mentés.
- Élesben is telepítve és megnézve.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Ha kettő egy körben nem fér el jól, vágd ketté** és jelezd.
- **Néma hiba tilos.**
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
- **A kör végén frissítsd az `ALLAPOT.md`-t.**
