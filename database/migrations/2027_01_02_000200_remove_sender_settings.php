<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Egy funkció = egy cím (tulajdonosi döntés, 2026-10-12): a Beállításokból kikerül a „Feladó cím”,
 * a „Feladó neve” (a feladó a szerver SMTP-beállítása, .env) és a „Kapcsolattartó cím a beküldőknek”
 * (a beküldő válasza a koordinátorhoz megy). Itt a már TÁROLT értékek is törlődnek (négy-hely szabály).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->torol('notifications', ['from_address', 'from_name']);
        $this->torol('submission', ['contact_email']);
    }

    /** @param list<string> $mezok */
    private function torol(string $blokk, array $mezok): void
    {
        $sor = DB::table('settings')->where('key', $blokk)->first();
        if (! $sor) {
            return;
        }
        $ertek = json_decode($sor->value, true) ?? [];
        foreach ($mezok as $m) {
            unset($ertek[$m]);
        }
        DB::table('settings')->where('key', $blokk)
            ->update(['value' => json_encode($ertek, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    }
};
