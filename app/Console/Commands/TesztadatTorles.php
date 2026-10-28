<?php

namespace App\Console\Commands;

use App\Support\Tesztadat;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Minden tesztadat törlése egy lépésben (App\Support\Tesztadat) — ugyanaz, mint a Beállítások → Tesztadatok gomb.
 * Élesítés előtti ellenőrzőlistához: utána 0-nak kell lennie, különben hibával lép ki.
 */
#[Signature('forgatag:tesztadat-torles {--force : kérdés nélkül}')]
#[Description('Minden tesztadat (TESZT jelolesu hir es program) torlese')]
class TesztadatTorles extends Command
{
    public function handle(): int
    {
        if (Tesztadat::total() === 0) {
            $this->info('TESZTADAT: nincs mit törölni.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Törlöm: ' . Tesztadat::summary() . '. Biztos?')) {
            return self::FAILURE;
        }

        $torolt = Tesztadat::purge();
        $this->info('TESZTADAT: törölve — ' . collect($torolt)->map(fn ($db, $mi) => "{$db} {$mi}")->implode(', '));
        if (($maradt = Tesztadat::total()) > 0) {
            $this->error("TESZTADAT: bent maradt {$maradt} — ellenőrizd!");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
