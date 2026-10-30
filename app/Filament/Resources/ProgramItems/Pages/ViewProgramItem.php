<?php

namespace App\Filament\Resources\ProgramItems\Pages;

use App\Filament\Resources\ProgramItems\ProgramItemResource;
use App\Models\ProgramHighlight;
use App\Models\ProgramItem;
use App\Services\ProgramReview;
use App\Services\ReviewStepRefused;
use App\Support\Festival;
use App\Support\Submission;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Egy program lapja = a jóváhagyás helye. Fent a figyelmeztetések (miért nem hagyható jóvá,
 * elavult fordítás, nyitott visszaküldés), alatta a fotó és minden adat, lent a napló.
 * Minden gomb visszajelez — a levél sikerét vagy kudarcát is kimondja (néma hiba tilos).
 */
class ViewProgramItem extends ViewRecord
{
    protected static string $resource = ProgramItemResource::class;

    public function getTitle(): string
    {
        return $this->program()->title;
    }

    private function program(): ProgramItem
    {
        /** @var ProgramItem */
        return $this->getRecord();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('jovahagy')->label('Jóváhagyás és közzététel')->color('success')->icon('heroicon-o-check-circle')
                ->visible(fn () => ProgramReview::canApprove($this->program()) && Gate::allows('approve', $this->program()))
                ->disabled(fn () => ProgramReview::approvalBlockers($this->program()) !== [])
                ->tooltip(fn () => ProgramReview::approvalBlockers($this->program()) !== [] ? 'Előbb a lap tetején jelzett hiányokat kell pótolni.' : null)
                ->requiresConfirmation()
                ->modalHeading('Jóváhagyod és közzéteszed?')
                ->modalDescription(fn () => filled($this->program()->contact_email)
                    ? 'A beküldő (' . $this->program()->contact_email . ') levelet kap a jóváhagyásról.'
                    : 'Belső program — levél nem megy ki.')
                ->modalSubmitActionLabel('Jóváhagyom')
                ->action(fn () => $this->lepes(function () {
                    $level = ProgramReview::approve($this->program(), Auth::user());
                    $cim = $this->program()->contact_email;
                    match ($level) {
                        true => Notification::make()->success()->title('Jóváhagyva, közzétéve.')
                            ->body("A beküldő levelet kapott: {$cim}")->send(),
                        false => Notification::make()->danger()->persistent()->title('Jóváhagyva, DE A LEVÉL NEM MENT KI.')
                            ->body("A beküldő ({$cim}) nem kapott értesítést. Írj neki kézzel; a hiba oka a naplóban (storage/logs).")->send(),
                        null => Notification::make()->success()->title('Jóváhagyva, közzétéve.')
                            ->body('Belső program — levél nem ment.')->send(),
                    };
                })),

            Action::make('kuld')->label('Jóváhagyásra küldés')->color('warning')->icon('heroicon-o-arrow-up-circle')
                ->visible(fn () => ProgramReview::canSendToReview($this->program()) && Gate::allows('review', $this->program()))
                ->requiresConfirmation()
                ->modalHeading('Jóváhagyásra küldöd?')
                ->modalDescription('Átnézted, rendben van — a jóváhagyó a „Jóváhagyásra vár” sorban látja. A beküldő a jóváhagyásig még javíthat.')
                ->modalSubmitActionLabel('Küldés')
                ->action(fn () => $this->lepes(function () {
                    ProgramReview::sendToReview($this->program(), Auth::user());
                    Notification::make()->success()->title('Jóváhagyásra küldve.')->send();
                })),

            Action::make('vissza')->label('Visszaküldés javításra')->color('danger')->outlined()->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn () => ProgramReview::canReturn($this->program()) && Gate::allows('review', $this->program()))
                ->modalHeading('Visszaküldés javításra')
                ->modalDescription(fn () => 'A beküldő (' . $this->program()->contact_email . ') levelet kap az indokkal és a javítás linkjével. A program vázlat lesz.'
                    . (Submission::isOpen() ? '' : ' FIGYELEM: a programbeküldés most ZÁRVA van, ezért a beküldő a felületen nem tud javítani — a levél arra kéri, hogy válaszoljon.'))
                ->schema([
                    Textarea::make('indok')->label('Mit javítson? (a beküldő pontosan ezt kapja meg)')
                        ->required()->rows(4)->maxLength(2000)
                        ->validationMessages(['required' => 'Írd le, mit javítson — enélkül nem tudja, mi a baj.']),
                ])
                ->modalSubmitActionLabel('Visszaküldöm')
                ->action(fn (array $data) => $this->lepes(function () use ($data) {
                    $ok = ProgramReview::returnForFix($this->program(), Auth::user(), $data['indok']);
                    $cim = $this->program()->contact_email;
                    $ok
                        ? Notification::make()->success()->title('Visszaküldve javításra.')->body("A beküldő levelet kapott: {$cim}")->send()
                        : Notification::make()->danger()->persistent()->title('Visszaküldve, DE A LEVÉL NEM MENT KI.')
                            ->body("A beküldő ({$cim}) nem kapott értesítést. Írj neki kézzel; a hiba oka a naplóban (storage/logs).")->send();
                })),

