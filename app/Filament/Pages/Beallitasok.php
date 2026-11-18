<?php

namespace App\Filament\Pages;

use App\Enums\NotificationTopic;
use App\Services\Forditas\GepiForditas;
use App\Support\Festival;
use App\Support\Settings;
use App\Support\Submission;
use App\Support\Tesztadat;
use App\Support\Titkok;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * A fesztivál MINDEN szervezői beállításának EGYETLEN otthona (CLAUDE.md 2/b).
 * Egy blokk = egy téma. ÚJ FUNKCIÓ = ÚJ BLOKK ezen a lapon, NEM új menüpont.
 * Fülek csak akkor jönnek, ha már sok a mező — akkor is ezen az egy lapon belül.
 * A /tema külön marad (saját szerepkör: aki színeket állít, ne tudja lezárni a beküldést).
 *
 * Új blokk felvétele: (1) itt egy Section, (2) a seed/settings.json-ba a kezdőértékei,
 * (3) a blokk neve a BLOKKOK listába. A betöltő a hiányzó blokkot magától létrehozza.
 */
class Beallitasok extends Page
{
    /** A lapon szerkesztett blokkok (a settings tábla sorai). */
    private const BLOKKOK = ['festival', 'submission', 'olympics', 'volunteer', 'public', 'notifications', 'translation'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Beállítások';

    protected static ?string $title = 'Beállítások';

    protected static ?int $navigationSort = 100;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Szuperadmin és koordinátor (App\Enums\UserRole::canEditSettings). */
    public static function canAccess(): bool
    {
        $u = auth('web')->user();

        return $u instanceof \App\Models\User && $u->role->canEditSettings();
    }

    public function mount(): void
    {
        $this->form->fill(array_intersect_key(Settings::all(), array_flip(self::BLOKKOK)));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                $this->fesztivalBlokk(),
                $this->bekuldesBlokk(),
                $this->olimpiaBlokk(),
                $this->onkentesBlokk(),
                $this->publikusBlokk(),
                $this->ertesitesBlokk(),
                $this->forditasBlokk(),
                $this->titkokBlokk(),
                $this->tesztadatBlokk(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Mentés')->submit('save')->keyBindings(['mod+s']),
                    ])->sticky()->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        $allapot = $this->form->getState();
        Settings::save(array_intersect_key($allapot, array_flip(self::BLOKKOK)));

        Notification::make()->success()->title('A beállítások elmentve.')->send();
    }

    private function fesztivalBlokk(): Section
    {
        return Section::make('Fesztivál alapadatai')
            ->description('A programnapokat a rendszer ezekből számolja: az előkészítő napok a kezdő dátum előtti napok. Csak ezekre a napokra lehet programot beküldeni.')
            ->columns(2)
            ->schema([
                DatePicker::make('festival.start_date')->label('Kezdő dátum')->required()->live(),
                DatePicker::make('festival.end_date')->label('Záró dátum')->required()->live()
                    ->afterOrEqual('festival.start_date')
                    ->validationMessages(['after_or_equal' => 'A záró dátum nem lehet a kezdő dátum előtt.']),
                TextInput::make('festival.prep_days')->label('Előkészítő napok száma')
                    ->integer()->minValue(0)->maxValue(14)->required()->live(debounce: 500)
                    ->helperText('A kezdő dátum előtti napok, amelyekre szintén lehet programot beküldeni.'),
                DatePicker::make('festival.opening_day')->label('Nyitónap')->required()
                    ->afterOrEqual('festival.start_date')->beforeOrEqual('festival.end_date')
                    ->validationMessages([
                        'after_or_equal' => 'A nyitónap a fesztivál napjai közé kell essen.',
                        'before_or_equal' => 'A nyitónap a fesztivál napjai közé kell essen.',
                    ])
                    ->helperText('A „Mi van ma?” gomb fesztiválon kívül erre a napra visz.'),
                TextInput::make('festival.name_hu')->label('A fesztivál neve (magyar)')->required()->maxLength(120),
                TextInput::make('festival.name_ro')->label('A fesztivál neve (román)')->required()->maxLength(120),
                TextInput::make('festival.day_cut_hour')->label('A fesztiválnap vége (hajnali óra)')
                    ->integer()->minValue(0)->maxValue(6)->required()->suffix('óra')
                    ->helperText('Pl. 3: a 00:00 és 03:00 között végződő program még az előző naphoz tartozik.'),
                Text::make(fn (Get $get) => $this->napokElonezet($get))->columnSpanFull(),
            ]);
    }

