<?php

namespace App\Console\Commands;

use App\Support\KezmuvesImport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * A 2026-os kézművesek behozatala (App\Support\KezmuvesImport). A telepítés futtatja, a médiatár-behozatal után; ha a tábla
 * már nem üres, csak kihagy. Hiányzó képnél nem ír semmit, hibával lép ki.
 */
#[Signature('forgatag:kezmuves-import')]
#[Description('A 2026-os kezmuvesek behozatala (csak ures tablaba)')]
class KezmuvesImportCommand extends Command
{
    public function handle(): int
    {
        try {
            (new KezmuvesImport)->run(fn (string $sor) => $this->line("KEZMUVESEK: {$sor}"));
        } catch (RuntimeException $e) {
            $this->error('KEZMUVESEK: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
