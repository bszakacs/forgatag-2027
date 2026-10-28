<?php

namespace App\Console\Commands;

use App\Support\TartalomImport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * A 2026-os menü, aloldalak, videók és teszthírek behozatala (App\Support\TartalomImport). A telepítés futtatja,
 * a médiatár-behozatal után; a második futástól csak kihagy. Hiányzó kép esetén nem ír semmit, hibával lép ki.
 */
#[Signature('forgatag:tartalom-import')]
#[Description('A 2026-os menu, aloldalak es teszthirek behozatala (csak egyszer)')]
class TartalomImportCommand extends Command
{
    public function handle(): int
    {
        try {
            (new TartalomImport)->run(fn (string $sor) => $this->line("TARTALOM: {$sor}"));
        } catch (RuntimeException $e) {
            $this->error('TARTALOM: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
