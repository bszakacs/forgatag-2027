<?php

namespace App\Support;

use App\Enums\ContentStatus;
use App\Enums\Gyujtemeny;
use App\Enums\MediaLicense;
use App\Enums\MediaOrigin;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A publikus sablonok SZERKESZTHETŐ SZÖVEGEI — EGY helyen (9. adósság).
 *
 * 2026-ban a lapok szövegének nagy része a sablonba volt égetve, és ami szerkeszthető volt, annak is
 * „üres = a sablon beépített szövege” volt a működése: az adminból nem látszott, mi van kint. Most
 * minden szöveg, ami a lapon látszik, egy kulcs az adatbázisban (PageText), és a sablon CSAK onnan olvas.
 * Az itteni alapértékek a 2026-os sablon szövegei: a `szinkron()` EGYSZER beírja őket az adatbázisba
 * (a telepítés futtatja), onnantól az admin az igazság, és a lapon nincs láthatatlan beépített szöveg.
 *
 * Típusok: sor (egysoros), szoveg (bekezdés), kep (médiatár-kép; `fajl` = a kezdőkép a seed/lapok/ alól),
 * link (cím; nyelvfüggetlen, a magyar mezőben).
 * `csoport`: egy több darabból álló cím részei. Románul a csoport EGYÜTT esik vissza a magyarra: ha
 * bármelyik része le van fordítva, a román változat érvényes (egy szándékosan üres rész üres marad).
 *
 * Új sablon = új elem a SABLONOK-ban + a resources/views/oldal/<slug>.blade.php. Ami kikerül innen, azt a
 * szinkron az adatbázisból is törli (négy-hely szabály).
 */
class LapSablon
{
    public const TIPUSOK = ['sor', 'szoveg', 'kep', 'link'];

