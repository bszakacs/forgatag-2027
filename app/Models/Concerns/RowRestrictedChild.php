<?php

namespace App\Models\Concerns;

use App\Models\Scopes\ProgramChildScope;

/**
 * A program gyerek-sorai (időpont, fordítás, napló): olvasásra és írásra is a program
 * sor-szintű joga érvényes (App\Models\Scopes\RowAccess). A modellnek programItem() kapcsolat kell.
 */
trait RowRestrictedChild
{
    public static function bootRowRestrictedChild(): void
    {
        static::addGlobalScope(new ProgramChildScope);
        static::saving(fn ($m) => ProgramChildScope::guard($m));
        static::deleting(fn ($m) => ProgramChildScope::guard($m));
    }
}
