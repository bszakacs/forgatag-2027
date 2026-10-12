<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource as FilamentResource;

/**
 * Minden admin-lap közös alapja. A Filament angolosan nagybetűsíti a modellnevet
 * („Új Programnap”) — magyarul ez hibás, ezért itt, egy helyen kapcsoljuk ki.
 */
abstract class Resource extends FilamentResource
{
    protected static bool $hasTitleCaseModelLabel = false;
}
