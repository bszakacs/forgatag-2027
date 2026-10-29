<?php

namespace App\Console\Commands;

use App\Support\LapSablon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * A publikus sablonok szerkeszthető szövegeinek igazítása a leíráshoz (App\Support\LapSablon). A telepítés
 * futtatja, a tartalom-behozatal után; ismételhető, kitöltött értékhez nem nyúl.
 */
#[Signature('forgatag:lap-szovegek')]
#[Description('A lapok szerkesztheto szovegei: hianyzo kulcsok letrehozasa a sablon alapszovegevel')]
class LapSzovegekCommand extends Command
{
    public function handle(): int
    {
        try {
            foreach (LapSablon::szinkron() as $sor) {
                $this->line("LAPSZÖVEG: {$sor}");
            }
        } catch (RuntimeException $e) {
            $this->error('LAPSZÖVEG: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
