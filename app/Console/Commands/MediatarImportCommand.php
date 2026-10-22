<?php

namespace App\Console\Commands;

use App\Support\MediatarImport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * A 2026-os képanyag behozatala (App\Support\MediatarImport) + az arculati fájlok felvétele a médiatárba.
 * A telepítés (.cpanel.yml) minden alkalommal futtatja: csomag nélkül és üres tárral csak kihagy; ha már
 * minden bent van, csak ellenőriz. Hiánynál/hibánál HIBÁVAL lép ki (és a médiatár lapján piros jelentés).
 */
#[Signature('forgatag:mediatar-import')]
#[Description('A 2026-os kepanyag behozatala a feltoltott csomagbol (storage/app/import-2026)')]
class MediatarImportCommand extends Command
{
    public function handle(): int
    {
        try {
            $jelentes = (new MediatarImport())->run(fn (string $s) => $this->line('MÉDIATÁR: ' . $s));
        } catch (RuntimeException $e) {
            $this->error('MÉDIATÁR: ' . $e->getMessage());

            return self::FAILURE;
        }

        return ($jelentes['hibak'] ?? []) === [] ? self::SUCCESS : self::FAILURE;
    }
}