    private function bekuldesBlokk(): Section
    {
        return Section::make('Programbeküldés')
            ->columns(2)
            ->schema([
                Toggle::make('submission.open')->label('A programbeküldés nyitva van')->columnSpanFull()
                    ->helperText('Kikapcsolva a beküldő űrlap azonnal bezár — kódmódosítás és újraindítás nélkül.'),
                Textarea::make('submission.closed_message')->label('Üzenet zárt állapotban')
                    ->required()->rows(2)->maxLength(500)->columnSpanFull(),
                DatePicker::make('submission.deadline')->label('Beküldési határidő (utolsó nap)')
                    ->helperText('Ezen a napon még be lehet küldeni, éjfélkor zár — akkor is, ha a kapcsoló nyitva áll. Üresen: nincs határidő.'),
                // Néma hiba tilos: ha a tárhely nem fogadná a 10 MB-os fotót, itt kiderül, nem élesben.
                Text::make(fn () => Submission::uploadLimitWarning())->color('danger')->columnSpanFull()
                    ->visible(fn () => Submission::uploadLimitWarning() !== null),
                Text::make('FIGYELEM: nincs koordinátori értesítési cím — senki nem kap levelet a beküldésekről és a fotóhibákról. Lent, az Értesítések blokkban a „Programbeküldés — új beküldött program” mezőbe írd be.')
                    ->color('danger')->columnSpanFull()
                    ->visible(fn (Get $get) => self::cimek($get('notifications.topics.' . NotificationTopic::ProgramSubmission->value)) === []),
            ]);
    }