    /** @var array<string, list<array{0: string, 1: string, 2: string, 3?: string|array{hu: string, ro?: string}, csoport?: string, fajl?: string}>> */
    public const SABLONOK = [
        // A KEZDŐLAP (PROMPT-14) — a 2026-os `home-v7/body.html` `data-hs`/`data-img` kulcsai, ugyanazzal a névvel (a 2026-os
        // tartalom ezekre jött át). Ami 2026-ban beégetett volt (a „számokban” értékei, a szervezők neve), az is kulcsot kapott.
        // A visszaszámláló négy kulcsa 2026-08-26-án kikerült (a fesztivál alatt) — a „fesztivál előtti” állapothoz visszajött.
        'home' => [
            ['home_hero_slide_1', 'Nyitókép — 1. dia (fotó)', 'kep'],
            ['home_hero_slide_2', 'Nyitókép — 2. dia (fotó)', 'kep'],
            ['home_hero_slide_3', 'Nyitókép — 3. dia (fotó)', 'kep'],
            ['home_hero_slide_4', 'Nyitókép — 4. dia (fotó)', 'kep'],
            ['home_hero_slide_5', 'Nyitókép — 5. dia (fotó)', 'kep'],
            ['home_hero_title_a', 'Nyitókép — cím, eleje (a kiemelt rész)', 'sor', '2027. augusztus 25-29.', 'csoport' => 'hero'],
            ['home_hero_title_b', 'Nyitókép — cím, folytatása (új sorban)', 'sor', ' között újra találkozunk!', 'csoport' => 'hero'],
            ['home_hero_countdown_label', 'Visszaszámláló — felirat fölötte (csak „fesztivál előtt” állapotban)', 'sor', 'Még'],
            ['home_hero_cd_days', 'Visszaszámláló — „nap”', 'sor', 'nap'],
            ['home_hero_cd_hours', 'Visszaszámláló — „óra”', 'sor', 'óra'],
            ['home_hero_cd_mins', 'Visszaszámláló — „perc”', 'sor', 'perc'],
            ['home_hero_countdown_foot', 'Visszaszámláló — alsó sor', 'sor', 'Ott találkozunk!'],

            ['home_highlight_title_a', 'Kiemelt programok — cím (csak „fesztivál előtt” állapotban)', 'sor', 'Kiemelt', 'csoport' => 'kiemelt'],
            ['home_highlight_title_b', 'Kiemelt programok — cím kiemelt része', 'sor', 'programok', 'csoport' => 'kiemelt'],
            ['home_highlight_1_img', 'Kiemelt programok — 1. kártya képe', 'kep'],
            ['home_highlight_1_title', 'Kiemelt programok — 1. kártya címe', 'sor', 'Nagyszínpadi záróbuli'],
            ['home_highlight_2_img', 'Kiemelt programok — 2. kártya képe', 'kep'],
            ['home_highlight_2_title', 'Kiemelt programok — 2. kártya címe', 'sor', 'A Noszty fiú – nagyszínházi bemutató'],
            ['home_highlight_3_img', 'Kiemelt programok — 3. kártya képe', 'kep'],
            ['home_highlight_3_title', 'Kiemelt programok — 3. kártya címe', 'sor', 'Interaktív mesejáték gyerekeknek'],
            ['home_highlight_4_img', 'Kiemelt programok — 4. kártya képe', 'kep'],
            ['home_highlight_4_title', 'Kiemelt programok — 4. kártya címe', 'sor', 'Borkert – kóstoló és beszélgetés'],
            ['home_highlight_5_img', 'Kiemelt programok — 5. kártya képe', 'kep'],
            ['home_highlight_5_title', 'Kiemelt programok — 5. kártya címe', 'sor', 'Erdélyi Néptánc Antológia – gála'],
            ['home_highlight_6_img', 'Kiemelt programok — 6. kártya képe', 'kep'],
            ['home_highlight_6_title', 'Kiemelt programok — 6. kártya címe', 'sor', 'Éjszakai felolvasás a Téka udvarán'],

            ['home_news_title_a', 'Hírek — cím (a román kezdőlapon a hírek nem látszanak)', 'sor', 'Ami a forgatag körül', 'csoport' => 'hirek'],
            ['home_news_title_b', 'Hírek — cím kiemelt része', 'sor', 'történik', 'csoport' => 'hirek'],
            ['home_news_cta', 'Hírek — gomb', 'sor', 'Összes hír'],

            ['home_numbers_title_a', 'Számokban — cím', 'sor', 'Forgatag', 'csoport' => 'szamok'],
            ['home_numbers_title_b', 'Számokban — cím kiemelt része', 'sor', 'számokban', 'csoport' => 'szamok'],
            ['home_stats_value_programs', 'Számokban — 1. szám (pl. 370, vagy 110+)', 'sor', '370'],
            ['home_stats_label_programs', 'Számokban — 1. felirat', 'sor', 'program'],
            ['home_stats_value_venues', 'Számokban — 2. szám', 'sor', '42'],
            ['home_stats_label_venues', 'Számokban — 2. felirat', 'sor', 'helyszín'],
            ['home_stats_value_days', 'Számokban — 3. szám', 'sor', '7'],
            ['home_stats_label_days', 'Számokban — 3. felirat', 'sor', 'nap'],
            ['home_stats_value_partners', 'Számokban — 4. szám', 'sor', '110+'],
            ['home_stats_label_partners', 'Számokban — 4. felirat', 'sor', 'partner'],
            ['home_stats_value_meetings', 'Számokban — 5. szám', 'sor', '10000+'],
            ['home_stats_label_meetings', 'Számokban — 5. felirat', 'sor', 'találkozás'],
            ['home_numbers_cta', 'Számokban — gomb', 'sor', 'A Forgatag története'],

            ['home_media_title_a', 'Képekben — cím', 'sor', 'A Forgatag', 'csoport' => 'media'],
            ['home_media_title_b', 'Képekben — cím kiemelt része', 'sor', 'képekben', 'csoport' => 'media'],
            ['home_media_videos_title', 'Képekben — videósáv címe (a videók: a Videó archívum lap ELSŐ videós blokkja)', 'sor', 'Aftermoviek'],
            ['home_media_gallery_title', 'Képekben — galériasáv címe (a galériák: Tartalom → Galériák)', 'sor', 'Galéria évenként'],
            ['home_media_cta_mestersegem', 'Képekben — narancs gomb', 'sor', 'Mesterségem Vásárhelyi'],
            ['home_media_cta_szinejava', 'Képekben — zöld gomb', 'sor', 'Vásárhely színe-java'],

            ['home_organizers_title_a', 'Mögött állnak — cím', 'sor', 'A Forgatag', 'csoport' => 'szervezok'],
            ['home_organizers_title_b', 'Mögött állnak — cím kiemelt része', 'sor', 'mögött állnak', 'csoport' => 'szervezok'],
            ['home_organizers_rail_title', 'Mögött állnak — „Szervező egyesületek” felirat', 'sor', 'Szervező egyesületek'],
            ['home_org_logo_1', 'Mögött állnak — 1. szervező logója', 'kep', 'fajl' => 'rolunk/about_organizer_logo1.jpg'],
            ['home_org_name_1', 'Mögött állnak — 1. szervező neve (a támogatói sávból ő kimarad)', 'sor', 'Marosvásárhelyért Egyesület'],
            ['home_org_logo_2', 'Mögött állnak — 2. szervező logója', 'kep', 'fajl' => 'rolunk/about_organizer_logo2.jpg'],
            ['home_org_name_2', 'Mögött állnak — 2. szervező neve (a támogatói sávból ő kimarad)', 'sor', 'Parapács Egyesület'],
            ['home_sponsors_rail_title', 'Mögött állnak — a támogatói sáv címe (a logók: Tartalom → Támogatók)', 'sor', 'Partnereink és támogatóink'],
            ['home_organizers_cta', 'Mögött állnak — gomb', 'sor', 'A Forgatag története'],
        ],
        'rolunk' => [
            ['about_hero_line1', 'Nyitócím — 1. sor', 'sor', 'Vásárhelyi Forgatag', 'csoport' => 'hero'],
            ['about_hero_line2', 'Nyitócím — 2. sor (sárga)', 'sor', 'a rendezvény, amely', 'csoport' => 'hero'],
            ['about_hero_line3', 'Nyitócím — 3. sor (narancs)', 'sor', 'összeköti a várost', 'csoport' => 'hero'],

            ['about_story_p1', 'Történet — bevezető bekezdés', 'szoveg', 'Vannak hagyományok, amelyeket örököl az ember, és vannak olyanok, amelyeket egy közösség együtt teremt meg. A Vásárhelyi Forgatag ilyen történet.'],
            ['about_story_p2', 'Történet — 2. bekezdés', 'szoveg', 'Ezt a hagyományt a Marosvásárhelyért Egyesület indította útjára 2013-ban, amikor létrehozta a Vásárhelyi Forgatagot. A kezdeményezés kezdettől fogva több volt egy fesztiválnál: olyan közösségi ünnepet álmodott meg, amely összehozza az itt élőket, a környékbelieket és azokat is, akiket messzire sodort az élet, de akiknek Marosvásárhely ma is az otthont jelenti.'],
            ['about_story_photo1', 'Történet — 1. kép', 'kep'],
            ['about_story_year1', 'Történet — 1. kép évszáma', 'sor', '2014'],
            ['about_story_caption1', 'Történet — 1. kép felirata', 'sor', 'A kezdetek — Maros-part'],
            ['about_story_p3', 'Történet — 3. bekezdés', 'szoveg', 'A történet a Maros-parton kezdődött, ott telt meg először a tér zenével, vásári zajjal, nevetéssel és élettel. A Forgatag később a Ligetbe költözött, ott tovább növekedett, egyre gazdagabb programkínálattal és mind több látogatóval, végül megérkezett a marosvásárhelyi Várba, amely történelmével, hangulatával és méltóságával valódi otthonává vált a rendezvénynek.'],
            ['about_story_photo2', 'Történet — 2. kép', 'kep'],
            ['about_story_year2', 'Történet — 2. kép évszáma', 'sor', '2015'],
            ['about_story_caption2', 'Történet — 2. kép felirata', 'sor', 'Növekvő közösség'],
            ['about_story_p4', 'Történet — 4. bekezdés', 'szoveg', 'A Forgatag azonban sosem szorul egyetlen helyszín falai közé. Miközben a rendezvény szíve a Várban dobog, Marosvásárhely számos pontja egyszerre kapcsolódik be az ünnepbe. Kulturális intézmények, kiállítóterek, könyvtárak, közösségi terek, udvarok és történelmi épületek nyitják meg kapuikat, így néhány napra maga a város válik a Forgatag színterévé. A látogatók nemcsak egyik programról a másikra sétálnak, hanem közben újra felfedezik Marosvásárhely épített örökségét, kulturális értékeit és rejtett kincseit is.'],
            ['about_story_photo3', 'Történet — 3. kép', 'kep'],
            ['about_story_year3', 'Történet — 3. kép évszáma', 'sor', '2016'],
            ['about_story_caption3', 'Történet — 3. kép felirata', 'sor', 'A Liget évei'],
            ['about_story_p5', 'Történet — 5. bekezdés', 'szoveg', 'Az első Forgatag óta minden augusztus végén új ritmusra ébred a város. Megtelnek a terek zenével, a színpadok történetekkel, az udvarok gyermekkacajjal. Táncosok perdülnek, kézművesek alkotnak, művészek, gondolkodók és közösségek mutatják meg mindazt, amit értéknek tartanak. Régi barátok találnak egymásra, családok indulnak közös felfedezőútra, az elszármazottak pedig néhány napra ismét hazatérnek.'],
            ['about_story_photo4', 'Történet — 4. kép', 'kep'],
            ['about_story_year4', 'Történet — 4. kép évszáma', 'sor', '2023'],
            ['about_story_caption4', 'Történet — 4. kép felirata', 'sor', 'Megérkezés a Várba'],
            ['about_story_p6', 'Történet — záró bekezdés', 'szoveg', 'Ez a Vásárhelyi Forgatag: egy közösség által teremtett ünnep, amely évről évre hazahív, találkozásokat szül, élményeket ad és közös emlékeket teremt. Mert a Forgatag több mint rendezvény: összeköt minket.'],
            ['about_story_photo5', 'Történet — 5. kép', 'kep'],
            ['about_story_year5', 'Történet — 5. kép évszáma', 'sor', '2024'],
            ['about_story_caption5', 'Történet — 5. kép felirata', 'sor', 'Egy egész város ünnepel'],

            ['about_organizers_title', 'Szervezők — cím', 'sor', 'Szervező', 'csoport' => 'szervezok'],
            ['about_organizers_title_accent', 'Szervezők — cím kiemelt része', 'sor', 'egyesületek', 'csoport' => 'szervezok'],
            ['about_organizer_logo1', 'Szervezők — 1. logó', 'kep', 'fajl' => 'rolunk/about_organizer_logo1.jpg'],
            ['about_organizer_name1', 'Szervezők — 1. egyesület neve', 'sor', 'Marosvásárhelyért Egyesület'],
            ['about_organizer_logo2', 'Szervezők — 2. logó', 'kep', 'fajl' => 'rolunk/about_organizer_logo2.jpg'],
            ['about_organizer_name2', 'Szervezők — 2. egyesület neve', 'sor', 'Parapács Egyesület'],

            ['about_related_title', 'Kapcsolódók — cím', 'sor', 'A Forgataghoz', 'csoport' => 'kapcsolodok'],
            ['about_related_title_accent', 'Kapcsolódók — cím kiemelt része', 'sor', 'kapcsolódó', 'csoport' => 'kapcsolodok'],
            ['about_related_logo1', 'Kapcsolódók — Borkert logója', 'kep', 'fajl' => 'rolunk/about_related_logo1.png'],
            ['about_related_name1', 'Kapcsolódók — Borkert neve', 'sor', 'Borkert'],
            ['about_related_logo2', 'Kapcsolódók — webshop logója', 'kep', 'fajl' => 'rolunk/about_related_logo2.png'],
            ['about_related_name2', 'Kapcsolódók — webshop neve', 'sor', 'Vásárhely'],
            ['about_related_url2', 'Kapcsolódók — webshop címe', 'link', 'https://vasarhely.forgatag.ro/'],

            ['about_borkert_photo', 'Borkert-ablak — kép', 'kep', 'fajl' => 'rolunk/about_borkert_photo.jpg'],
            ['about_borkert_title', 'Borkert-ablak — cím', 'sor', 'Borkert'],
            ['about_borkert_p1', 'Borkert-ablak — 1. bekezdés', 'szoveg', 'A Borkert a Vásárhelyi Forgatag egyik legkülönlegesebb találkozóhelye. Egy hangulatos tér a Marosvásárhelyi Várban, ahol a Kárpát-medence kiváló borai, az élő zene, a finom falatok és a baráti beszélgetések együtt teremtik meg a Forgatag egyik legkedvesebb hangulatát.'],
            ['about_borkert_p2', 'Borkert-ablak — 2. bekezdés', 'szoveg', 'A Borkert azért született, hogy a fesztivál nyüzsgésében legyen egy hely, ahol meg lehet állni egy pohár borra, találkozni régi ismerősökkel, új barátságokat kötni, vagy egyszerűen csak élvezni a nyári esték varázsát. Itt a bor nem csupán ital, hanem élmény, amely összehozza az embereket.'],
            ['about_borkert_p3', 'Borkert-ablak — 3. bekezdés', 'szoveg', 'Évről évre neves borászatok érkeznek a Borkertbe, hogy bemutassák boraikat és történeteiket. A kínálatot gondosan válogatott gasztronómiai különlegességek egészítik ki, így mindenki megtalálhatja a borokhoz illő ízeket.'],
            ['about_borkert_p4', 'Borkert-ablak — 4. bekezdés', 'szoveg', 'Amikor lemegy a nap, a Borkert új arcát mutatja. Az esték élő zenével, koncertekkel és közösségi programokkal telnek meg, amelyek még különlegesebbé teszik az együtt töltött időt. A hangulat egyszerre családias és pezsgő – olyan, amely miatt sokan évről évre visszatérnek.'],
        ],
        // A FESZTIVÁLREND (PROMPT-17) — a 2026-os `fesztivalrend/body.html` MINDEN szövege, a lapon elfoglalt sorrendben. A 25
        // 2026-ban is szerkeszthető kulcs (`data-hs`) a régi nevén; a többi 78 2026-ban beégetett magyar volt (a román lapon egy
        // egyszer lefordított külön törzs, `body.ro.html`) — a román alapszöveg onnan. A két dátum-kulcs a 2027-es fesztivál dátuma
        // (CLAUDE.md 1.; 2026-ban „2026. augusztus 26–30.”); a fesztivál sorszáma (ecsetírás, 3. sor) a 2026-os szöveg.
        'fesztivalrend' => [
            ['fesztivalrend_hero_title', 'Nyitókép — cím', 'sor', ['hu' => 'Fesztiválrend', 'ro' => 'Regulamentul festivalului']],
            ['fesztivalrend_hero_lead', 'Nyitókép — alcím', 'sor', ['hu' => 'Minden, amit tudnod érdemes a Vásárhelyi Forgatag előtt', 'ro' => 'Tot ce este bine să știi înainte de Vâltoarea Mureșeană']],
            ['fesztivalrend_intro_1', 'Nyitókép — 1. bekezdés', 'szoveg', ['hu' => 'A Vásárhelyi Forgatag Marosvásárhely közös ünnepe. Évről évre több tízezer látogatót fogadunk a város különböző pontjain, hogy együtt ünnepeljük kultúránkat, hagyományainkat és közösségünket.', 'ro' => 'Vâltoarea Mureșeană este sărbătoarea comună a Târgu-Mureșului. An de an primim zeci de mii de vizitatori în diferite puncte ale orașului, pentru a sărbători împreună cultura, tradițiile și comunitatea noastră.']],
            ['fesztivalrend_intro_2', 'Nyitókép — 2. bekezdés', 'szoveg', ['hu' => 'Ahhoz, hogy ez a néhány nap mindenki számára biztonságos, kényelmes és felejthetetlen élmény legyen, összegyűjtöttük a legfontosabb tudnivalókat. Arra kérünk, olvasd el figyelmesen a nyugodt, felhőtlen fesztiválozás érdekében.', 'ro' => 'Pentru ca aceste câteva zile să fie o experiență sigură, confortabilă și de neuitat pentru toată lumea, am adunat cele mai importante informații. Te rugăm să le citești cu atenție, pentru o experiență de festival liniștită și lipsită de griji.']],
            ['fesztivalrend_intro_ott', 'Nyitókép — „Ott találkozunk!”', 'sor', ['hu' => 'Ott találkozunk!', 'ro' => 'Ne vedem acolo!']],
            ['fesztivalrend_quick_title', 'Gyors tudnivalók — cím', 'sor', ['hu' => 'Gyors tudnivalók', 'ro' => 'Informații rapide']],
            ['fesztivalrend_quick_time_t', 'Gyors tudnivalók — 1. doboz címe', 'sor', ['hu' => 'Időpont', 'ro' => 'Perioada']],
            ['fesztivalrend_quick_time_p', 'Gyors tudnivalók — 1. doboz szövege (a fesztivál dátuma)', 'sor', ['hu' => '2027. augusztus 25–29.', 'ro' => '25–29 august 2027']],
            ['fesztivalrend_quick_place_t', 'Gyors tudnivalók — 2. doboz címe', 'sor', ['hu' => 'Helyszínek', 'ro' => 'Locații']],
            ['fesztivalrend_quick_place_p', 'Gyors tudnivalók — 2. doboz szövege', 'szoveg', ['hu' => 'A Vásárhelyi Forgatag programjainak fő helyszíne a Marosvásárhelyi Vár és a város történelmi belvárosa. A Programok aloldalon megtekinthetsz minden helyszínt és listázhatod az ott zajló programpontokat.', 'ro' => 'Locația principală a programelor Vâltorii Mureșene este Cetatea Târgu-Mureșului și centrul istoric al orașului. Pe subpagina Programe poți vedea toate locațiile și poți lista punctele de program care au loc acolo.']],
            ['fesztivalrend_quick_entry_t', 'Gyors tudnivalók — 3. doboz címe', 'sor', ['hu' => 'Belépés', 'ro' => 'Acces']],
            ['fesztivalrend_quick_entry_p', 'Gyors tudnivalók — 3. doboz szövege', 'szoveg', ['hu' => 'A Vásárhelyi Forgatag programjainak döntő többsége ingyenesen látogatható. Azoknál a programoknál, amelyek regisztrációhoz vagy jegyvásárláshoz kötöttek, ezt a programoldalon külön feltüntetjük.', 'ro' => 'Marea majoritate a programelor Vâltorii Mureșene pot fi vizitate gratuit. La programele care necesită înregistrare sau achiziționarea unui bilet, acest lucru este menționat separat pe pagina programului.']],
            ['fesztivalrend_quick_info_t', 'Gyors tudnivalók — 4. doboz címe', 'sor', ['hu' => 'Információ', 'ro' => 'Informații']],
            ['fesztivalrend_quick_info_p', 'Gyors tudnivalók — 4. doboz szövege', 'szoveg', ['hu' => 'A rendezvény ideje alatt információs pont működik a Vár területén, ahol önkénteseink készséggel segítenek kérdéseiddel kapcsolatban.', 'ro' => 'Pe durata evenimentului funcționează un punct de informare pe teritoriul Cetății, unde voluntarii noștri te vor ajuta cu plăcere cu întrebările tale.']],
            ['fesztivalrend_acc_bring_title', 'Részletek — „Mit hozz, és mit ne hozz?” — lenyíló címe', 'sor', ['hu' => 'Mit hozz, és mit ne hozz?', 'ro' => 'Ce să aduci și ce să nu aduci?']],
            ['fesztivalrend_bring_yes_title', 'Mit hozz — bal oszlop (ajánlott) címe', 'sor', ['hu' => 'Mit hozz magaddal?', 'ro' => 'Ce să aduci cu tine?']],
            ['fesztivalrend_bring_yes_note', 'Mit hozz — bal oszlop megjegyzése', 'sor', ['hu' => 'Az alábbi lista csupán ajánlás.', 'ro' => 'Lista de mai jos este doar o recomandare.']],
            ['fesztivalrend_bring_yes_1', 'Mit hozz — ajánlott tárgy 1. (az ikon a helyéhez tartozik)', 'sor', ['hu' => 'Naptej', 'ro' => 'Cremă cu protecție solară']],
            ['fesztivalrend_bring_yes_2', 'Mit hozz — ajánlott tárgy 2. (az ikon a helyéhez tartozik)', 'sor', ['hu' => 'Fejfedő', 'ro' => 'Pălărie']],
            ['fesztivalrend_bring_yes_3', 'Mit hozz — ajánlott tárgy 3. (az ikon a helyéhez tartozik)', 'sor', ['hu' => 'Kulacs', 'ro' => 'Sticlă de apă']],
            ['fesztivalrend_bring_yes_4', 'Mit hozz — ajánlott tárgy 4. (az ikon a helyéhez tartozik)', 'sor', ['hu' => 'Feltöltött telefon', 'ro' => 'Telefon încărcat']],
            ['fesztivalrend_bring_yes_5', 'Mit hozz — ajánlott tárgy 5. (az ikon a helyéhez tartozik)', 'sor', ['hu' => 'Esőkabát', 'ro' => 'Pelerină de ploaie']],
            ['fesztivalrend_bring_yes_6', 'Mit hozz — ajánlott tárgy 6. (az ikon a helyéhez tartozik)', 'sor', ['hu' => 'Kényelmes cipő', 'ro' => 'Încălțăminte comodă']],
            ['fesztivalrend_bring_yes_7', 'Mit hozz — ajánlott tárgy 7. (az ikon a helyéhez tartozik)', 'sor', ['hu' => 'Jókedv', 'ro' => 'Bună dispoziție']],
            ['fesztivalrend_bring_no_title', 'Mit hozz — jobb oszlop (tilos) címe', 'sor', ['hu' => 'Mit ne hozz a Forgatagra?', 'ro' => 'Ce să nu aduci la Vâltoare?']],
            ['fesztivalrend_bring_no_1', 'Mit hozz — tilos tárgy 1. (az ikon a helyéhez tartozik)', 'sor', ['hu' => 'Pirotechnikai eszközök', 'ro' => 'Articole pirotehnice']],
            ['fesztivalrend_bring_no_2', 'Mit hozz — tilos tárgy 2. (az ikon a helyéhez tartozik)', 'sor', ['hu' => 'Tűzijáték', 'ro' => 'Artificii']],
            ['fesztivalrend_bring_no_3', 'Mit hozz — tilos tárgy 3. (az ikon a helyéhez tartozik)', 'sor', ['hu' => 'Fegyver vagy fegyverutánzat', 'ro' => 'Arme sau replici de arme']],
            ['fesztivalrend_bring_no_4', 'Mit hozz — tilos tárgy 4. (az ikon a helyéhez tartozik)', 'sor', ['hu' => 'Veszélyes tárgyak', 'ro' => 'Obiecte periculoase']],
            ['fesztivalrend_bring_no_5', 'Mit hozz — tilos tárgy 5. (az ikon a helyéhez tartozik)', 'sor', ['hu' => 'Kábítószer és egyéb tiltott szerek', 'ro' => 'Droguri și alte substanțe interzise']],
            ['fesztivalrend_bring_no_6', 'Mit hozz — tilos tárgy 6. (az ikon a helyéhez tartozik)', 'sor', ['hu' => 'Engedély nélküli drón', 'ro' => 'Drone fără autorizație']],
            ['fesztivalrend_bring_no_7', 'Mit hozz — tilos tárgy 7. (az ikon a helyéhez tartozik)', 'sor', ['hu' => 'Nagy teljesítményű hangosító berendezések', 'ro' => 'Echipamente de sonorizare de mare putere']],
            ['fesztivalrend_bring_no_8', 'Mit hozz — tilos tárgy 8. (az ikon a helyéhez tartozik)', 'sor', ['hu' => 'Esernyő', 'ro' => 'Umbrelă']],
            ['fesztivalrend_acc_forbidden_title', 'Részletek — „Tiltott tevékenységek” — lenyíló címe', 'sor', ['hu' => 'Tiltott tevékenységek', 'ro' => 'Activități interzise']],
            ['fesztivalrend_acc_forbidden_p1', 'Részletek — „Tiltott tevékenységek” — 1. bekezdés', 'szoveg', ['hu' => 'A Vásárhelyi Forgatag területén a szervezők előzetes írásos engedélye nélkül tilos:', 'ro' => 'Pe teritoriul Vâltorii Mureșene, fără acordul scris prealabil al organizatorilor, este interzis:']],
            ['fesztivalrend_acc_forbidden_li1', 'Részletek — „Tiltott tevékenységek” — felsorolás 1. pontja', 'szoveg', ['hu' => 'kereskedelmi tevékenységet folytatni;', 'ro' => 'desfășurarea de activități comerciale;']],
            ['fesztivalrend_acc_forbidden_li2', 'Részletek — „Tiltott tevékenységek” — felsorolás 2. pontja', 'szoveg', ['hu' => 'reklám-, marketing- vagy propaganda anyagokat terjeszteni;', 'ro' => 'distribuirea de materiale publicitare, de marketing sau de propagandă;']],
            ['fesztivalrend_acc_forbidden_li3', 'Részletek — „Tiltott tevékenységek” — felsorolás 3. pontja', 'szoveg', ['hu' => 'politikai vagy egyéb demonstrációt, tüntetést szervezni vagy tartani;', 'ro' => 'organizarea sau desfășurarea de demonstrații ori manifestații politice sau de altă natură;']],
            ['fesztivalrend_acc_forbidden_li4', 'Részletek — „Tiltott tevékenységek” — felsorolás 4. pontja', 'szoveg', ['hu' => 'hangosító eszközökkel a rendezvényt vagy annak programjait zavarni;', 'ro' => 'perturbarea evenimentului sau a programelor acestuia cu ajutorul unor dispozitive de sonorizare;']],
            ['fesztivalrend_acc_forbidden_li5', 'Részletek — „Tiltott tevékenységek” — felsorolás 5. pontja', 'szoveg', ['hu' => 'kéregetni vagy koldulni;', 'ro' => 'cerșitul;']],
            ['fesztivalrend_acc_forbidden_li6', 'Részletek — „Tiltott tevékenységek” — felsorolás 6. pontja', 'szoveg', ['hu' => 'bármilyen olyan viselkedést tanúsítani, ami másokat vagy a programok lebonyolítását zavarja.', 'ro' => 'manifestarea oricărui comportament care deranjează alte persoane sau desfășurarea programelor.']],
            ['fesztivalrend_acc_forbidden_p2', 'Részletek — „Tiltott tevékenységek” — 2. bekezdés', 'szoveg', ['hu' => 'A szervezők fenntartják a jogot arra, hogy a fenti szabályokat megszegő személyeket vagy szervezeteket felszólítsák a tevékenység megszüntetésére, illetve indokolt esetben eltávolítsák a rendezvény területéről.', 'ro' => 'Organizatorii își rezervă dreptul de a soma persoanele sau organizațiile care încalcă regulile de mai sus să înceteze activitatea respectivă, respectiv, în cazuri justificate, de a le îndepărta de pe teritoriul evenimentului.']],
            ['fesztivalrend_acc_conduct_title', 'Részletek — „Magatartási szabályok” — lenyíló címe', 'sor', ['hu' => 'Magatartási szabályok', 'ro' => 'Reguli de comportament']],
            ['fesztivalrend_acc_conduct_p1', 'Részletek — „Magatartási szabályok” — 1. bekezdés', 'szoveg', ['hu' => 'A Vásárhelyi Forgatag célja, hogy minden látogató számára biztonságos, kulturált és kellemes környezetet biztosítson. A rendezvény területén kérjük, hogy:', 'ro' => 'Vâltoarea Mureșeană își propune să asigure un mediu sigur, civilizat și plăcut pentru toți vizitatorii. Pe teritoriul evenimentului vă rugăm:']],
            ['fesztivalrend_acc_conduct_li1', 'Részletek — „Magatartási szabályok” — felsorolás 1. pontja', 'szoveg', ['hu' => 'tartsd tiszteletben a többi látogatót, a fellépőket, az önkénteseket és a rendezvény munkatársait;', 'ro' => 'să respecți ceilalți vizitatori, artiștii, voluntarii și membrii echipei evenimentului;']],
            ['fesztivalrend_acc_conduct_li2', 'Részletek — „Magatartási szabályok” — felsorolás 2. pontja', 'szoveg', ['hu' => 'kövesd a szervezők, a biztonsági szolgálat és az önkéntesek útmutatásait;', 'ro' => 'să urmezi îndrumările organizatorilor, ale serviciului de securitate și ale voluntarilor;']],
            ['fesztivalrend_acc_conduct_li3', 'Részletek — „Magatartási szabályok” — felsorolás 3. pontja', 'szoveg', ['hu' => 'kerüld a mások nyugalmát zavaró vagy veszélyeztető magatartást;', 'ro' => 'să eviți un comportament care deranjează sau pune în pericol liniștea celorlalți;']],
            ['fesztivalrend_acc_conduct_li4', 'Részletek — „Magatartási szabályok” — felsorolás 4. pontja', 'szoveg', ['hu' => 'ügyelj a rendezvény helyszíneinek, berendezéseinek és eszközeinek megóvására.', 'ro' => 'să ai grijă de protejarea spațiilor, a instalațiilor și a echipamentelor evenimentului.']],
            ['fesztivalrend_acc_conduct_p2', 'Részletek — „Magatartási szabályok” — 2. bekezdés', 'szoveg', ['hu' => 'A szervezők fenntartják a jogot arra, hogy a rendezvény területéről eltávolítsák azokat a személyeket, akik agresszív magatartásukkal, rongálással, a közrend megzavarásával vagy a fesztiválrend megsértésével veszélyeztetik a rendezvény biztonságos lebonyolítását. Ugyanakkor a szervezők nem vállalnak felelősséget a személyekben, vagy anyagi értékekben esetlegesen keletkező károkért.', 'ro' => 'Organizatorii își rezervă dreptul de a îndepărta de pe teritoriul evenimentului persoanele care, prin comportament agresiv, distrugere de bunuri, tulburarea ordinii publice sau încălcarea regulamentului festivalului, pun în pericol desfășurarea în siguranță a evenimentului. Totodată, organizatorii nu își asumă răspunderea pentru eventualele daune produse persoanelor sau bunurilor materiale.']],
            ['fesztivalrend_acc_env_title', 'Részletek — „Környezetvédelmi irányelvek” — lenyíló címe', 'sor', ['hu' => 'Környezetvédelmi irányelvek', 'ro' => 'Politici de mediu']],
            ['fesztivalrend_acc_env_p1', 'Részletek — „Környezetvédelmi irányelvek” — 1. bekezdés', 'szoveg', ['hu' => 'A Vásárhelyi Forgatag Marosvásárhely történelmi belvárosában és műemléki környezetében valósul meg. Kérjük, hogy közösen vigyázzunk ezeknek a helyszíneknek az állapotára és tisztaságára. Ennek érdekében:', 'ro' => 'Vâltoarea Mureșeană se desfășoară în centrul istoric al Târgu Mureșului și în împrejurimile monumentelor sale. Te rugăm ca împreună să avem grijă de starea și curățenia acestor locuri. Pentru aceasta:']],
            ['fesztivalrend_acc_env_li1', 'Részletek — „Környezetvédelmi irányelvek” — felsorolás 1. pontja', 'szoveg', ['hu' => 'a hulladékot kizárólag a kijelölt gyűjtőedényekbe helyezd;', 'ro' => 'aruncă deșeurile exclusiv în recipientele de colectare desemnate;']],
            ['fesztivalrend_acc_env_li2', 'Részletek — „Környezetvédelmi irányelvek” — felsorolás 2. pontja', 'szoveg', ['hu' => 'óvd a zöldterületeket, növényzetet és történelmi épületeket;', 'ro' => 'protejează spațiile verzi, vegetația și clădirile istorice;']],
            ['fesztivalrend_acc_env_li3', 'Részletek — „Környezetvédelmi irányelvek” — felsorolás 3. pontja', 'szoveg', ['hu' => 'ne rongáld a rendezvény infrastruktúráját és berendezéseit;', 'ro' => 'nu deteriora infrastructura și echipamentele evenimentului;']],
            ['fesztivalrend_acc_env_li4', 'Részletek — „Környezetvédelmi irányelvek” — felsorolás 4. pontja', 'szoveg', ['hu' => 'lehetőség szerint használj újratölthető kulacsot, és csökkentsd az egyszer használatos műanyagok mennyiségét.', 'ro' => 'pe cât posibil, folosește o sticlă reutilizabilă și redu cantitatea de materiale plastice de unică folosință.']],
            ['fesztivalrend_acc_env_p2', 'Részletek — „Környezetvédelmi irányelvek” — 2. bekezdés', 'szoveg', ['hu' => 'Köszönjük, hogy hozzájárulsz ahhoz, hogy a Vásárhelyi Forgatag helyszínei a rendezvény után is ugyanolyan rendezettek és értékesek maradjanak, mint az érkezésedkor.', 'ro' => 'Îți mulțumim că, prin comportamentul tău, contribui la păstrarea locurilor Vâltorii Mureșene la fel de curate și valoroase după eveniment, așa cum le-ai găsit la sosire.']],
            ['fesztivalrend_acc_practical_title', 'Részletek — „Praktikus információk” — lenyíló címe', 'sor', ['hu' => 'Praktikus információk', 'ro' => 'Informații practice']],
            ['fesztivalrend_acc_practical_s1_title', 'Részletek — „Praktikus információk” — 1. alpont címe', 'sor', ['hu' => 'Megközelítés és parkolás', 'ro' => 'Acces și parcare']],
            ['fesztivalrend_acc_practical_s1_p1', 'Részletek — „Praktikus információk” — „Megközelítés és parkolás” — 1. bekezdés', 'szoveg', ['hu' => 'A Vásárhelyi Forgatag területére kizárólag a technikai személyzet (tűzoltóautó, mentőautó, rendőrségi járművek, áruszállító járművek) járművei, az előadókat és kellékeiket szállító, valamint a műszaki személyzet járművei léphetnek be.', 'ro' => 'Pe teritoriul Vâltorii Mureșene pot intra exclusiv vehiculele personalului tehnic (mașini de pompieri, ambulanțe, autovehicule ale poliției, vehicule de aprovizionare), precum și vehiculele care transportă artiștii și recuzita acestora, respectiv personalul tehnic.']],
            ['fesztivalrend_acc_practical_s1_p2', 'Részletek — „Praktikus információk” — „Megközelítés és parkolás” — 2. bekezdés', 'szoveg', ['hu' => 'A rendezvény ideje alatt egyes utcákban ideiglenes forgalomkorlátozások és útlezárások léphetnek életbe, ezért javasoljuk, hogy indulás előtt tájékozódj az aktuális közlekedési rendről.', 'ro' => 'Pe durata evenimentului, pe unele străzi pot fi introduse restricții temporare de circulație și închideri de drumuri, de aceea îți recomandăm să te informezi despre situația actuală a circulației înainte de a porni la drum.']],
            ['fesztivalrend_acc_practical_s1_p3', 'Részletek — „Praktikus információk” — „Megközelítés és parkolás” — 3. bekezdés', 'szoveg', ['hu' => 'Amennyiben lehetőséged van rá, válaszd a gyalogos, kerékpáros vagy tömegközlekedéssel történő érkezést. A kerékpárokat kérjük, kizárólag a kijelölt tárolókban helyezd el. Taxi igénybevétele esetén a belváros több pontján kijelölt taxiállomások állnak rendelkezésre.', 'ro' => 'Dacă ai posibilitatea, alege să vii pe jos, cu bicicleta sau cu mijloacele de transport în comun. Bicicletele te rugăm să le parchezi exclusiv în locurile special amenajate pentru acestea. Dacă alegi taxiul, în mai multe puncte din centrul orașului sunt amenajate stații de taxi.']],
            ['fesztivalrend_acc_practical_s2_title', 'Részletek — „Praktikus információk” — 2. alpont címe', 'sor', ['hu' => 'Fizetés', 'ro' => 'Plata']],
            ['fesztivalrend_acc_practical_s2_p1', 'Részletek — „Praktikus információk” — „Fizetés” — 1. bekezdés', 'szoveg', ['hu' => 'A Forgatag vendéglátó egységeinek többségében készpénzzel és bankkártyával egyaránt lehet fizetni. Az esetleges ettől eltérő fizetési lehetőségekről az adott árus vagy szolgáltató ad tájékoztatást.', 'ro' => 'În majoritatea unităților de alimentație publică din cadrul Vâltorii se poate plăti atât cash, cât și cu cardul bancar. Despre eventualele modalități de plată diferite te va informa comerciantul sau prestatorul de servicii respectiv.']],
            ['fesztivalrend_acc_practical_s2_p2', 'Részletek — „Praktikus információk” — „Fizetés” — 2. bekezdés', 'szoveg', ['hu' => 'Javasoljuk, hogy érkezés előtt gondoskodj a számodra megfelelő fizetési lehetőségről.', 'ro' => 'Îți recomandăm ca înainte de sosire să te asiguri că ai la îndemână modalitatea de plată care ți se potrivește.']],
            ['fesztivalrend_acc_practical_s3_title', 'Részletek — „Praktikus információk” — 3. alpont címe', 'sor', ['hu' => 'Étel és ital', 'ro' => 'Mâncare și băutură']],
            ['fesztivalrend_acc_practical_s3_p1', 'Részletek — „Praktikus információk” — „Étel és ital” — 1. bekezdés', 'szoveg', ['hu' => 'A Vásárhelyi Forgatag gasztronómiai kínálatában helyi és erdélyi ízek, street food ételek, kézműves termékek és különféle italok várják a látogatókat.', 'ro' => 'În oferta gastronomică a Vâltorii Mureșene îi așteaptă pe vizitatori gusturi locale și transilvănene, mâncăruri street food, produse meșteșugărești și băuturi de tot felul.']],
            ['fesztivalrend_acc_practical_s3_p2', 'Részletek — „Praktikus információk” — „Étel és ital” — 2. bekezdés', 'szoveg', ['hu' => 'Az egyes vendéglátóegységek saját nyitvatartási rend szerint működnek, amely eltérhet a rendezvény általános nyitvatartásától.', 'ro' => 'Fiecare unitate de alimentație publică funcționează după propriul program, care poate diferi de programul general al evenimentului.']],
            ['fesztivalrend_acc_practical_s4_title', 'Részletek — „Praktikus információk” — 4. alpont címe', 'sor', ['hu' => 'Mosdók', 'ro' => 'Toalete']],
            ['fesztivalrend_acc_practical_s4_p1', 'Részletek — „Praktikus információk” — „Mosdók” — 1. bekezdés', 'szoveg', ['hu' => 'Állandó mosdók a rendezvény több pontján is rendelkezésre állnak. Elhelyezkedésüket a fesztiváltérképen, valamint az információs pontokon található tájékoztató felületeken is feltüntetjük.', 'ro' => 'Toalete fixe sunt disponibile în mai multe puncte ale evenimentului. Amplasarea lor este indicată atât pe harta festivalului, cât și pe materialele informative de la punctele de informare.']],
            ['fesztivalrend_acc_practical_s4_p2', 'Részletek — „Praktikus információk” — „Mosdók” — 2. bekezdés', 'szoveg', ['hu' => 'Kérjük, segíts a közös terek tisztán tartásában, és rendeltetésszerűen használd a rendelkezésre álló létesítményeket.', 'ro' => 'Te rugăm să contribui la păstrarea curățeniei spațiilor comune și să folosești facilitățile puse la dispoziție în mod corespunzător.']],
            ['fesztivalrend_acc_practical_s5_title', 'Részletek — „Praktikus információk” — 5. alpont címe', 'sor', ['hu' => 'Családoknak', 'ro' => 'Pentru familii']],
            ['fesztivalrend_acc_practical_s5_p1', 'Részletek — „Praktikus információk” — „Családoknak” — 1. bekezdés', 'szoveg', ['hu' => 'A Vásárhelyi Forgatag kiemelten családbarát rendezvény, ahol minden korosztály találhat számára érdekes programot.', 'ro' => 'Vâltoarea Mureșeană este un eveniment deosebit de prietenos pentru familii, unde fiecare grupă de vârstă poate găsi un program pe placul ei.']],
            ['fesztivalrend_acc_practical_s5_p2', 'Részletek — „Praktikus információk” — „Családoknak” — 2. bekezdés', 'szoveg', ['hu' => 'A családokat gyermekprogramok, pihenőhelyek, pelenkázási lehetőségek és kényelmes közösségi terek várják. A kisebb gyermekeket a rendezvény teljes ideje alatt kísérd megfelelő felügyelettel.', 'ro' => 'Familiile sunt așteptate de programe pentru copii, locuri de odihnă, posibilități de schimbare a scutecelor și spații comunitare confortabile. Pe cei mici te rugăm să îi supraveghezi corespunzător pe toată durata evenimentului.']],
            ['fesztivalrend_acc_practical_s5_p3', 'Részletek — „Praktikus információk” — „Családoknak” — 3. bekezdés', 'szoveg', ['hu' => 'Ha gyermeked elveszne, kérjük, haladéktalanul fordulj az információs ponthoz vagy a legközelebbi szervezőhöz.', 'ro' => 'Dacă se întâmplă ca un copil să se rătăcească, te rugăm să te adresezi neîntârziat punctului de informare sau celui mai apropiat organizator.']],
            ['fesztivalrend_acc_practical_s6_title', 'Részletek — „Praktikus információk” — 6. alpont címe', 'sor', ['hu' => 'Akadálymentesség', 'ro' => 'Accesibilitate']],
            ['fesztivalrend_acc_practical_s6_p1', 'Részletek — „Praktikus információk” — „Akadálymentesség” — 1. bekezdés', 'szoveg', ['hu' => 'A rendezvény helyszíneinek jelentős része akadálymentesen megközelíthető. Amennyiben a rendezvény ideje alatt segítségre van szükséged, fordulj bizalommal a szervezőkhöz vagy az önkéntesekhez.', 'ro' => 'O parte semnificativă a locațiilor evenimentului este accesibilă persoanelor cu mobilitate redusă. Dacă ai nevoie de ajutor pe durata evenimentului, adresează-te cu încredere organizatorilor sau voluntarilor.']],
            ['fesztivalrend_acc_practical_s7_title', 'Részletek — „Praktikus információk” — 7. alpont címe', 'sor', ['hu' => 'Háziállatok', 'ro' => 'Animale de companie']],
            ['fesztivalrend_acc_practical_s7_p1', 'Részletek — „Praktikus információk” — „Háziállatok” — 1. bekezdés', 'szoveg', ['hu' => 'A szabadtéri helyszínekre a hatályos törvényeknek megfelelően kizárólag pórázon vezetett, jól szocializált háziállatok behozhatók (a törvény által előírt méretű és fajtájú kutyák esetében kötelező a szájkosár is). Gazdaként felelős vagy a kedvenced folyamatos felügyeletéért, valamint azért, hogy ne zavarja a rendezvény többi látogatóját.', 'ro' => 'În locațiile din aer liber, conform legislației în vigoare, pot fi aduse exclusiv animale de companie bine socializate, ținute în lesă (în cazul câinilor de talie și rasă prevăzute de lege, botnița este de asemenea obligatorie). Ca proprietar, ești responsabil de supravegherea permanentă a animalului tău și de a te asigura că acesta nu deranjează ceilalți vizitatori ai evenimentului.']],
            ['fesztivalrend_acc_practical_s7_p2', 'Részletek — „Praktikus információk” — „Háziállatok” — 2. bekezdés', 'szoveg', ['hu' => 'Kérjük, gondoskodj az utána történő takarításról, és ügyelj arra, hogy a nagyobb tömeg vagy a hangos programok a kedvenced számára is megfelelő környezetet jelentsenek.', 'ro' => 'Te rugăm să ai grijă de curățenie după animalul tău și să ții cont de faptul că mulțimea mai mare sau programele zgomotoase trebuie să reprezinte un mediu potrivit și pentru el.']],
            ['fesztivalrend_acc_practical_s7_p3', 'Részletek — „Praktikus információk” — „Háziállatok” — 3. bekezdés', 'szoveg', ['hu' => 'A zárt kulturális intézmények saját házirendje eltérhet, ezért egyes helyszínekre háziállatok nem vihetők be.', 'ro' => 'Regulamentul propriu al instituțiilor culturale cu spații închise poate diferi, astfel încât în anumite locații animalele de companie nu pot fi introduse.']],
            ['fesztivalrend_acc_health_title', 'Részletek — „Egészség és biztonság” — lenyíló címe', 'sor', ['hu' => 'Egészség és biztonság', 'ro' => 'Sănătate și siguranță']],
            ['fesztivalrend_acc_health_s1_title', 'Részletek — „Egészség és biztonság” — 1. alpont címe', 'sor', ['hu' => 'Elsősegély', 'ro' => 'Prim ajutor']],
            ['fesztivalrend_acc_health_s1_p1', 'Részletek — „Egészség és biztonság” — „Elsősegély” — 1. bekezdés', 'szoveg', ['hu' => 'A rendezvény teljes időtartama alatt egészségügyi szolgálat és elsősegélypont működik.', 'ro' => 'Pe toată durata evenimentului funcționează un serviciu medical și un punct de prim ajutor.']],
            ['fesztivalrend_acc_health_s1_p2', 'Részletek — „Egészség és biztonság” — „Elsősegély” — 2. bekezdés, a kiemelés előtti rész', 'szoveg', ['hu' => 'Ha rosszul érzed magad, vagy segítségre van szükséged, keresd a legközelebbi szervezőt, önkéntest, biztonsági munkatársat vagy elsősegély pontot. Sürgős esetben hívd a', 'ro' => 'Dacă te simți rău sau ai nevoie de ajutor, adresează-te celui mai apropiat organizator, voluntar, coleg din echipa de siguranță sau punctului de prim ajutor. În caz de urgență, sună la numărul de urgență'], 'csoport' => 'fesztivalrend_acc_health_s1_p2'],
            ['fesztivalrend_acc_health_s1_p2_kiemelt', 'Részletek — „Egészség és biztonság” — „Elsősegély” — 2. bekezdés, piros kiemelés (segélyhívó szám)', 'sor', ['hu' => '112-es', 'ro' => '112'], 'csoport' => 'fesztivalrend_acc_health_s1_p2'],
            ['fesztivalrend_acc_health_s1_p2_vege', 'Részletek — „Egészség és biztonság” — „Elsősegély” — 2. bekezdés, a kiemelés utáni rész', 'szoveg', ['hu' => 'segélyhívó számot.', 'ro' => '.'], 'csoport' => 'fesztivalrend_acc_health_s1_p2'],
            ['fesztivalrend_acc_health_s2_title', 'Részletek — „Egészség és biztonság” — 2. alpont címe', 'sor', ['hu' => 'Elveszett gyermek', 'ro' => 'Copil pierdut']],
            ['fesztivalrend_acc_health_s2_p1', 'Részletek — „Egészség és biztonság” — „Elveszett gyermek” — 1. bekezdés', 'szoveg', ['hu' => 'Amennyiben gyermeked szem elől téveszted, vagy felügyelet nélkül maradt gyermeket találsz, kérjük, haladéktalanul értesítsd a legközelebbi szervezőt, önkéntest, biztonsági munkatársat vagy keresd fel az információs pontot.', 'ro' => 'Dacă îți pierzi copilul din ochi sau găsești un copil rămas fără supraveghere, te rugăm să anunți neîntârziat cel mai apropiat organizator, voluntar, coleg din echipa de siguranță sau să te adresezi punctului de informare.']],
            ['fesztivalrend_acc_health_s2_p2', 'Részletek — „Egészség és biztonság” — „Elveszett gyermek” — 2. bekezdés', 'szoveg', ['hu' => 'Munkatársaink kiemelt figyelmet fordítanak arra, hogy az ilyen helyzeteket a lehető leggyorsabban és legbiztonságosabban kezeljék.', 'ro' => 'Colegii noștri acordă o atenție deosebită gestionării unor astfel de situații cât mai rapid și în cele mai sigure condiții posibile.']],
            ['fesztivalrend_acc_health_s3_title', 'Részletek — „Egészség és biztonság” — 3. alpont címe', 'sor', ['hu' => 'Talált tárgyak', 'ro' => 'Obiecte găsite']],
            ['fesztivalrend_acc_health_s3_p1', 'Részletek — „Egészség és biztonság” — „Talált tárgyak” — 1. bekezdés', 'szoveg', ['hu' => 'A rendezvény területén talált tárgyakat az információs ponton gyűjtjük.', 'ro' => 'Obiectele găsite pe teritoriul evenimentului sunt adunate la punctul de informare.']],
            ['fesztivalrend_acc_health_s3_p2', 'Részletek — „Egészség és biztonság” — „Talált tárgyak” — 2. bekezdés', 'szoveg', ['hu' => 'Ha elvesztettél valamit, elsőként itt érdeklődj, vagy fordulj a szervezőkhöz, önkéntesekhez segítségért.', 'ro' => 'Dacă ai pierdut ceva, informează-te mai întâi aici sau adresează-te organizatorilor și voluntarilor pentru ajutor.']],
            ['fesztivalrend_acc_media_title', 'Részletek — „Fotózás és média” — lenyíló címe', 'sor', ['hu' => 'Fotózás és média', 'ro' => 'Fotografiere și media']],
            ['fesztivalrend_acc_media_p1', 'Részletek — „Fotózás és média” — 1. bekezdés', 'szoveg', ['hu' => 'A Vásárhelyi Forgatag nyilvános rendezvény, ezért a programok során a szervezők és hivatalos partnereik fotó- és videófelvételeket készíthetnek, amelyeket kommunikációs, promóciós és archiválási célokra felhasználhatnak.', 'ro' => 'Vâltoarea Mureșeană este un eveniment public, prin urmare, pe durata programelor, organizatorii și partenerii lor oficiali pot realiza fotografii și înregistrări video, pe care le pot folosi în scopuri de comunicare, promovare și arhivare.']],
            ['fesztivalrend_acc_media_p2', 'Részletek — „Fotózás és média” — 2. bekezdés', 'szoveg', ['hu' => 'Professzionális fotózás, videózás, élő közvetítés vagy drónhasználat kizárólag a szervezők előzetes írásos engedélyével végezhető.', 'ro' => 'Fotografierea și filmarea profesională, transmisiunile live sau utilizarea dronelor pot fi realizate exclusiv cu acordul scris prealabil al organizatorilor.']],
            ['fesztivalrend_acc_weather_title', 'Részletek — „Rossz idő esetén” — lenyíló címe', 'sor', ['hu' => 'Rossz idő esetén', 'ro' => 'În caz de vreme nefavorabilă']],
            ['fesztivalrend_acc_weather_p1', 'Részletek — „Rossz idő esetén” — 1. bekezdés', 'szoveg', ['hu' => 'A szabadtéri programok lebonyolítása az időjárási körülményektől függhet. Kedvezőtlen időjárás esetén egyes programok helyszíne vagy időpontja módosulhat, illetve szükség esetén elmaradhat.', 'ro' => 'Desfășurarea programelor din aer liber poate depinde de condițiile meteorologice. În cazul unei vremi nefavorabile, locația sau ora anumitor programe se poate modifica, respectiv, dacă este necesar, acestea pot fi anulate.']],
            ['fesztivalrend_acc_weather_p2', 'Részletek — „Rossz idő esetén” — 2. bekezdés', 'szoveg', ['hu' => 'Minden programváltozásról a lehető leghamarabb tájékoztatást adunk a Vásárhelyi Forgatag hivatalos weboldalán, valamint közösségi média felületein. Kérjük, indulás előtt kövesd ezeket a csatornákat a legfrissebb információkért.', 'ro' => 'Despre orice modificare de program vă informăm cât mai curând posibil pe pagina web oficială a Vâltorii Mureșene, precum și pe platformele de social media. Te rugăm ca înainte de a porni la drum să urmărești aceste canale pentru cele mai recente informații.']],
            ['fesztivalrend_thanks_title', 'Köszönjük — cím', 'sor', ['hu' => 'Köszönjük az együttműködést!', 'ro' => 'Vă mulțumim pentru înțelegere!']],
            ['fesztivalrend_thanks_p', 'Köszönjük — bekezdés', 'szoveg', ['hu' => 'A Vásárhelyi Forgatagot szervezők, partnerek, önkéntesek és fellépők közös munkája teszi lehetővé. Köszönjük, hogy Te is hozzájárulsz ahhoz, hogy ez a néhány nap mindenki számára biztonságos, kulturált és emlékezetes legyen.', 'ro' => 'Vâltoarea Mureșeană este posibilă datorită muncii comune a organizatorilor, partenerilor, voluntarilor și artiștilor care urcă pe scenă. Îți mulțumim că și tu contribui la a face din aceste câteva zile un eveniment sigur, civilizat și memorabil pentru toată lumea.']],
            ['fesztivalrend_thanks_brush_1', 'Köszönjük — ecsetírás, 1. sor', 'sor', ['hu' => 'Találkozzunk', 'ro' => 'Ne întâlnim'], 'csoport' => 'zaro'],
            ['fesztivalrend_thanks_brush_2', 'Köszönjük — ecsetírás, 2. sor (a fesztivál dátuma)', 'sor', ['hu' => 'augusztus 25–29. között', 'ro' => 'în perioada 25–29 august'], 'csoport' => 'zaro'],
            ['fesztivalrend_thanks_brush_glow', 'Köszönjük — ecsetírás, 3. sor (fénylő, a fesztivál sorszáma)', 'sor', ['hu' => 'a 13. Vásárhelyi Forgatagon!', 'ro' => 'la a 13-a ediție a Vâltorii Mureșene!'], 'csoport' => 'zaro'],
        ],

        // A GALÉRIA (PROMPT-17, 2. rész) — a 2026-os GalleryView: a cím és a bevezető 2026-ban is szerkeszthető volt (`data-hs`), az
        // üres állapot beégetett. A galériák maguk: Tartalom → Galériák. A 2026-os román bevezetőben a hibás „Vâltoarei” alak állt —
        // javítva „Vâltorii”-ra (CLAUDE.md 1.).
        'galeria' => [
            ['gallery_title', 'Cím (ecsetírás)', 'sor', ['hu' => 'Galéria', 'ro' => 'Galerie']],
            ['gallery_intro', 'Bevezető', 'szoveg', ['hu' => 'A legjobb pillanatok, a legbulisabb esték és legtartalmasabb forgatagos hangulatok. Kattints végig az elmúlt évek fotóin!', 'ro' => 'Cele mai frumoase momente și cele mai bune seri ale edițiilor trecute — dă click și retrăiește atmosfera Vâltorii.']],
            ['gallery_empty', 'Ha még nincs galéria', 'sor', ['hu' => 'Még nincs feltöltött galéria.', 'ro' => 'Încă nu am adăugat galerii.']],
        ],

        // A VIDEÓ ARCHÍVUM (PROMPT-17, 2. rész) — a 2026-os VideoArchiveView kulcsai a régi nevükön (a 2026-ban beírt román értékek
        // megmaradnak). A cím két fele egymás mellé kerül — a szóköz a mező része (2026-ban is: „Videó ” + „archívum”, de „After” +
        // „movie-k”). A videók: a lap videós blokkjai (a sorrend adja a sávot — App\Support\MediaLapok::SAVOK).
        'videoarchivum' => [
            ['videos_hero_title', 'Nyitócím — eleje (a végén szóköz, ha kell)', 'sor', ['hu' => 'Videó ', 'ro' => 'Arhivă '], 'csoport' => 'hero'],
            ['videos_hero_title_accent', 'Nyitócím — kiemelt rész (ecsetírás)', 'sor', ['hu' => 'archívum', 'ro' => 'video'], 'csoport' => 'hero'],
            ['videos_aftermovie_title', '1. sáv (az 1. videós blokk, világos) — cím eleje', 'sor', ['hu' => 'After', 'ro' => 'After'], 'csoport' => 'sav1'],
            ['videos_aftermovie_title_accent', '1. sáv — cím kiemelt része', 'sor', ['hu' => 'movie-k', 'ro' => 'movie-uri'], 'csoport' => 'sav1'],
            ['videos_szinejava_title', '2. sáv (a 2. videós blokk, sötét, álló videók) — cím eleje', 'sor', ['hu' => 'Vásárhely ', 'ro' => 'Târgu Mureș '], 'csoport' => 'sav2'],
            ['videos_szinejava_title_accent', '2. sáv — cím kiemelt része', 'sor', ['hu' => 'színe-java', 'ro' => 'la superlativ'], 'csoport' => 'sav2'],
            ['videos_mestersegem_title', '3. sáv (a 3. videós blokk, narancs) — cím eleje', 'sor', ['hu' => 'Mesterségem: ', 'ro' => 'Meseria mea: '], 'csoport' => 'sav3'],
            ['videos_mestersegem_title_accent', '3. sáv — cím kiemelt része', 'sor', ['hu' => 'vásárhelyi', 'ro' => 'târgumureșean'], 'csoport' => 'sav3'],
        ],

        // A TÁMOGATÓK (PROMPT-17, 2. rész) — a 2026-os SponsorsView: az öt `data-hs` kulcs a régi nevén; a „Köszönjük!” 2026-ban
        // beégetett volt (most kulcs). A támogatók: Tartalom → Támogatók (a legutóbbi év); a szint neve a szinttől (román név, ha van).
        'tamogatok' => [
            ['sponsors_title_a', 'Cím — eleje (a végén szóköz)', 'sor', ['hu' => 'Akik ', 'ro' => 'Cei care '], 'csoport' => 'cim'],
            ['sponsors_title_b', 'Cím — kiemelt rész', 'sor', ['hu' => 'velünk', 'ro' => 'ne sunt'], 'csoport' => 'cim'],
            ['sponsors_title_c', 'Cím — vége (az elején szóköz)', 'sor', ['hu' => ' tartanak', 'ro' => ' alături'], 'csoport' => 'cim'],
            ['sponsors_lead', 'Bevezető', 'szoveg', ['hu' => 'A Vásárhelyi Forgatag tizenhárom éve azért lehet minden augusztusban a város közös ünnepe, mert vannak, akik hisznek benne. Támogatóink és partnereink nélkül nem lenne se nagyszínpad, se kézműves vásár, se ingyenes gyerekprogram.', 'ro' => 'Vâltoarea Mureșeană poate fi de treisprezece ani sărbătoarea comună a orașului în fiecare august pentru că există cei care cred în ea. Fără susținătorii și partenerii noștri nu ar exista nici scena mare, nici târgul de meșteșugari, nici programele gratuite pentru copii.']],
            ['sponsors_thanks_strong', 'Köszönet — kiemelt eleje', 'sor', ['hu' => 'Köszönjük!', 'ro' => 'Mulțumim!'], 'csoport' => 'koszonet'],
            ['sponsors_thanks', 'Köszönet — folytatása (az elején szóköz)', 'szoveg', ['hu' => ' Minden támogatás egy programot, egy fellépőt vagy egy gyerekfoglalkozást jelent a Forgatagon.', 'ro' => ' Fiecare sprijin înseamnă un program, un artist sau un atelier pentru copii la Vâltoare.'], 'csoport' => 'koszonet'],
        ],

        // A KÉZMŰVES VÁSÁR (PROMPT-17) — a 2026-os CraftspeopleView: az öt 2026-ban is szerkeszthető kulcs (`data-hs`) a régi
        // nevén, az alapszöveg a 2026-os HU/RO szöveg; a többi látható felirat 2026-ban beégetett volt (most kulcs). A kézművesek
        // maguk: Tartalom → Kézművesek. A csak képernyőolvasónak szóló feliratok (lapozó, bezárás): lang/*/publikus.php `cv_*`.
        'kezmuvesek' => [
            ['craft_hero_title', 'Nyitócím — eleje', 'sor', ['hu' => 'Kézműves', 'ro' => 'Târg de'], 'csoport' => 'hero'],
            ['craft_hero_title_accent', 'Nyitócím — kiemelt rész (ecsetírás)', 'sor', ['hu' => 'vásár', 'ro' => 'meșteșugari'], 'csoport' => 'hero'],
            ['craft_hero_p1', 'Bevezető — 1. bekezdés', 'szoveg', ['hu' => 'Vannak helyek, ahová nemcsak a vásárfiáért térünk vissza, hanem a mögöttük rejlő történetekért és az emberekért is. A Forgatag kézműves vására ilyen: a szebbnél szebb portékák és finomságok között régi ismerősökkel találkozhatunk, új alkotókat fedezhetünk fel, és minden standnál egy-egy történet vár.', 'ro' => 'Există locuri în care ne întoarcem nu doar pentru târgul în sine, ci și pentru poveștile și oamenii din spatele lui. Târgul de meșteșugari al Vâltorii este așa: între produsele și bunătățile una mai frumoasă decât alta ne întâlnim cu cunoscuți vechi, descoperim creatori noi, iar la fiecare stand ne așteaptă câte o poveste.']],
            ['craft_hero_p2', 'Bevezető — 2. bekezdés', 'szoveg', ['hu' => 'Idén is három napon át válogathattok a Vársétányon, ahol térségünk kézművesei és termelői hozzák el legszebb alkotásaikat és ínyencségeiket.', 'ro' => 'Și în acest an puteți alege timp de trei zile pe Promenada Cetății, unde meșteșugarii și producătorii regiunii noastre își aduc cele mai frumoase creații și bunătăți.']],
            ['craft_list_count', 'Lista — számláló a lapozó mellett ({from}, {to}, {total} helyére a számok kerülnek)', 'sor', ['hu' => '{from}–{to}. a {total} kézművesből', 'ro' => '{from}–{to}. din {total} de meșteșugari']],
            ['craft_list_more', 'Lista — „Bővebben” a kártyán', 'sor', ['hu' => 'Bővebben', 'ro' => 'Mai mult']],
            ['craft_list_note', 'Lista — megjegyzés alatta', 'sor', ['hu' => 'Kattints egy kézművesre a teljes bemutatkozásért.', 'ro' => 'Dă clic pe un meșteșugar pentru prezentarea completă.']],
            ['craft_list_empty', 'Lista — ha még nincs kézműves', 'sor', ['hu' => 'Még nincsenek felvett kézművesek.', 'ro' => 'Încă nu am adăugat meșteșugari.']],
        ],
        // A hírlista fejléce és üres állapota (2026: a NewsListView-ba és az i18n-be égetve).
        'hirek' => [
            ['news_title', 'Lista — cím', 'sor', ['hu' => 'Ami a forgatag körül', 'ro' => 'Ce se întâmplă în jurul'], 'csoport' => 'cim'],
            ['news_title_accent', 'Lista — cím kiemelt része', 'sor', ['hu' => 'történik', 'ro' => 'festivalului'], 'csoport' => 'cim'],
            ['news_empty', 'Lista — szöveg, ha még nincs hír', 'szoveg', ['hu' => 'Egyelőre nincs közzétett hír. Nézz vissza hamarosan!', 'ro' => 'Deocamdată nu există știri publicate. Revino în curând!']],
        ],
        // A programkereső (/programok), a napi nézet (/nap/<dátum>) és a felugró ablak szövegei. Ami 2026-ban
        // szerkeszthető volt (`data-hs="programs_*"`), az itt is az, ugyanazzal a kulccsal és alapszöveggel; a többi felületi
        // felirat (csoportcímek, jelzések) a lang/*/publikus.php-ben, mint 2026-ban az i18n-ben.
        'programok' => [
            ['programs_title_a', 'Főcím (képernyőolvasónak) — 1. rész', 'sor', ['hu' => 'A fesztivál ', 'ro' => 'Programul '], 'csoport' => 'cim'],
            ['programs_title_b', 'Főcím (képernyőolvasónak) — 2. rész', 'sor', ['hu' => 'programja', 'ro' => 'festivalului'], 'csoport' => 'cim'],
            ['programs_filter_title', 'Szűrő — „Szűrés” felirat', 'sor', ['hu' => 'Szűrés', 'ro' => 'Filtrare']],
            ['programs_apply', 'Szűrő — „Alkalmaz” gomb (mobil)', 'sor', ['hu' => 'Alkalmaz', 'ro' => 'Aplică']],
            ['programs_word', 'Szűrő — „program” szó a gombon', 'sor', ['hu' => 'program', 'ro' => 'programe']],
            ['programs_now_1', '„Most zajlik” gomb — 1. szó', 'sor', ['hu' => 'Most', 'ro' => 'Acum'], 'csoport' => 'most'],
            ['programs_now_2', '„Most zajlik” gomb — 2. szó', 'sor', ['hu' => 'zajlik', 'ro' => 'are loc'], 'csoport' => 'most'],
            ['programs_filter_search_ph', 'Kereső — helyőrző szöveg', 'sor', ['hu' => 'Fellépő, program, kulcsszó…', 'ro' => 'Artist, program, cuvânt-cheie…']],
            ['programs_filter_daypart', 'Szűrő — „Napszak” csoport', 'sor', ['hu' => 'Napszak', 'ro' => 'Perioada zilei']],
            ['programs_daypart_morning', 'Napszak — délelőtt', 'sor', ['hu' => 'Délelőtt', 'ro' => 'Dimineață']],
            ['programs_daypart_afternoon', 'Napszak — délután', 'sor', ['hu' => 'Délután', 'ro' => 'După-amiază']],
            ['programs_daypart_evening', 'Napszak — este', 'sor', ['hu' => 'Este', 'ro' => 'Seara']],
            ['programs_filter_venue', 'Szűrő — „Helyszín” csoport', 'sor', ['hu' => 'Helyszín', 'ro' => 'Locație']],
            ['programs_filter_venue_ph', 'Szűrő — helyszín keresése (helyőrző)', 'sor', ['hu' => 'Helyszín keresése…', 'ro' => 'Caută locație…']],
            ['programs_filter_category', 'Szűrő — „Kategória” csoport', 'sor', ['hu' => 'Kategória', 'ro' => 'Categorie']],
            ['programs_filter_category_ph', 'Szűrő — kategória keresése (helyőrző)', 'sor', ['hu' => 'Kategória keresése…', 'ro' => 'Caută categorie…']],
            ['programs_highlight_title_a', 'Kiemelt sáv — cím', 'sor', ['hu' => 'Kiemelt ', 'ro' => 'Programe '], 'csoport' => 'kiemelt'],
            ['programs_highlight_title_b', 'Kiemelt sáv — cím kiemelt része', 'sor', ['hu' => 'programok', 'ro' => 'recomandate'], 'csoport' => 'kiemelt'],
            ['programs_modal_organizer', 'Felugró ablak — „Szervező”', 'sor', ['hu' => 'Szervező', 'ro' => 'Organizator']],
            ['programs_modal_guests', 'Felugró ablak — „Közreműködők”', 'sor', ['hu' => 'Közreműködők', 'ro' => 'Colaboratori']],
            ['programs_modal_mt', 'Felugró ablak — gépi fordítás jelzése (csak románul látszik)', 'sor', ['hu' => '🌐 Automatikus fordítás – emberi átnézés folyamatban.', 'ro' => '🌐 Traducere automată – în curs de verificare umană.']],
            ['programs_modal_share', 'Felugró ablak — „Megosztás” gomb', 'sor', ['hu' => 'Megosztás', 'ro' => 'Distribuie']],
            ['programs_modal_map_cta', 'Felugró ablak — „Mutasd a térképen” gomb', 'sor', ['hu' => 'Mutasd a térképen', 'ro' => 'Arată pe hartă']],
            ['programs_modal_map_cta_short', 'Felugró ablak — „Térkép” (mobilon)', 'sor', ['hu' => 'Térkép', 'ro' => 'Hartă']],
            ['programs_modal_ics', 'Felugró ablak — „Naptárba” gomb', 'sor', ['hu' => 'Naptárba', 'ro' => 'În calendar']],
            ['programs_modal_ics_short', 'Felugró ablak — „Naptár” (mobilon)', 'sor', ['hu' => 'Naptár', 'ro' => 'Calendar']],
            ['programs_modal_save', 'Felugró ablak — „Mentés” gomb', 'sor', ['hu' => 'Mentés', 'ro' => 'Salvează']],
            ['programs_title_short', 'Beúszó sáv — a lap neve', 'sor', ['hu' => 'Programkereső', 'ro' => 'Căutare în program']],
            ['programs_map_btn', 'Mobil — „Térkép” gomb', 'sor', ['hu' => 'Térkép', 'ro' => 'Hartă']],
            ['programs_empty_title', 'Nincs találat — cím', 'sor', ['hu' => 'Erre a kombinációra nincs program', 'ro' => 'Nu există program pentru această combinație']],
            ['programs_empty_hint', 'Nincs találat — tanács', 'szoveg', ['hu' => 'Próbálj másik napot, vagy törölj néhány szűrőt.', 'ro' => 'Încearcă altă zi sau șterge câteva filtre.']],
            ['programs_noprog_title', 'Még nincs közzétett program — cím', 'sor', ['hu' => 'A program készül', 'ro' => 'Programul se pregătește']],
            ['programs_noprog_text', 'Még nincs közzétett program — szöveg', 'szoveg', ['hu' => 'A beküldött programok még jóváhagyás alatt vannak. Nézz vissza hamarosan — itt lesz a program, szűrhető formában.', 'ro' => 'Programele sunt încă în curs de aprobare. Reveniți în curând — aici va apărea programul, cu filtrare.']],
            ['programs_now_empty_title', '„Most zajlik”, de nincs program — cím', 'sor', ['hu' => 'Most nem zajlik program', 'ro' => 'Acum nu se desfășoară niciun program']],
            ['programs_now_before', '„Most zajlik” a fesztivál ELŐTT — szöveg (a dátumot a rendszer írja elé)', 'szoveg', ['hu' => 'Válassz napot a fenti sávból, és tervezz előre!', 'ro' => 'Alege o zi din bara de sus și plănuiește din timp!']],
            ['programs_now_after', '„Most zajlik” a fesztivál UTÁN — szöveg', 'szoveg', ['hu' => 'A fesztivál véget ért, ezért most nem zajlik program. A napokra kattintva a program továbbra is megnézhető.', 'ro' => 'Festivalul s-a încheiat, așa că acum nu se desfășoară niciun program. Programul rămâne disponibil pe zile.']],
        ],
    ];

