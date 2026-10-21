<?php

namespace App\Support;

use RuntimeException;

/** Publikálás kontraszt-hiba miatt megállítva (írásos felülbírálás nélkül). */
class ThemeContrastException extends RuntimeException
{
    /** @param list<array{label: string, mode: string, ratio: float|null, min: float}> $failures */
    public function __construct(public readonly array $failures)
    {
        $sorok = array_map(fn ($f) => "{$f['label']} (" . ($f['mode'] === 'dark' ? 'sötét' : 'világos') . '): '
            . ($f['ratio'] === null ? 'nem számolható' : number_format($f['ratio'], 2, ',', '') . ':1') . ", kell: {$f['min']}:1", $failures);
        parent::__construct('A kontraszt-őr megállította a publikálást — ' . implode(' · ', $sorok) . '. Írásos felülbírálással (legalább 10 karakter) mehet ki.');
    }
}
