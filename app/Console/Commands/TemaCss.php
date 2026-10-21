<?php

namespace App\Console\Commands;

use App\Support\Theme;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * A kint lévő téma CSS-fájljának kiírása (public/arculat/tema-<lenyomat>.css), ha még nincs meg.
 * A telepítés (.cpanel.yml) minden alkalommal futtatja: ha a generátor kódja változott, a lenyomat
 * és így a fájlnév is új — ezt itt írjuk ki, nem a látogató első kérése.
 */
#[Signature('forgatag:tema-css')]
#[Description('A publikalt tema CSS-fajljanak kiirasa (ha meg nincs meg)')]
class TemaCss extends Command
{
    public function handle(): int
    {
        try {
            $this->info('TÉMA-CSS: ' . Theme::ensureCss());
        } catch (RuntimeException $e) {
            $this->error('TÉMA-CSS: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