    /**
     * Rendszerlapok, amelyeket NEM a 2026-os behozatal hoz létre (2026-ban nem volt lap-rekordjuk), hanem a szinkron
     * — a behozatal után fut, ezért a behozatal „csak üres táblába” szabályát nem zavarja. Kitöltött lapot nem módosít.
     */
    private const UJ_RENDSZERLAPOK = [
        'programok' => [
            'title_hu' => 'Programkereső', 'title_ro' => 'Căutare în program',
            'seo_description_hu' => 'A Vásárhelyi Forgatag programja: szűrés nap, helyszín és kategória szerint, kereséssel.',
            'seo_description_ro' => 'Programul Festivalului Vâltoarea Mureșeană: filtrare după zi, locație și categorie, cu căutare.',
        ],
    ];

    public static function van(string $slug): bool
    {
        return isset(self::SABLONOK[$slug]);
    }

    /**
     * Egy sablon mezői, kulccsal.
     *
     * @return array<string, array{kulcs: string, cimke: string, tipus: string, hu: ?string, ro: ?string, csoport: ?string, fajl: ?string}>
     */
    public static function mezok(string $slug): array
    {
        if (! self::van($slug)) {
            throw new RuntimeException("A(z) „{$slug}” laphoz nincs sablon-leírás (App\\Support\\LapSablon).");
        }

        $ki = [];
        foreach (self::SABLONOK[$slug] as $m) {
            $alap = $m[3] ?? null;
            $ki[$m[0]] = [
                'kulcs' => $m[0],
                'cimke' => $m[1],
                'tipus' => $m[2],
                'hu' => is_array($alap) ? $alap['hu'] : $alap,
                'ro' => is_array($alap) ? ($alap['ro'] ?? null) : null,
                'csoport' => $m['csoport'] ?? null,
                'fajl' => $m['fajl'] ?? null,
            ];
        }

        return $ki;
    }

