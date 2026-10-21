# Prompt Code-nak — 03. A szabadulószoba-jelentkező

> **Használat:** másold be Code-nak.

---

## Hatókör — CSAK a szabadulószoba-űrlap

A három hátralévő űrlap (szabadulószoba, olimpia, önkéntes) **külön körökben** készül.
Ez az első, mert a legnehezebb: **az időpont-sáv atomi foglalása** itt dől el, és ugyanaz a minta
hajtja majd az olimpia színfoglalását. Ha ez jól van megoldva, a másik kettő olcsó.

## Olvasd el ELŐSZÖR

- Ebben a mappában: `CLAUDE.md`.
- A 2026-os megvalósítás **csak olvasásra**: `..\forgatag-os\escape-room-api\server.mjs`.

**A mezőket, a feliratokat, a hibaüzeneteket és a nyilatkozatok szó szerinti szövegét vedd át
egy az egyben.** A nyilatkozatok szövegét azért, mert azok **bizonyítékként tárolódnak** — ha
átfogalmazod, a korábbi elfogadások értelmüket vesztik.

---

## 1. Ami más, mint a programbeküldőnél

**Ez PUBLIKUS űrlap, belépés nélkül.** A programbeküldőt a belépési fal védte; itt nincs ilyen.
Ezért a védelem ezen az űrlapon **nem opcionális**:

- **méz-bödön mező** (rejtett mező, amit csak a robot tölt ki) → látszólagos siker, de nincs mentés,
- **IP-alapú gyakorisági korlát**,
- a nyitva/zárva kapcsoló **a végponton is** érvényesüljön, ne csak az űrlap tűnjön el.

## 2. A sávfoglalás — ez a kör lényege

**A sáv a BEKÜLDÉSSEL válik foglalttá, nem a jóváhagyással.** Ez tulajdonosi döntés, és felülírja
a 2026-os eredeti tervet. Aki előbb küldi be, azé a sáv.

🔴 **A foglalás egyetlen igazsága az adatbázis egyediségi megszorítása, NEM a felület állapota.**
Két csapat egyszerre is kattinthat; ilyenkor az adatbázis utasítja vissza a másodikat.

**Ütközéskor:** a válasz mondja meg emberi nyelven, hogy a sáv elkelt, adjon **friss sávlistát**, és
**a beírt adatok maradjanak meg** — a csapat ne kezdje elölről. A 2026-os szöveg:
„A(z) <sáv> időpont közben elkelt — egy másik csapat egy pillanattal előbb küldte be.
Válasszatok másik sávot; a beírt adatok megmaradnak."

**Ezt élesben is próbáld ki**, ne csak tesztben: két böngészőablakból, ugyanarra a sávra.

## 3. Mezők és szabályok

- **Csapatnév.**
- **6 tag**, ebből **1–3 kötelező** (tehát 3–6 fős csapat), 4–6 opcionális.
  **Félig kitöltött tag hiba** — ha név van, kor is kell, és fordítva.
- **Csapatkapitány:** név, telefon, e-mail.
- **Sáv:** nap + időpont. A sávokat a Beállítások adja (lásd lentebb), ne legyenek beégetve.
- **Négy kötelező pipáló**, és **mind a négy szó szerinti szövege tárolódjon** a jelentkezés mellé,
  elfogadás-bizonyítékként.
- **Kiskorú:** ha bármelyik tag 16 alatti, a rendszer jelölje meg, és kínálja fel a szülői
  hozzájárulás letölthető nyomtatványát.

## 4. Beállítások — ide kerüljön, nem kódba

A `CLAUDE.md` 2/b pontja szerint: **egy új „Szabadulószoba" blokk** a meglévő Beállítások lapon.
**Új menüpontot NE vegyél fel.** Benne:

- nyitva/zárva + a zárt állapot üzenete,
- a **sávok**: mely napokon és mely időpontokban (2026-ban 3 nap × 5 időpont = 15 sáv, beégetve),
- a koordinátor értesítési címe,
- a kiskorúsági korhatár (2026-ban 16).

## 5. Értesítések

- A koordinátor kap levelet az új jelentkezésről.
- A jelentkező **visszaigazolást kap** — ellentétben a programbeküldővel, itt ez azonnal megy.
- A sávütközésről is legyen jelzés.

🔴 **2026-ban itt konkrét baj volt:** nem létezett szabadulószoba-koordinátor, és az értesítések egy
esetleg nem létező tartalék címre mentek. Ha a cím üres, a Beállítások lap **jelezze pirossal**,
ahogy a programbeküldésnél is.

## 6. Admin

A jelentkezések listája a programokhoz hasonló módon, `pending_review` állapotban érkezve.
**A sor-szintű jog mintája itt is érvényes**, ha a jelentkezéseket nem mindenki láthatja —
és ha új felületet építesz (export, kapcsolatkezelő), azt **kategóriagazda-fiókkal is próbáld végig**,
a közvetlen címet is begépelve.

## 7. Ellenőrzés, mielőtt készre jelented

- **375 px-en** végig.
- Félig kitöltött tag → hibaüzenet.
- 16 alatti tag → megjelenik a szülői hozzájárulás.
- **Két ablakból ugyanarra a sávra, élesben** → a második emberi üzenetet kap, friss listát, és
  nem veszti el a beírt adatait.
- Zárt állapotban a végpont is nemet mond.
- A méz-bödön kitöltve → látszólagos siker, de nincs mentés.

---

## Munkamód

- Ne írj döntéskérő dokumentumot. Ahol részletkérdés merül fel, döntsd el a legkonzervatívabb
  irányba, csináld meg, és egy sorban írd le, mit döntöttél.
- **Új admin-menüpontot ne vegyél fel.** A szabadulószoba beállításai blokként mennek a meglévő
  Beállítások lapra.
- **Néma hiba tilos.** Ha a mentés sikerült, de a levél nem ment ki, az ne látsszon sikertelen
  beküldésnek — és fordítva se.
- **Jelentés: legfeljebb 5 sor** + külön a felhasználónak szóló teendők.
