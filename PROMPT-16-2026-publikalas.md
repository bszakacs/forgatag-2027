# Prompt Code-nak — 16. A 2026-os oldal élő publikálása (galéria + aftermovie)

> **Használat:** másold be Code-nak. **Ez egyszeri üzemeltetési feladat, nem fejlesztés.**

---

## A helyzet

A tulajdonos **feltöltötte az idei galériát és az aftermovie-t** a 2026-os rendszer Directusába
(`admin.forgatag.ro`). **Ki kell tenni az élő `forgatag.ro`-ra.**

**A szokásos út nem járható:** a `forgatag.ro/tema` lapon a „Élő publikálás" gomb nem ismeri fel a
belépést. A `/tema` saját munkamenetet tart nyilván, külön a Directus admin-belépésétől, és az
hónapok óta nem volt használva — valószínűleg lejárt. **A tulajdonosnak most nincs ideje ezzel
bajlódni.**

## A feladat

**Tedd ki az oldalt élesbe.** Az utat te választod meg, de a lehetőségek:

1. **Helyi build + feltöltés.** A `..\forgatag-os` repóban megvan minden; a Directus az interneten
   elérhető (`admin.forgatag.ro`), a feltöltéshez a hitelesítő adatok a `.env`-ben.
2. **A NAS-on futtatva** — de tudd: a `Portik` SSH-felhasználó **már nem futtathat Docker-parancsot**
   a NAS-on (2026-10-21-i tapasztalat), a `publish-live.sh` viszont Dockerben épít.
3. **DSM Feladatütemező** — ehhez a tulajdonos kattintása kell. Ha ez az út, **írd le kattintásszinten**,
   mit nyomjon meg.

**Válaszd a legkevesebb mozgó alkatrészt igénylőt**, és ha elakadsz, **kérdezz** — ne találgass.

## Kötelező óvatosságok

🔴 **A `..\forgatag-os` BE VAN FAGYASZTVA.** Build-melléktermék keletkezhet benne, de **forráskódot
ne módosíts**, és semmit ne „javíts ki" útközben. Ha hibát találsz, **írd le, ne javítsd.**

🔴 **A publikálás MINDENT kitesz, ami a Directusban változott**, nem csak a galériát. Augusztus óta
volt ott próbálkozás (pl. 2026-09-02-án egy gépi téma-publikálás). **Publikálás ELŐTT nézd meg, mi
fog kimenni**, és sorold fel a tulajdonosnak. Ha félkész vagy teszt-tartalom van köztük, **állj meg
és kérdezz.**

🔴 **Használd a meglévő szkripteket, ne írj újat.** A 2026-os publikálási láncban benne van a
növekményes feltöltés, az egyetlen kapcsolat és a `version.txt` ellenőrző kapu — ezek tavaly
kilenc nap szenvedés után születtek:
- a tárhely **Imunify360**-ja blokkolta a párhuzamos FTP-kapcsolatokat,
- és kétszer is előfordult, hogy a lánc **sikert jelentett, miközben semmi nem történt.**

## Mit kell bizonyítani a végén

**A „feltöltöttem" önmagában semmit nem jelent.** A jelentésben szerepeljen:

- a `version.txt` ellenőrző kapu **átment**,
- a **galéria látszik** az élő `forgatag.ro`-n (add meg a pontos címet),
- az **aftermovie lejátszható**,
- a nyitólap és egy programlap **nem tört el**,
- **375 px-en** is rendben.

---

## Munkamód

- A tulajdonosnak nincs ideje ezzel foglalkozni — **csináld meg, és csak akkor kérdezz, ha tényleg
  elakadtál** vagy ha valami váratlan menne ki.
- **Jelszót nem gépelsz.**
- **Néma hiba tilos.** Ha a lánc sikert jelent, de a lap nem változott, azt mondd ki.
- **Jelentés: legfeljebb 5 sor** + a fenti bizonyítékok.
