<?php

namespace App\Console\Commands;

use App\Support\HelyszinTerkepImport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * A 2026-os helyszín-térképadatok csomagolása — a FEJLESZTŐGÉPEN, a mentésből (App\Support\HelyszinTerkepImport).
 *
 *   docker compose run --rm -v "E:/Work/mysites/forgatag.ro/MENTES-2026:/mentes" app \
 *     php artisan forgatag:helyszin-terkep-csomag /mentes/2026-10-21
 */
#[Signature('forgatag:helyszin-terkep-csomag {mentes : a mentés mappája (benne adatbazis/)}')]
#[Description('A 2026-os helyszinek terkep-mezoinek csomagolasa a mentesbol (fejlesztogepen fut)')]
class HelyszinTerkepCsomag extends Command
{
    public function handle(): int
    {
        $db = HelyszinTerkepImport::csomagol(rtrim($this->argument('mentes'), '/'));
        $this->info('HELYSZÍN-TÉRKÉP: ' . collect($db)->map(fn ($n, $mi) => "{$n} {$mi}")->implode(', ') . ' → ' . HelyszinTerkepImport::CSOMAG);

        return self::SUCCESS;
    }
}
