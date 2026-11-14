<?php

namespace App\Livewire;

use App\Enums\Gyujtemeny;
use App\Models\Media;
use Awcodes\Curator\Components\Modals\CuratorPanel;
use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;

/**
 * A Curator képválasztó-panelje + a feltöltés helyéből jövő gyűjtemény (CLAUDE.md 2/d).
 * A gyűjtemény a mező beállításából jön (App\Filament\Forms\MediaPicker), zárolt tulajdonság: a böngésző
 * nem írhatja át. Gyűjtemény nélkül (null) a feltöltött kép „Rendezetlen” marad.
 *
 * A feltöltés UTÁNI két gomb (a Curatorban „Fájlok hozzáadása” és „Beillesztés”) valójában ezt csinálja:
 *  · BEFEJEZŐ (addInsertFiles): a feltöltött képeket a médiatárba menti, BETESZI a mezőbe (pl. a galériába),
 *    és bezárja az ablakot — ez a szokásos befejezés, ezért ez az elsődleges, elöl álló gomb.
 *  · MÁSODLAGOS (addFiles): csak a médiatárba menti (kijelölve), a mezőbe NEM teszi be, az ablak nyitva marad.
 * A feliratokat ehhez igazítottuk (tulajdonosi kérés, 2026-11-14): ránézésre látszódjon, melyik zár.
 */
class MediaPanel extends CuratorPanel
{
    #[Locked]
    public ?string $gyujtemeny = null;

    /** A befejező gomb felirata a mezőhöz igazítva, pl. „Beillesztés a galériába” (MediaPicker::befejezoFelirat). */
    #[Locked]
    public ?string $befejezo = null;

    public function render(): View
    {
        return view('mediatar.curator-panel');
    }

    /** Hány feltöltött kép vár beillesztésre (a feltöltő mezőben lévők). */
    public function varakozo(): int
    {
        return count($this->form->getRawState()['files_to_add'] ?? []);
    }

    public function varakozoSzoveg(): string
    {
        return $this->varakozo() . ' feltöltött kép vár beillesztésre.';
    }

    public function befejezoSugo(): string
    {
        return '„' . $this->befejezoAlap() . '”: a képek a médiatárba ÉS ide kerülnek, az ablak bezárul'
            . ' (utána a lapon nyomd meg a Mentést). „Csak a médiatárba”: ide nem kerülnek be, az ablak nyitva marad.';
    }

    private function befejezoAlap(): string
    {
        return filled($this->befejezo) ? $this->befejezo : 'Beillesztés';
    }

    /** A BEFEJEZŐ gomb: mentés + betétel + bezárás → elsődleges szín, a felirat megmondja, mi történik. */
    public function addInsertFilesAction(): Action
    {
        return parent::addInsertFilesAction()
            ->color('primary')
            ->icon('heroicon-m-check')
            ->label(fn (): string => $this->befejezoAlap() . ($this->isMultiple ? ' (' . $this->varakozo() . ' kép)' : ''));
    }

    /** A tárból kijelölt (nem feltöltött) képek betétele — ugyanaz a befejező felirat. */
    public function insertMediaAction(): Action
    {
        return parent::insertMediaAction()
            ->color('primary')
            ->label(fn (): string => $this->befejezoAlap());
    }

    /**
     * A Curator „Fájlok hozzáadása” művelete, egy eltéréssel: a létrejött képek a gyűjteményükbe kerülnek.
     * (A Curator a feltöltést `self::createMediaFiles()`-szel hívja, ami felülírással nem érhető el — ezért a
     * művelet teste a CuratorPanel::addFilesAction() betűhű mása; csomagfrissítéskor összevetendő.)
     * Önmagában (nem a befejező gomb részeként) ez a MÁSODLAGOS „Csak a médiatárba” gomb.
     */
    public function addFilesAction(bool $insertAfter = false): Action
    {
        $action = parent::addFilesAction($insertAfter)
            ->action(function () use ($insertAfter): void {
                $media = $this->createMediaFiles();
                $this->gyujtemenybe($media);

                $this->form->fill();

                $this->files = [
                    ...$media,
                    ...$this->files,
                ];

                $this->selected = $this->isMultiple
                    ? [...$this->selected, ...$media]
                    : array_slice($media, 0, 1);

                if ($insertAfter) {
                    $this->dispatchInsertMedia();
                }
            });

        return $insertAfter ? $action : $action->color('gray')->outlined()->label('Csak a médiatárba');
    }

    /** @param array<int, array<string, mixed>> $letrejott */
    private function gyujtemenybe(array $letrejott): void
    {
        $g = Gyujtemeny::tryFrom((string) $this->gyujtemeny);
        if ($g === null) {
            return;
        }
        foreach ($letrejott as $m) {
            Media::find($m['id'])?->addToCollection($g);
        }
    }
}