    /** Egy kulcs leírása bármelyik sablonból (az admin feliratához). */
    public static function mezo(?string $kulcs): ?array
    {
        foreach (array_keys(self::SABLONOK) as $slug) {
            if ($kulcs !== null && isset(self::mezok($slug)[$kulcs])) {
                return self::mezok($slug)[$kulcs];
            }
        }

        return null;
    }

    /**
     * Az adatbázis igazítása a leíráshoz (a telepítés futtatja; ismételhető). Laponként:
     *  · hiányzó kulcs → létrejön az alapszöveggel; meglévő, de üres magyar (vagy román, ha van román
     *    alapérték) → kitöltődik — így az admin a ténylegesen kint lévő szöveget mutatja;
     *  · üres kép, amihez van kezdőfájl → a fájl a médiatárba („Aloldalak”), és a kulcs rá mutat;
     *  · a leírásban már nem szereplő kulcs → törlődik (holt adat nem marad);
     *  · a sorrend a leírás sorrendje.
     * Kitöltött értékhez SOHA nem nyúl.
     *
     * @return list<string> emberi nyelvű napló
     */
    public static function szinkron(): array
    {
        $naplo = [];
        foreach (array_keys(self::SABLONOK) as $slug) {
            $lap = Page::query()->where('slug', $slug)->first();
            if ($lap === null && isset(self::UJ_RENDSZERLAPOK[$slug])) {
                $lap = new Page(self::UJ_RENDSZERLAPOK[$slug] + ['slug' => $slug, 'status' => ContentStatus::Published]);
                $lap->is_system = true;
                $lap->save();
                $naplo[] = "{$slug}: a rendszerlap létrehozva.";
            }
            if ($lap === null) {
                $naplo[] = "{$slug}: a lap nincs az adatbázisban — kihagyva (a 2026-os tartalom behozatala hozza létre).";

                continue;
            }
            $naplo[] = $slug . ': ' . DB::transaction(fn () => self::lapSzinkron($lap));
        }

        return $naplo;
    }

