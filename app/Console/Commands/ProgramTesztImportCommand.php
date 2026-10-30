<?php

namespace App\Console\Commands;

use App\Support\ProgramTesztImport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * A 2026-os programok behozatala TESZTADATKÉNT (`is_test`), csak egyszer (App\Support\ProgramTesztImport).
 * A telepítés futtatja, a médiatár-behozatal után (a borítók onnan jönnek).
 */
#[Signature('forgatag:program-teszt-import')]
#[Description('A 2026-os programok behozatala tesztadatkent (csak egyszer)')]
class ProgramTesztImportCommand extends Command
{
    public function handle(): int
    {
        try {
            (new ProgramTesztImport)->run(fn (string $sor) => $this->line("PROGRAM-TESZT: {$sor}"));
        } catch (RuntimeException $e) {
            $this->error('PROGRAM-TESZT: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
