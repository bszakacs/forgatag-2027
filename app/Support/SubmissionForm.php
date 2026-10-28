<?php

namespace App\Support;

use App\Models\Category;

/**
 * A programbeküldő űrlap SZERKEZETE (kategóriánkénti, adaptív) — a 2026-os submit-api/form-config.mjs
 * átvétele, változatlan mezőkkel, feliratokkal és szabályokkal. Ugyanez a leírás hajtja az űrlap
 * megjelenítését ÉS a szerver-oldali validációt (SubmissionValidator), tehát nem csúszhatnak el.
 *
 * Ez a rendszer szerkezete, ezért él kódban (CLAUDE.md 2/b) — nem admin-felület.
 * Ismeretlen / új kategória → csak az alapmezők (szándékos, hibatűrő viselkedés).
 *
 * ⚠ A kulcs a kategória SLUGJA. Ha a kategóriát törlik vagy átnevezik, a hozzá írt extra mezők némán
 * elvesznének (5. adósság: így maradt hatástalan a 2026-ban megszűnt „kerekasztal-beszelgetesek”).
 * Ezért self::unknownSlugs() felderíti, és a jelzés az admin nyitólapján pirosan látszik
 * (App\Filament\Widgets\Figyelmeztetesek); a teszt a seed/categories.csv-vel is összeveti.
 *
 * Extra mező alakja: { key, label, type, required, help?, requiredIf? }
 *   type: 'text' | 'longtext' | 'number' | 'url' | 'checkbox'
 */
class SubmissionForm
{
    /** Minden kategóriánál kötelező alapmezők (implicit). */
    public const BASE_FIELDS = [
        'title', 'start_time', 'end_time', 'schedule_note', 'recurrence_days',
        'venue', 'description', 'entry_type', 'ticket_info', 'photo',
    ];

    /** Fotó-kritériumok. */
    public const PHOTO_MIN_WIDTH = 1200;

    public const PHOTO_MIN_HEIGHT = 800;

    public const PHOTO_MAX_BYTES = 10 * 1024 * 1024;

    public const PHOTO_FORMATS = ['image/jpeg', 'image/png', 'image/webp'];

    /** Szöveges mezők hosszkorlátai. */
    public const DESCRIPTION_MIN = 60;

    public const DESCRIPTION_MAX = 800;

    /** @return array<string, list<array<string, mixed>>> kategória-slug → extra mezők */
    public static function extraFieldsBySlug(): array
    {
        // Újrahasznosított extra-mező csoportok
        $speakerBios = ['key' => 'speaker_bios', 'label' => 'Előadók bemutatkozója', 'type' => 'longtext', 'required' => true, 'help' => 'Röviden az előadókról.'];
        $performer = ['key' => 'performer_name', 'label' => 'Fellépő / zenekar neve', 'type' => 'text', 'required' => true];

        // „Műhely/foglalkozás" jellegű extrák (opcionálisak, nem minden programpontnál)
        $workshop = [
            ['key' => 'age_group', 'label' => 'Korosztály', 'type' => 'text', 'required' => false, 'help' => 'Pl. 6–10 év, minden korosztály.'],
            ['key' => 'capacity', 'label' => 'Létszámkorlát', 'type' => 'number', 'required' => false],
            ['key' => 'pre_registration', 'label' => 'Előzetes jelentkezés szükséges', 'type' => 'checkbox', 'required' => false],
            ['key' => 'registration_link', 'label' => 'Regisztrációs link', 'type' => 'url', 'required' => false, 'help' => 'Ha előzetes jelentkezés kell.'],
        ];

        return [
            'koncertforgatag' => [$performer],
            'duma-ter' => [$speakerBios],
            'kiallitasforgatag' => [
                ['key' => 'exhibition_hours', 'label' => 'Nyitvatartás (több napon át)', 'type' => 'longtext', 'required' => true, 'help' => 'Napokra bontva, pl. „aug. 26–28.: 10–18".'],
                ['key' => 'vernissage_at', 'label' => 'Kiállításmegnyitó időpontja', 'type' => 'text', 'required' => false, 'help' => 'Ha van megnyitó.'],
            ],
            'szinhazi-forgatag' => [
                ['key' => 'age_limit', 'label' => 'Korhatár', 'type' => 'text', 'required' => true],
                ['key' => 'duration_min', 'label' => 'Időtartam (perc)', 'type' => 'number', 'required' => true],
                ['key' => 'ticket_price', 'label' => 'Jegyár', 'type' => 'text', 'required' => true],
                ['key' => 'booking_link', 'label' => 'Foglalási link', 'type' => 'url', 'required' => false, 'help' => 'Ha van online foglalás.'],
            ],
            'csicsergo-csaladi-es-gyermekprogramok' => $workshop,
            'folkudvar-az-erdelyi-hagyomanyok-hazaval' => $workshop,
            'fomco-udvar' => $workshop,
            'mcc-sator' => $workshop,
            'muzeumforgatag' => $workshop,
            'sportforgatag' => [
                ['key' => 'meeting_point', 'label' => 'Találkozási pont', 'type' => 'text', 'required' => false],
                ['key' => 'map_link', 'label' => 'Térkép (link)', 'type' => 'url', 'required' => false],
                ['key' => 'rules_link', 'label' => 'Játékszabályzat (link)', 'type' => 'url', 'required' => false],
            ],
            'tekaforgatag' => [
                ['key' => 'is_book_launch', 'label' => 'Ez könyvbemutató', 'type' => 'checkbox', 'required' => false, 'help' => 'Könyvbemutatónál az alábbiak kötelezők.'],
                ['key' => 'book_author', 'label' => 'Szerző neve', 'type' => 'text', 'required' => false, 'requiredIf' => 'is_book_launch'],
                ['key' => 'book_author_bio', 'label' => 'Szerző bemutatkozása', 'type' => 'longtext', 'required' => false, 'requiredIf' => 'is_book_launch'],
                ['key' => 'book_title', 'label' => 'Könyv címe', 'type' => 'text', 'required' => false, 'requiredIf' => 'is_book_launch'],
                ['key' => 'book_publisher', 'label' => 'Kiadó', 'type' => 'text', 'required' => false, 'requiredIf' => 'is_book_launch'],
            ],
            // Extra nélküli kategóriák (csak base mezők) — a 2026-os végleges lista szerint:
            // bolyai-muhely, borkert, egeszsegforgatag, filmforgatag, folk-forgo, kezmuves-vasar,
            // pulzus-by-rmdsz-maros, szenior-sziget, szoforgatag, torteno-sator, veltol, zsido-ormeny.
            // (A 2026-ban jött négy új kategóriának 2026-ban sem volt extra mezője — nem találunk ki újat.)
        ];
    }