    private static function lapSzinkron(Page $lap): string
    {
        $mezok = self::mezok($lap->slug);
        $uj = $kitoltve = $kep = 0;
        $sorrend = 0;
        foreach ($mezok as $kulcs => $m) {
            $sor = PageText::query()->firstOrNew(['page_id' => $lap->id, 'key' => $kulcs]);
            $letezett = $sor->exists;
            $sor->sort = ++$sorrend;
            if ($m['tipus'] === 'kep') {
                if ($sor->media_id === null && $m['fajl'] !== null) {
                    $sor->media_id = self::kezdoKep($m['fajl'], $m['cimke'])->id;
                    $kep++;
                }
            } else {
                foreach (['hu', 'ro'] as $ny) {
                    if ($m[$ny] !== null && blank($sor->{"value_{$ny}"})) {
                        $sor->{"value_{$ny}"} = $m[$ny];
                        $letezett && $kitoltve++;
                    }
                }
            }
            $letezett || $uj++;
            $sor->save();
        }
        $torolt = PageText::query()->where('page_id', $lap->id)->whereNotIn('key', array_keys($mezok))->pluck('key')->all();
        PageText::query()->where('page_id', $lap->id)->whereIn('key', $torolt)->delete();

        return "{$uj} új kulcs, {$kitoltve} üres mező kitöltve, {$kep} kezdőkép a médiatárba"
            . ($torolt ? ', törölt (a sablonban már nincs): ' . implode(', ', $torolt) : '') . '.';
    }

