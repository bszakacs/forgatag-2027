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
            ['programs_empty_title', 'Nincs találat — cím', 'sor', ['hu' => 'Erre a kombinációra nincs program', 'ro' => 'Nu există program pentru această combinație']],
            ['programs_empty_hint', 'Nincs találat — tanács', 'szoveg', ['hu' => 'Próbálj másik napot, vagy törölj néhány szűrőt.', 'ro' => 'Încearcă altă zi sau șterge câteva filtre.']],
            ['programs_noprog_title', 'Még nincs közzétett program — cím', 'sor', ['hu' => 'A program készül', 'ro' => 'Programul se pregătește']],
            ['programs_noprog_text', 'Még nincs közzétett program — szöveg', 'szoveg', ['hu' => 'A beküldött programok még jóváhagyás alatt vannak. Nézz vissza hamarosan — itt lesz a program, szűrhető formában.', 'ro' => 'Programele sunt încă în curs de aprobare. Reveniți în curând — aici va apărea programul, cu filtrare.']],
            ['programs_now_empty_title', '„Most zajlik”, de nincs program — cím', 'sor', ['hu' => 'Most nem zajlik program', 'ro' => 'Acum nu se desfășoară niciun program']],
            ['programs_now_before', '„Most zajlik” a fesztivál ELŐTT — szöveg (a dátumot a rendszer írja elé)', 'szoveg', ['hu' => 'Válassz napot a fenti sávból, és tervezz előre!', 'ro' => 'Alege o zi din bara de sus și plănuiește din timp!']],
            ['programs_now_after', '„Most zajlik” a fesztivál UTÁN — szöveg', 'szoveg', ['hu' => 'A fesztivál véget ért, ezért most nem zajlik program. A napokra kattintva a program továbbra is megnézhető.', 'ro' => 'Festivalul s-a încheiat, așa că acum nu se desfășoară niciun program. Programul rămâne disponibil pe zile.']],
            ['programs_day_title', 'Napi nézet — cím (utána a nap neve)', 'sor', ['hu' => 'A nap programja', 'ro' => 'Programul zilei']],
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