    /**
     * CSALÁDI OLIMPIA (PROMPT-20; 2026: olympics-api — a csapatszámot env, a színeket a Directus adta). A színlista KULCSA
     * (rejtett) a csapatok foglalásának azonosítója: átnevezéskor megmarad, így a már elvitt szín elvitt marad. Új szín kulcsa
     * a nevéből készül, mentéskor.
     */
    private function olimpiaBlokk(): Section
    {
        return Section::make('Családi Olimpia')
            ->description('A publikus jelentkező űrlap: ' . url('/olimpia') . ' — a bevezető szövege: Tartalom → Aloldalak → Családi Olimpia.')
            ->columns(2)
            ->schema([
                Toggle::make('olympics.open')->label('A jelentkezés nyitva van')->columnSpanFull()->live()
                    ->helperText('Kikapcsolva az űrlap helyén a lenti üzenet áll, és a beküldő végpont is elutasít — mentett lapról vagy robottól sem jöhet jelentkezés.')
                    ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                        if (! $value) {
                            return;
                        }
                        if (blank($get('olympics.event_date'))) {
                            $fail('Nyitás előtt add meg az olimpia napját.');
                        }
                        if (collect((array) $get('olympics.colors'))->where('active', true)->filter(fn ($c) => filled($c['name'] ?? null))->isEmpty()) {
                            $fail('Nyitás előtt legalább egy választható csapatszín kell.');
                        }
                    }),
                Textarea::make('olympics.closed_message')->label('Üzenet zárt állapotban')->required()->rows(2)->maxLength(500)->columnSpanFull(),
                DatePicker::make('olympics.event_date')->label('Az olimpia napja')
                    ->helperText('A bevezetőben így jelenik meg: „2027. augusztus 28-án, szombaton”. Amíg üres, az a bekezdés nem látszik.'),
                TextInput::make('olympics.max_teams')->label('Induló csapatok száma')->integer()->minValue(1)->maxValue(50)->required()
                    ->helperText('Eddig a számig „Jelentkezett”, utána a jelentkezés sorrendjében VÁRÓLISTA (2026-ban 6).'),
                Repeater::make('olympics.colors')->label('Választható csapatszínek')->columnSpanFull()
                    ->helperText('Egy színt csak egy csapat vihet el. Visszalépéskor a szín magától felszabadul. Kikapcsolt szín nem választható (de az elvitt szín elvittként látszik).')
                    ->schema([
                        Hidden::make('key'),
                        TextInput::make('name')->label('Név')->required()->maxLength(60),
                        TextInput::make('hex')->label('Színkód')->placeholder('#E13E42')->maxLength(7)
                            ->regex('/^#[0-9A-Fa-f]{6}$/')->validationMessages(['regex' => '#RRGGBB alakban add meg, pl. #E13E42.']),
                        Toggle::make('active')->label('Választható')->default(true)->inline(false),
                    ])
                    ->columns(3)->defaultItems(0)->addActionLabel('Új szín')->reorderable()
                    ->dehydrateStateUsing(fn ($state) => self::szinKulcsok($state)),
                self::cimMezo(NotificationTopic::Olympics)->label('A koordinátor e-mail-címe (értesítés minden új jelentkezésről)'),
                Text::make('FIGYELEM: nincs koordinátori cím — senki nem kap levelet az új jelentkezésekről (a csapat a visszaigazolást így is megkapja). Írd be fent.')
                    ->color('danger')->columnSpanFull()
                    ->visible(fn (Get $get) => self::cimek($get('notifications.topics.' . NotificationTopic::Olympics->value)) === []),
            ]);
    }

    /**
     * ÖNKÉNTESEK (PROMPT-20). ⚠ Az önkéntes-napok SAJÁT lista, NEM a programnapok: van „nulladik nap” és „utómunkálatok” nap is
     * (2026-ban a volunteer-api fájljába égetve). A napok a dátumból kapják a feliratukat, a megjegyzés a zárójelbe kerül.
     */
    private function onkentesBlokk(): Section
    {
        return Section::make('Önkéntesek')
            ->description('A publikus jelentkező űrlap: ' . url('/onkentes') . ' — kiskorúak adatai is érkeznek rajta; a jelentkezéseket csak a szuperadmin és az önkéntes-koordinátor látja. A bevezető szövege: Tartalom → Aloldalak → Önkéntes-jelentkezés.')
            ->columns(2)
            ->schema([
                Toggle::make('volunteer.open')->label('A jelentkezés nyitva van')->columnSpanFull()->live()
                    ->helperText('Kikapcsolva az űrlap helyén a lenti üzenet áll, és a beküldő végpont is elutasít.')
                    ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                        if (! $value) {
                            return;
                        }
                        if (collect((array) $get('volunteer.days'))->filter(fn ($n) => filled($n['date'] ?? null))->isEmpty()) {
                            $fail('Nyitás előtt add meg az önkéntes-napokat.');
                        }
                        if (self::sorok($get('volunteer.areas')) === []) {
                            $fail('Nyitás előtt add meg a területeket.');
                        }
                    }),
                Textarea::make('volunteer.closed_message')->label('Üzenet zárt állapotban')->required()->rows(2)->maxLength(500)->columnSpanFull(),
                TextInput::make('volunteer.minor_age')->label('Korhatár (év)')->integer()->minValue(1)->maxValue(25)->required()
                    ->helperText('Ez alatt a szülői beleegyezés ÉS a kézzel írt motivációs levél feltöltése kötelező (2026-ban 16).'),
                Textarea::make('volunteer.areas')->label('Területek, ahol segíthetnek')->rows(6)
                    ->helperText('Egy sorba egy terület. A jelentkező többet is választhat.')
                    ->formatStateUsing(fn ($state) => implode("\n", (array) $state))
                    ->dehydrateStateUsing(fn ($state) => self::sorok($state)),
                Repeater::make('volunteer.days')->label('Önkéntes-napok (SAJÁT lista — nem a programnapok)')->columnSpanFull()
                    ->helperText('A nyitás előtti „nulladik nap” és a zárás utáni „utómunkálatok” napja is ide kell. A felirat a dátumból készül, pl. „aug. 24. (kedd – nulladik nap)”.')
                    ->schema([
                        DatePicker::make('date')->label('Dátum')->required(),
                        TextInput::make('note')->label('Megjegyzés a zárójelben (nem kötelező)')->placeholder('pl. nulladik nap')->maxLength(40),
                    ])
                    ->columns(2)->defaultItems(0)->addActionLabel('Új nap')->reorderable(false)
                    ->dehydrateStateUsing(fn ($state) => array_values(array_map(fn ($n) => [
                        'date' => (string) ($n['date'] ?? ''), 'note' => trim((string) ($n['note'] ?? '')),
                    ], (array) $state))),
                Actions::make([
                    Action::make('onkentesNapokKitoltes')->label('Kitöltés a fesztivál napjaiból (előtte és utána egy nappal)')->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Az önkéntes-napok kitöltése')
                        ->modalDescription(fn (Get $get) => 'A lista helyére a fesztivál napjai kerülnek (' . ($get('festival.start_date') ?: '—') . ' – ' . ($get('festival.end_date') ?: '—')
                            . '), előtte egy „nulladik nap”, utána egy „utómunkálatok” nap — a 2026-os felépítés szerint. Mentés előtt átírható.')
                        ->action(function (Get $get, \Filament\Schemas\Components\Utilities\Set $set) {
                            $napok = Festival::computeDays($get('festival.start_date'), $get('festival.end_date'), 0);
                            if ($napok === []) {
                                Notification::make()->danger()->title('Előbb a fesztivál kezdő és záró dátumát add meg (fent).')->send();

                                return;
                            }
                            $lista = [['date' => $napok[0]['date']->subDay()->toDateString(), 'note' => 'nulladik nap']];
                            foreach ($napok as $n) {
                                $lista[] = ['date' => $n['date']->toDateString(), 'note' => ''];
                            }
                            $lista[] = ['date' => $napok[array_key_last($napok)]['date']->addDay()->toDateString(), 'note' => 'utómunkálatok'];
                            $set('volunteer.days', collect($lista)->mapWithKeys(fn ($n) => [(string) \Illuminate\Support\Str::uuid() => $n])->all());
                        }),
                ])->columnSpanFull(),
                Text::make(fn (Get $get) => 'Az űrlapon így látszanak: ' . (collect((array) $get('volunteer.days'))
                    ->filter(fn ($n) => filled($n['date'] ?? null))->sortBy('date')
                    ->map(fn ($n) => \App\Support\Onkentes::napFelirat(\Carbon\CarbonImmutable::parse($n['date']), (string) ($n['note'] ?? '')))
                    ->implode(' · ') ?: '— (még nincs nap)'))->columnSpanFull(),
                self::cimMezo(NotificationTopic::Volunteer)->label('Az önkéntes-koordinátor e-mail-címe (értesítés minden új jelentkezésről)'),
                Text::make('FIGYELEM: nincs koordinátori cím — senki nem kap levelet az új önkéntes-jelentkezésekről. Írd be fent.')
                    ->color('danger')->columnSpanFull()
                    ->visible(fn (Get $get) => self::cimek($get('notifications.topics.' . NotificationTopic::Volunteer->value)) === []),
                Text::make(fn () => \App\Support\Onkentes::feltoltesiKorlatFigyelmeztetes())->color('danger')->columnSpanFull()
                    ->visible(fn () => \App\Support\Onkentes::feltoltesiKorlatFigyelmeztetes() !== null),
            ]);
    }

    /** Egy téma koordinátori címmezője — EGY leírás az Értesítések blokknak és a saját blokkos témáknak. */
    private static function cimMezo(NotificationTopic $t): Textarea
    {
        return Textarea::make("notifications.topics.{$t->value}")
            ->label($t->label())
            ->rows(2)
            ->placeholder('koordinator@pelda.ro')
            ->helperText('Egy sorba egy cím (vagy vesszővel elválasztva). Ha a beküldő válaszol a rendszer levelére, a válasza is ide jön.')
            ->live(onBlur: true)
            ->formatStateUsing(fn ($state) => implode("\n", self::cimek($state)))
            ->dehydrateStateUsing(fn ($state) => self::cimek($state))
            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) {
                foreach (self::cimek($value) as $cim) {
                    if (! filter_var($cim, FILTER_VALIDATE_EMAIL)) {
                        $fail("Érvénytelen e-mail-cím: {$cim}");
                    }
                }
            })
            ->columnSpanFull();
    }

    /**
     * A színlista mentéskor: üres kulcsú (új) szín kulcsot kap a nevéből, egyedire igazítva. A meglévő kulcs SOSEM változik
     * (a csapatok foglalása erre hivatkozik).
     *
     * @return list<array{key: string, name: string, hex: ?string, active: bool}>
     */
    private static function szinKulcsok(mixed $state): array
    {
        $lista = array_values(array_filter((array) $state, fn ($c) => filled($c['name'] ?? null)));
        $foglalt = array_filter(array_map(fn ($c) => (string) ($c['key'] ?? ''), $lista));
        foreach ($lista as $i => $c) {
            if (blank($c['key'] ?? null)) {
                $alap = \Illuminate\Support\Str::slug((string) $c['name']) ?: 'szin';
                $kulcs = $alap;
                for ($n = 2; in_array($kulcs, $foglalt, true); $n++) {
                    $kulcs = "{$alap}-{$n}";
                }
                $foglalt[] = $kulcs;
                $lista[$i]['key'] = $kulcs;
            }
            $lista[$i] = [
                'key' => (string) $lista[$i]['key'],
                'name' => trim((string) $c['name']),
                'hex' => filled($c['hex'] ?? null) ? strtoupper((string) $c['hex']) : null,
                'active' => (bool) ($c['active'] ?? true),
            ];
        }

        return $lista;
    }

    /**
     * A publikus oldal: a „Hamarosan” mód (korai élesítés) és a közös elemek szövegei. A Hamarosan mód CSAK a
     * publikus lapokat takarja el (App\Http\Middleware\HamarosanMod) — az admin és a beküldő űrlap elérhető marad,
     * a belépett admin pedig a valódi lapokat látja (egy sávval jelezve).
     */
    private function publikusBlokk(): Section
    {
        return Section::make('Publikus oldal')
            ->columns(2)
            ->schema([
                Toggle::make('public.coming_soon')->label('„Hamarosan” mód')->columnSpanFull()
                    ->helperText('Bekapcsolva a látogatók minden publikus címen a Hamarosan lapot látják. Az admin és a beküldő űrlap ettől függetlenül működik; belépett adminként a valódi lapok látszanak.'),
                TextInput::make('public.coming_soon_title_hu')->label('Hamarosan lap — cím (magyar)')->required()->maxLength(120),
                TextInput::make('public.coming_soon_title_ro')->label('Hamarosan lap — cím (román)')->maxLength(120),
                Textarea::make('public.coming_soon_text_hu')->label('Hamarosan lap — szöveg (magyar)')->required()->rows(3)->maxLength(600),
                Textarea::make('public.coming_soon_text_ro')->label('Hamarosan lap — szöveg (román)')->rows(3)->maxLength(600),
                Text::make('A Hamarosan lapon a logó (Arculat), a fesztivál neve és dátuma (fent) magától jelenik meg. A román üres mező helyén a magyar szöveg látszik.')->columnSpanFull(),
                Actions::make([
                    Action::make('hamarosanElonezet')->label('A Hamarosan lap megnyitása')->color('gray')
                        ->url('/hamarosan', shouldOpenInNewTab: true),
                ])->columnSpanFull(),
                Textarea::make('public.footer_text_hu')->label('Lábléc — rövid bemutatkozás (magyar)')->required()->rows(2)->maxLength(300)
                    ->helperText('Elé magától kerül a fesztivál dátuma és a város.'),
                Textarea::make('public.footer_text_ro')->label('Lábléc — rövid bemutatkozás (román)')->rows(2)->maxLength(300),
                // A KEZDŐLAP KÉT ÁLLAPOTA (PROMPT-14): ugyanaz a lap, csak a két elem látszik vagy nem.
                Radio::make('public.home_state')->label('A kezdőlap állapota')->required()->columnSpanFull()
                    ->options([
                        'fesztival' => 'Fesztivál előtt / alatt — a nyitóképen visszaszámláló, alatta a „Kiemelt programok” sáv',
                        'utana' => 'Fesztivál után — visszaszámláló és „Kiemelt programok” nélkül',
                    ])
                    ->helperText('A partner-logó sáv mindkét állapotban látszik. A nyitókép címe és a kiemelt kártyák szövege/képe: Tartalom → Aloldalak → Kezdőlap — váltáskor érdemes azokat is átírni.'),
                TextInput::make('public.countdown_time')->label('Visszaszámláló — a nyitás időpontja a nyitónapon')
                    ->required()->regex('/^([01]\d|2[0-3]):[0-5]\d$/')->placeholder('10:00')
                    ->validationMessages(['regex' => 'Óra:perc alakban add meg, pl. 10:00.'])
                    ->helperText(fn (Get $get) => 'A visszaszámláló eddig számol: ' . ($get('festival.opening_day') ?: '—') . ' (nyitónap, fent), ennyi órakor.'),
            ]);
    }

    /**
     * A témánkénti címek egyszerű szövegmezőben (soronként vagy vesszővel elválasztva).
     * NEM címke-mező: abban az Enter nélkül beírt cím mentéskor némán elveszett (2026-10-12, élesben).
     */
    private function ertesitesBlokk(): Section
    {
        // A saját blokkal bíró témák (olimpia, önkéntes) címe a saját blokkjukban ül (NotificationTopic::blokk) — a tárolás ugyanaz.
        $temak = array_map(
            fn (NotificationTopic $t) => self::cimMezo($t),
            array_values(array_filter(NotificationTopic::cases(), fn (NotificationTopic $t) => $t->blokk() === null)),
        );

        return Section::make('Értesítések')
            // EGY funkció = EGY cím: itt csak az áll, KI kapja a levelet. A feladó (noreply@) a szerver
            // levelezési beállítása (.env, SMTP) — azt itt nem kell, és nem is lehet megadni.
            ->description('Témánként azok a koordinátorok, akik értesítést kapnak. Egy témához több cím is megadható.')
            ->schema($temak);
    }

    /**
     * GÉPI FORDÍTÁS (PROMPT-19, CLAUDE.md 5.): a védett nevek és a kötött román alakok (szervezői adat, 2/b — kezdőértékük a
     * seed/do-not-translate.json), alatta a gép állapota és az utolsó futás. A modell és a kulcs technikai beállítás (.env).
     * Soronkénti szövegmező, NEM címke-mező: abban az Enter nélkül beírt érték mentéskor némán elveszett (2026-10-12).
     */
    private function forditasBlokk(): Section
    {
        return Section::make('Gépi fordítás (magyar → román)')
            ->description('CSAK a beküldött programok címét, leírását és jegyinformációját fordítja gép (Claude). A saját tartalmakat (hírek, lapok, kategória- és helyszínnevek, menü) kézzel fordítjuk. A kézzel átnézett fordítást a gép soha nem írja felül.')
            ->columns(2)
            ->schema([
                Text::make(fn () => self::forditasAllapot())->columnSpanFull()
                    ->color(fn () => (GepiForditas::utolsoFutas()['allapot'] ?? null) === 'hiba' || ! GepiForditas::vanKulcs() ? 'danger' : null),
                Actions::make([
                    Action::make('forditasMost')->label('Gépi fordítás futtatása most')->color('gray')
                        ->disabled(fn () => ! GepiForditas::vanKulcs())
                        ->requiresConfirmation()
                        ->modalHeading('Gépi fordítás futtatása most')
                        ->modalDescription('Minden program fordítatlan vagy megváltozott mezőjét lefordítja. A változatlan szöveget nem fordítja újra, a kézzel átnézettekhez nem nyúl. Sok programnál percekig is tarthat.')
                        ->modalSubmitActionLabel('Futtatás')
                        ->action(function (): void {
                            abort_unless(static::canAccess(), 403);
                            @set_time_limit(600);
                            $j = app(GepiForditas::class)->futtat(null, 'Beállítások gomb');
                            $n = Notification::make()->title('Gépi fordítás: ' . GepiForditas::allapotSzoveg($j))
                                ->body("Lefordítva: {$j['forditva']} mező · jelzéssel: {$j['jelzett']} · elavult (emberre vár): {$j['elavult_osszes']} · API-hívás: {$j['api_hivas']}");
                            $j['allapot'] === 'hiba' ? $n->danger()->persistent()->send() : $n->success()->send();
                        }),
                ])->columnSpanFull(),
                Textarea::make('translation.terms')->label('Változatlanul hagyandó nevek')->rows(10)
                    ->helperText('Egy sorba egy név. A gép ezeket betűre pontosan, magyarul hagyja a román szövegben (márkanevek, helyszínek, programnevek — pl. Ebforgatag, Csicsergő). Ami köznév, azt NE ide írd: azt le kell fordítani.')
                    ->formatStateUsing(fn ($state) => implode("\n", (array) $state))
                    ->dehydrateStateUsing(fn ($state) => self::sorok($state)),
                Repeater::make('translation.bound')->label('Kötött román alakok')
                    ->helperText('A szervezők által eldöntött hivatalos román név — a gép mindig ezt használja (pl. Vásárhelyi Forgatag → Vâltoarea Mureșeană). A ragozását a rendszer ismeri.')
                    ->schema([
                        TextInput::make('hu')->label('Magyarul')->required()->maxLength(120),
                        TextInput::make('ro')->label('Románul')->required()->maxLength(120),
                    ])
                    ->columns(2)->defaultItems(0)->addActionLabel('Új kötött alak')
                    ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                        $nevek = array_map('mb_strtolower', self::sorok($get('translation.terms')));
                        foreach ((array) $value as $k) {
                            if (in_array(mb_strtolower(trim((string) ($k['hu'] ?? ''))), $nevek, true)) {
                                $fail('„' . $k['hu'] . '” mindkét listán szerepel — egy név vagy változatlan marad, vagy kötött román alakja van.');
                            }
                        }
                    }),
            ]);
    }

    /**
     * TITKOS BEÁLLÍTÁSOK (App\Support\Titkok) — CSAK SZUPERADMIN látja. ÍRHATÓ, DE NEM OLVASHATÓ: nincs mező, ami a tárolt
     * értéket tartalmazná (az űrlap állapotába sem kerül); titkonként csak az állapot (be van-e állítva, honnan, ki és mikor
     * cserélte) és két gomb: „Új érték megadása” (felugró ablak, üres jelszómező) és „Törlés”. Nem a BLOKKOK része (nem a
     * settings táblában él).
     */
    private function titkokBlokk(): Section
    {
        $sorok = [];
        foreach (array_keys(Titkok::KULCSOK) as $kulcs) {
            $sorok[] = Section::make(Titkok::KULCSOK[$kulcs]['felirat'])->compact()
                ->description(Titkok::KULCSOK[$kulcs]['sugo'])
                ->schema([
                    Text::make(fn () => Titkok::allapot($kulcs)['szoveg'])
                        ->color(fn () => Titkok::allapot($kulcs)['forras'] === null ? 'danger' : null),
                    Text::make(fn () => (string) Titkok::allapot($kulcs)['hiba'])->color('danger')
                        ->visible(fn () => Titkok::allapot($kulcs)['hiba'] !== null),
                    Actions::make([
                        Action::make("titok_{$kulcs}")->label(fn () => Titkok::allapot($kulcs)['forras'] === 'admin' ? 'Csere új értékre' : 'Megadás')
                            ->icon('heroicon-o-key')
                            ->modalHeading(Titkok::KULCSOK[$kulcs]['felirat'])
                            ->modalDescription('Az új érték titkosítva kerül az adatbázisba, és azonnal ez lesz használatban. A régi érték elvész. Megadás után senki nem tudja itt visszaolvasni — csak lecserélni.')
                            ->schema([
                                TextInput::make('ertek')->label('Új érték')->password()->revealable(false)
                                    ->required()->maxLength(500)->autocomplete('new-password'),
                            ])
                            ->modalSubmitActionLabel('Mentés')
                            ->action(function (array $data) use ($kulcs): void {
                                abort_unless(self::szuperadmin(), 403);
                                Titkok::beallit($kulcs, (string) $data['ertek'], auth('web')->user());
                                Notification::make()->success()->title('Elmentve: ' . Titkok::KULCSOK[$kulcs]['felirat'])
                                    ->body('Mostantól ez van használatban.')->send();
                            }),
                        Action::make("titok_{$kulcs}_torles")->label('Törlés')->color('danger')->outlined()
                            ->visible(fn () => Titkok::allapot($kulcs)['forras'] === 'admin')
                            ->requiresConfirmation()
                            ->modalHeading('Törlöd az adminban megadott értéket?')
                            ->modalDescription(fn () => Titkok::allapot($kulcs)['env_is']
                                ? 'Utána a szerveri .env értéke (' . Titkok::KULCSOK[$kulcs]['env'] . ') lesz használatban.'
                                : 'A szerveri .env-ben NINCS érték — utána a funkció kikapcsol, amíg újat nem adsz meg.')
                            ->modalSubmitActionLabel('Törlöm')
                            ->action(function () use ($kulcs): void {
                                abort_unless(self::szuperadmin(), 403);
                                Titkok::torol($kulcs, auth('web')->user());
                                Notification::make()->success()->title('Törölve: ' . Titkok::KULCSOK[$kulcs]['felirat'])->send();
                            }),
                    ])->key("titok_{$kulcs}_gombok"),
                ]);
        }

        return Section::make('Titkos beállítások (csak szuperadmin)')
            ->description('Pénzt költő vagy hozzáférést adó kulcsok. Csak megadni és cserélni lehet, visszaolvasni nem. Az itt megadott érték NYER a szerveri .env-ben lévő fölött; ha itt nincs, a .env értéke él.')
            ->visible(fn () => self::szuperadmin())
            ->schema($sorok);
    }

    private static function szuperadmin(): bool
    {
        $u = auth('web')->user();

        return $u instanceof \App\Models\User && $u->role->canManageSecrets();
    }

    /** Az utolsó futás és a gép állapota emberi nyelven (néma hiba tilos: a hiba itt is látszik). */
    private static function forditasAllapot(): string
    {
        $sor = GepiForditas::vanKulcs()
            ? 'A gépi fordítás BE van kapcsolva (modell: ' . config('forditas.modell') . ').'
            : 'A gépi fordítás KI van kapcsolva: nincs Claude API-kulcs (szuperadmin adhatja meg lent, a Titkos beállításokban). A beküldés ettől működik, csak román fordítás nem készül.';
        $j = GepiForditas::utolsoFutas();

        return $sor . ' ' . ($j === null
            ? 'Még nem futott.'
            : 'Utolsó futás: ' . \Carbon\CarbonImmutable::parse($j['vege'] ?? $j['kezdet'])->timezone(config('app.timezone'))->format('Y. m. d. H:i')
                . ' (' . $j['indito'] . ') — ' . GepiForditas::allapotSzoveg($j)
                . ". Lefordítva: {$j['forditva']} · jelzéssel: {$j['jelzett']} · elavult, emberre vár: {$j['elavult_osszes']} · API-hívás: {$j['api_hivas']}, kb. {$j['koltseg_usd']} USD.");
    }

    /** @return list<string> soronkénti lista (üres sorok és ismétlések nélkül) */
    private static function sorok(mixed $ertek): array
    {
        $d = is_array($ertek) ? $ertek : preg_split('/\R/u', (string) $ertek);

        return array_values(array_unique(array_filter(array_map('trim', $d), fn ($s) => $s !== '')));
    }

    /**
     * Tesztadatok (App\Support\Tesztadat): a tesztelésre behozott hírek és programok egy lépésben törölhetők.
     * Nem tárolt beállítás — nincs a BLOKKOK között, csak a számot és a törlés gombját mutatja.
     */
    private function tesztadatBlokk(): Section
    {
        return Section::make('Tesztadatok')
            ->description('A 2026-ból tesztelésre behozott hírek és programok (az adminban TESZT jelvénnyel). Élesítés előtt egy lépésben törölhetők.')
            ->schema([
                Text::make(fn () => Tesztadat::total() > 0 ? 'Most bent: ' . Tesztadat::summary() . '.' : 'Nincs tesztadat az adatbázisban.')
                    ->color(fn () => Tesztadat::total() > 0 ? 'danger' : null),
                Actions::make([
                    Action::make('tesztadatTorles')->label('Minden tesztadat törlése')->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Minden tesztadat törlése')
                        ->modalDescription(fn () => 'Törlődik: ' . Tesztadat::summary() . '. A valódi (nem teszt) hírekhez és programokhoz nem nyúl. Nem vonható vissza.')
                        ->modalSubmitActionLabel('Igen, törlöm')
                        ->disabled(fn () => Tesztadat::total() === 0)
                        ->action(function (): void {
                            abort_unless(static::canAccess(), 403);
                            $torolt = Tesztadat::purge();
                            $maradt = Tesztadat::total();
                            $n = Notification::make()->title('Törölve: ' . collect($torolt)->map(fn ($db, $mi) => "{$db} {$mi}")->implode(', '));
                            // Néma hiba tilos: ha valami bent maradt, az látszódjon.
                            $maradt === 0 ? $n->success()->send() : $n->danger()->body("Bent maradt {$maradt} tesztadat — szólj a fejlesztőnek.")->persistent()->send();
                        }),
                ]),
            ]);
    }

    /** @return list<string> a címlista tömbből vagy szövegből (sor / vessző / pontosvessző) */
    private static function cimek(mixed $ertek): array
    {
        $darabok = is_array($ertek) ? $ertek : preg_split('/[\s,;]+/', (string) $ertek);

        return array_values(array_unique(array_filter(array_map('trim', $darabok))));
    }

    /** Élő előnézet a még mentetlen értékekből is — hogy mentés előtt látszódjon, mi jön ki. */
    private function napokElonezet(Get $get): string
    {
        $napok = Festival::computeDays(
            $get('festival.start_date'),
            $get('festival.end_date'),
            (int) $get('festival.prep_days'),
        );
        if ($napok === []) {
            return blank($get('festival.start_date')) || blank($get('festival.end_date'))
                ? 'Programnapok: a kezdő és záró dátum megadása után itt látszanak.'
                : 'Programnapok: nincsenek — a záró dátum a kezdő dátum előtt van.';
        }

        $elokeszito = array_map(fn ($n) => Festival::dayLabel($n['date']), array_filter($napok, fn ($n) => $n['prep']));
        $fesztival = array_map(fn ($n) => Festival::dayLabel($n['date']), array_filter($napok, fn ($n) => ! $n['prep']));

        return 'Év: ' . $napok[array_key_last($napok)]['date']->year
            . ' · Előkészítő napok: ' . ($elokeszito ? implode(', ', $elokeszito) : 'nincs')
            . ' · Fesztiválnapok: ' . implode(', ', $fesztival);
    }
}