            // NAPI KIEMELÉS (2026: program_highlight gyűjtemény): a programkereső a kiválasztott nap kiemeltjeit a találatok
            // fölötti sávban mutatja, a sorrend szerint. Csak a program saját napjai választhatók (más napon nem jelenne meg).
            Action::make('kiemeles')
                ->label(fn () => 'Napi kiemelés' . (($n = $this->program()->highlights()->count()) ? " ({$n} nap)" : ''))
                ->color('gray')->icon('heroicon-o-star')
                ->visible(fn () => (bool) Auth::user()?->role?->canApprovePrograms())
                ->modalHeading('Napi kiemelés a programkeresőben')
                ->modalDescription('A kiválasztott napokon a program a programkereső „Kiemelt programok” sávjában is megjelenik (csak közzétett programnál látszik).')
                ->fillForm(fn () => [
                    'napok' => $this->program()->highlights->map(fn ($h) => $h->date->format('Y-m-d'))->all(),
                    'sorrend' => (int) ($this->program()->highlights->min('sort') ?? 0),
                ])
                ->schema([
                    CheckboxList::make('napok')->label('Melyik napokon?')
                        ->options(fn () => $this->program()->occurrences
                            ->map(fn ($o) => $o->date->format('Y-m-d'))->unique()->filter(fn ($d) => Festival::isProgramDay($d))
                            ->mapWithKeys(fn ($d) => [$d => Festival::fullDayLabel(CarbonImmutable::parse($d))])->all())
                        ->helperText('Csak azok a napok, amelyeken a programnak időpontja van.'),
                    TextInput::make('sorrend')->label('Sorrend a sávban')->numeric()->minValue(0)->maxValue(999)->default(0)
                        ->helperText('Kisebb szám = előrébb. Azonos számnál a kiemelés sorrendje dönt.'),
                ])
                ->modalSubmitActionLabel('Mentés')
                ->action(function (array $data) {
                    $napok = array_values($data['napok'] ?? []);
                    ProgramHighlight::query()->where('program_item_id', $this->program()->id)->whereNotIn('date', $napok)->delete();
                    foreach ($napok as $nap) {
                        $this->program()->highlights()->updateOrCreate(['date' => $nap], ['sort' => (int) ($data['sorrend'] ?? 0)]);
                    }
                    Notification::make()->success()->title($napok ? 'Kiemelve: ' . count($napok) . ' napon.' : 'A kiemelés törölve.')->send();
                    $this->record = ProgramItemResource::getEloquentQuery()->findOrFail($this->program()->getKey());
                }),

            Action::make('foto')->label('Fotó teljes méretben')->color('gray')->icon('heroicon-o-photo')
                ->visible(fn () => $this->program()->hasPhotoFile())
                ->url(fn () => ProgramItemResource::photoUrl($this->program()), shouldOpenInNewTab: true),
        ];
    }

    /** Egy lépés: az elutasítás oka a felületen jelenik meg, utána a lap a friss állapotot mutatja. */
    private function lepes(callable $lepes): void
    {
        try {
            $lepes();
        } catch (ReviewStepRefused $e) {
            Notification::make()->danger()->persistent()->title('A lépés nem hajtható végre')->body($e->getMessage())->send();
        }
        $this->record = ProgramItemResource::getEloquentQuery()->findOrFail($this->program()->getKey());
    }
}
