<?php

namespace App\Filament\Pages;

use App\Enums\NotificationTopic;
use App\Support\Festival;
use App\Support\Settings;
use App\Support\Submission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
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
    private const BLOKKOK = ['festival', 'submission', 'notifications'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Beállítások';

    protected static ?string $title = 'Beállítások';

    protected static ?int $navigationSort = 100;

    /** @var array<string, mixed> */
    public ?array $data = [];

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
                $this->ertesitesBlokk(),
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
                TextInput::make('submission.contact_email')->label('Kapcsolattartó cím a beküldőknek')->email()
                    ->helperText('A beküldők ezt látják, ha kérdésük van, és ide megy a válaszuk a jóváhagyási levélre (a feladó noreply@). Üresen a válasz a noreply@ címre menne. A koordinátorok értesítési címei az Értesítések blokkban vannak.'),
                // Néma hiba tilos: ha a tárhely nem fogadná a 10 MB-os fotót, itt kiderül, nem élesben.
                Text::make(fn () => Submission::uploadLimitWarning())->color('danger')->columnSpanFull()
                    ->visible(fn () => Submission::uploadLimitWarning() !== null),
                Text::make('FIGYELEM: nincs koordinátori értesítési cím — senki nem kap levelet a beküldésekről és a fotóhibákról. Lent, az Értesítések blokkban a „Programbeküldés — új beküldött program” mezőbe írd be.')
                    ->color('danger')->columnSpanFull()
                    ->visible(fn (Get $get) => self::cimek($get('notifications.topics.' . NotificationTopic::ProgramSubmission->value)) === []),
            ]);
    }

    /**
     * A témánkénti címek egyszerű szövegmezőben (soronként vagy vesszővel elválasztva).
     * NEM címke-mező: abban az Enter nélkül beírt cím mentéskor némán elveszett (2026-10-12, élesben).
     */
    private function ertesitesBlokk(): Section
    {
        $temak = array_map(
            fn (NotificationTopic $t) => Textarea::make("notifications.topics.{$t->value}")
                ->label($t->label())
                ->rows(2)
                ->placeholder('koordinator@pelda.ro')
                ->helperText('Egy sorba egy cím (vagy vesszővel elválasztva).')
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
                ->columnSpanFull(),
            NotificationTopic::cases(),
        );

        return Section::make('Értesítések')
            ->description('Témánként azok a koordinátorok, akik értesítést kapnak. Egy témához több cím is megadható.')
            ->columns(2)
            ->schema([
                TextInput::make('notifications.from_address')->label('Feladó cím')->email()
                    ->helperText('Ezzel a címmel mennek ki a rendszer levelei.'),
                TextInput::make('notifications.from_name')->label('Feladó neve')->maxLength(120),
                ...$temak,
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
