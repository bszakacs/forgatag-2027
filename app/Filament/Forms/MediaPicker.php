<?php

namespace App\Filament\Forms;

use App\Enums\Gyujtemeny;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Actions\Action;
use Illuminate\Contracts\View\View;

/**
 * Képválasztó a tartalmak szerkesztőlapjára (hír, program, támogató, galéria...).
 *
 * A GYŰJTEMÉNY ABBÓL JÖN, HONNAN TÖLTÖTTÉK FEL (CLAUDE.md 2/d): ami ezen a mezőn keresztül kerül fel,
 * az magától a mezőhöz rendelt gyűjteménybe kerül — a felhasználónak nem kell semmit csinálnia.
 *   MediaPicker::make('logo_media_id')->gyujtemeny(Gyujtemeny::Tamogatok)
 * A már a tárban lévő kép kiválasztása NEM teszi át (csak az új feltöltés kapja a gyűjteményt).
 */
class MediaPicker extends CuratorPicker
{
    protected ?Gyujtemeny $gyujtemeny = null;

    public function gyujtemeny(Gyujtemeny $g): static
    {
        $this->gyujtemeny = $g;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->buttonLabel('Kép kiválasztása vagy feltöltése');
    }

    /**
     * A Curator saját panel-indítója, egyetlen eltéréssel: a panel a mi Livewire-komponensünk
     * (App\Livewire\MediaPanel), ami a gyűjteményt zárolt tulajdonságként kapja (a böngésző nem írhatja át).
     * A beállítás-lista a Curator CuratorPicker::getPickerAction()-jének betűhű mása — frissítéskor összevetendő.
     */
    public function getPickerAction(): Action
    {
        return parent::getPickerAction()
            ->modalContent(fn (MediaPicker $component): View => view('mediatar.picker-panel', [
                'key' => $component->getKey(),
                'settings' => [
                    'acceptedFileTypes' => $component->getAcceptedFileTypes(),
                    'defaultSort' => $component->getDefaultPanelSort(),
                    'directory' => $component->getDirectory(),
                    'diskName' => $component->getDiskName(),
                    'imageCropAspectRatio' => $component->getImageCropAspectRatio(),
                    'imageResizeMode' => $component->getImageResizeMode(),
                    'imageResizeTargetWidth' => $component->getImageResizeTargetWidth(),
                    'imageResizeTargetHeight' => $component->getImageResizeTargetHeight(),
                    'isLimitedToDirectory' => $component->isLimitedToDirectory(),
                    'isTenantAware' => $component->isTenantAware(),
                    'tenantOwnershipRelationshipName' => $component->getTenantOwnershipRelationshipName(),
                    'isMultiple' => $component->isMultiple(),
                    'maxItems' => $component->getMaxItems(),
                    'maxSize' => $component->getMaxSize(),
                    'minSize' => $component->getMinSize(),
                    'pathGenerator' => $component->getPathGenerator(),
                    'rules' => $component->getPanelValidationRules(),
                    'selected' => (array) $component->getState(),
                    'shouldPreserveFilenames' => $component->shouldPreserveFilenames(),
                    'statePath' => $component->getStatePath(),
                    'visibility' => $component->getVisibility(),
                    'gyujtemeny' => $component->gyujtemeny?->value,
                ],
            ]));
    }
}
