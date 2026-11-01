<?php

namespace App\Console\Commands;

use App\Support\HelyszinTerkepImport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/** A 2026-os helyszín-térképadatok behozatala (csak az érintetlen helyszínekbe; ismételhető). A telepítés része. */
#[Signature('forgatag:helyszin-terkep-import')]
#[Description('A 2026-os helyszin-terkepadatok behozatala (csak az ures helyszinekbe)')]
class HelyszinTerkepImportCommand extends Command
{
    public function handle(): int
    {
        try {
            (new HelyszinTerkepImport)->run(fn (string $sor) => $this->line("HELYSZÍN-TÉRKÉP: {$sor}"));
        } catch (RuntimeException $e) {
            $this->error('HELYSZÍN-TÉRKÉP: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
