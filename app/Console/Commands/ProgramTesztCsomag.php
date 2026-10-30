<?php

namespace App\Console\Commands;

use App\Support\ProgramTesztImport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * A 2026-os programok csomagolása TESZTADATNAK — a FEJLESZTŐGÉPEN, a mentésből (App\Support\ProgramTesztImport).
 *
 *   docker compose run --rm -v "E:/Work/mysites/forgatag.ro/MENTES-2026:/mentes" app \
 *     php artisan forgatag:program-teszt-csomag /mentes/2026-10-21
 */
#[Signature('forgatag:program-teszt-csomag {mentes : a mentés mappája (benne adatbazis/)}')]
#[Description('A 2026-os programok csomagolasa tesztadatnak a mentesbol (fejlesztogepen fut)')]
class ProgramTesztCsomag extends Command
{
    public function handle(): int
    {
        $db = ProgramTesztImport::csomagol(rtrim($this->argument('mentes'), '/'));
        $this->info('PROGRAM-TESZT: ' . collect($db)->map(fn ($n, $mi) => "{$n} {$mi}")->implode(', ') . ' → ' . ProgramTesztImport::CSOMAG);

        return self::SUCCESS;
    }
}
