<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource as FilamentResource;
use Illuminate\Support\Str;

/**
 * Minden admin-lap közös alapja. A Filament angolosan nagybetűsíti a modellnevet
 * („Új Programnap”) — magyarul ez hibás, ezért itt, egy helyen kapcsoljuk ki.
 */
abstract class Resource extends FilamentResource
{
    protected static bool $hasTitleCaseModelLabel = false;

    /** A többes név lapcímként és morzsaként áll (mondat eleje): „Programok”, nem „programok”. */
    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }
}
