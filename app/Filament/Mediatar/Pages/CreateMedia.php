<?php

namespace App\Filament\Mediatar\Pages;

use App\Filament\Mediatar\MediatarResource;
use Awcodes\Curator\Resources\Media\Pages\CreateMedia as CuratorCreateMedia;

/**
 * Közvetlen feltöltés a médiatárba: saját forrás (origin = admin, licenc alapból „Saját forrás”), és
 * gyűjtemény nélkül — tehát „Rendezetlen” (CLAUDE.md 2/d). Utólag tehető gyűjteménybe.
 */
class CreateMedia extends CuratorCreateMedia
{
    protected static string $resource = MediatarResource::class;
}
