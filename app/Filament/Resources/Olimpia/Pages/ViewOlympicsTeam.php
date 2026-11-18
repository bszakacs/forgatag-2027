<?php

namespace App\Filament\Resources\Olimpia\Pages;

use App\Enums\OlympicsStatus;
use App\Filament\Resources\Olimpia\OlympicsTeamResource;
use App\Models\OlympicsTeam;
use App\Support\Olimpia;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

/**
 * Egy csapat lapja. A státusz gombokkal változik (2026: kézzel a Directusban + a szín-felszabadító flow):
 *  · „Visszalépett” → a szín AZONNAL felszabadul (a modell üríti), a hely szabad lesz;
 *  · „Előléptetés a várólistáról” → csak ha van szabad induló hely (a sorban következőt érdemes, a lista sorrendje mutatja).
 * Levél egyik sem megy magától: a csapatot a koordinátor értesíti (2026-ban is így).
 */
class ViewOlympicsTeam extends ViewRecord
{
    protected static string $resource = OlympicsTeamResource::class;

    private function csapat(): OlympicsTeam
    {
        /** @var OlympicsTeam $r */
        $r = $this->getRecord();

        return $r;
    }

    private static function szabadHely(OlympicsTeam $t): int
    {
        $indulo = OlympicsTeam::query()->where('year', $t->year)->where('status', OlympicsStatus::Applied->value)->count();

        return max(0, Olimpia::maxCsapat() - $indulo);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('eloleptet')->label('Előléptetés a várólistáról')->color('success')->icon('heroicon-o-arrow-up-circle')
                ->visible(fn () => $this->csapat()->status === OlympicsStatus::Waitlist && Gate::allows('update', $this->csapat()))
                ->disabled(fn () => self::szabadHely($this->csapat()) === 0)
                ->tooltip(fn () => self::szabadHely($this->csapat()) === 0 ? 'Nincs szabad induló hely — előbb egy csapatnak vissza kell lépnie.' : null)
                ->requiresConfirmation()
                ->modalHeading('A csapat elindulhat?')
                ->modalDescription(fn () => 'A csapat „Jelentkezett” állapotba kerül. Levél NEM megy ki magától — értesítsd a kapitányt: '
                    . $this->csapat()->captain_phone . ' · ' . $this->csapat()->captain_email)
                ->action(function () {
                    abort_unless(Gate::allows('update', $this->csapat()) && self::szabadHely($this->csapat()) > 0, 403);
                    $this->csapat()->update(['status' => OlympicsStatus::Applied, 'status_manual' => true]);
                    Notification::make()->success()->title('A csapat elindulhat.')->body('Ne felejtsd el értesíteni a kapitányt.')->send();
                }),
            Action::make('visszalep')->label('Visszalépett')->color('danger')->outlined()->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn () => $this->csapat()->status !== OlympicsStatus::Withdrawn && Gate::allows('update', $this->csapat()))
                ->requiresConfirmation()
                ->modalHeading('A csapat visszalépett?')
                ->modalDescription(fn () => 'A(z) „' . $this->csapat()->color_name . '” szín azonnal felszabadul, és újra választható lesz az űrlapon. Ez nem vonható vissza'
                    . ($this->csapat()->status === OlympicsStatus::Applied ? ' — a hely szabad lesz; a várólistán sorban következő csapatot te értesíted (a lista sorrendje mutatja).' : '.'))
                ->modalSubmitActionLabel('Igen, visszalépett')
                ->action(function () {
                    abort_unless(Gate::allows('update', $this->csapat()), 403);
                    $this->csapat()->update(['status' => OlympicsStatus::Withdrawn, 'status_manual' => true]);
                    Notification::make()->success()->title('Visszalépett — a szín felszabadult.')->send();
                }),
            DeleteAction::make()->modalDescription('A csapat minden adata törlődik (a színe felszabadul). Ha csak visszalépett, inkább a „Visszalépett” gombot használd — az megőrzi, ki jelentkezett.'),
        ];
    }
}