    /**
     * A konfigurációban szereplő, de az adatbázisban NEM létező kategória-slugok (törölt vagy átnevezett
     * kategória). Üres lista = rendben. Néma hiba helyett: piros figyelmeztetés a nyitólapon.
     *
     * @return list<string>
     */
    public static function unknownSlugs(): array
    {
        $letezo = Category::query()->pluck('slug')->all();

        return array_values(array_diff(array_keys(self::extraFieldsBySlug()), $letezo));
    }

    /** @return list<array<string, mixed>> */
    public static function extraFieldsFor(?string $slug): array
    {
        return self::extraFieldsBySlug()[$slug ?? ''] ?? [];
    }

    public static function photoMaxMb(): int
    {
        return (int) round(self::PHOTO_MAX_BYTES / 1024 / 1024);
    }

    /** A fesztiválnap záró ideje „HH:00” alakban — EGY forrásból (Beállítások) a böngészőnek és a szervernek. */
    public static function dayCutTime(): string
    {
        return str_pad((string) Festival::dayCutHour(), 2, '0', STR_PAD_LEFT) . ':00';
    }

    /** Az éjfél utáni befejezés hibaüzenete — a böngésző is ezt a kész szöveget kapja (2026-ban szétcsúszott). */
    public static function dayCutMessage(): string
    {
        return 'Éjfél utáni befejezésnél a vége legkésőbb ' . self::dayCutTime()
            . ' lehet (a fesztiválnap hajnali ' . Festival::dayCutHour() . ' óráig tart).';
    }

    /** A segédszöveg a Vége mező alatt — szintén a beállított záró órából. */
    public static function dayCutHelp(): string
    {
        return 'Éjfél után is végződhet: pl. 23:00 → 00:30. A fesztiválnap hajnali ' . Festival::dayCutHour()
            . '-ig tart, tehát az ilyen program ugyanazon a napon marad.';
    }

    /** @return list<array{value: string, label: string}> a pipálható napok */
    public static function festivalDays(): array
    {
        return array_map(
            fn (array $n) => ['value' => $n['id'], 'label' => Festival::dayLabel($n['date'])],
            Festival::days(),
        );
    }

    /**
     * Az űrlap teljes, böngészőnek szóló konfigja (a 2026-os /api/options megfelelője).
     *
     * @return array<string, mixed>
     */
    public static function clientConfig(Category $category): array
    {
        return [
            'category' => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name_hu,
                'extraFields' => self::extraFieldsFor($category->slug),
            ],
            'baseFields' => self::BASE_FIELDS,
            'festivalDays' => self::festivalDays(),
            'festivalSpan' => Festival::spanLabel(),
            'photoRules' => [
                'minWidth' => self::PHOTO_MIN_WIDTH,
                'minHeight' => self::PHOTO_MIN_HEIGHT,
                'maxMB' => self::photoMaxMb(),
            ],
            'textRules' => ['descriptionMin' => self::DESCRIPTION_MIN, 'descriptionMax' => self::DESCRIPTION_MAX],
            'dayCut' => ['time' => self::dayCutTime(), 'message' => self::dayCutMessage()],
        ];
    }
}