    /** Egy kezdőkép (seed/lapok/…) a médiatárba, ha még nincs bent (a fájl lenyomata alapján). */
    private static function kezdoKep(string $fajl, string $cimke): Media
    {
        $forras = base_path('seed/lapok/' . $fajl);
        if (! is_file($forras)) {
            throw new RuntimeException("Hiányzik a kezdőkép: seed/lapok/{$fajl}");
        }
        $jel = 'seed/lapok/' . $fajl . ' sha256:' . hash_file('sha256', $forras);
        $meglevo = Media::query()->where('legacy_note', $jel)->first();
        if ($meglevo !== null) {
            return $meglevo;
        }

        $ext = strtolower(pathinfo($fajl, PATHINFO_EXTENSION));
        $nev = (string) Str::uuid();
        $tar = Storage::disk('mediatar');
        $tar->put("{$nev}.{$ext}", (string) file_get_contents($forras));
        [$w, $h] = @getimagesize($forras) ?: [null, null];
        $media = Media::create([
            'disk' => 'mediatar',
            'directory' => null,
            'visibility' => 'public',
            'name' => $nev,
            'path' => "{$nev}.{$ext}",
            'width' => $w,
            'height' => $h,
            'size' => filesize($forras),
            'type' => mime_content_type($forras) ?: 'image/' . $ext,
            'ext' => $ext,
            'title' => $cimke,
            'alt' => null,
            'origin' => MediaOrigin::Import2026,
            'license' => MediaLicense::Sajat,
            'legacy_note' => $jel,
        ]);
        $media->addToCollection(Gyujtemeny::Lapok);

        return $media;
    }
}
