<?php

namespace App\Livewire;

use App\Enums\Gyujtemeny;
use App\Models\Media;
use Awcodes\Curator\Components\Modals\CuratorPanel;
use Filament\Actions\Action;
use Livewire\Attributes\Locked;

/**
 * A Curator képválasztó-panelje + a feltöltés helyéből jövő gyűjtemény (CLAUDE.md 2/d).
 * A gyűjtemény a mező beállításából jön (App\Filament\Forms\MediaPicker), zárolt tulajdonság: a böngésző
 * nem írhatja át. Gyűjtemény nélkül (null) a feltöltött kép „Rendezetlen” marad.
 */
class MediaPanel extends CuratorPanel
{
    #[Locked]
    public ?string $gyujtemeny = null;

    /**
     * A Curator „Fájlok hozzáadása” művelete, egyetlen eltéréssel: a létrejött képek a gyűjteményükbe kerülnek.
     * (A Curator a feltöltést `self::createMediaFiles()`-szel hívja, ami felülírással nem érhető el — ezért a
     * művelet teste a CuratorPanel::addFilesAction() betűhű mása; csomagfrissítéskor összevetendő.)
     */
    public function addFilesAction(bool $insertAfter = false): Action
    {
        return parent::addFilesAction($insertAfter)
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
