<?php

namespace App\Console\Commands;

use App\Services\Forditas\GepiForditas;
use Illuminate\Console\Command;

/**
 * A gépi fordítás PÓTLÓ futása (cPanel cron, pl. 15 percenként) — ami a mentés utáni futásból kimaradt (hiba, zár,
 * megszakadt kérés), azt ez felveszi. Üres futásnál API-hívás nincs; kulcs nélkül csendben kilép (0-s kóddal).
 */
class ForditasCommand extends Command
{
    protected $signature = 'forgatag:forditas
        {--limit= : legfeljebb ennyi mezőt fordít (óvatos első éles kör)}
        {--proba : csak megszámolja, mi lenne a teendő — nem ír, nem hív API-t}';

    protected $description = 'A beküldött programok gépi fordítása (HU → RO, Claude API) — csak a fordítatlan és a megváltozott mezők.';

    public function handle(GepiForditas $forditas): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $j = $forditas->futtat(null, 'parancs', $limit, (bool) $this->option('proba'));

        $this->line('FORDÍTÁS: ' . GepiForditas::allapotSzoveg($j));
        $this->line("  naprakész: {$j['naprakesz']} · emberi fordítás megőrizve: {$j['emberi']} · most lett elavult: {$j['elavult_most']}");
        $this->line("  fordítandó: {$j['forditando']} · lefordítva: {$j['forditva']} · jelzéssel: {$j['jelzett']}");
        $this->line("  API-hívás: {$j['api_hivas']} (be {$j['token_be']}, ki {$j['token_ki']} token, kb. {$j['koltseg_usd']} USD)");
        if ($j['elavult_osszes'] > 0) {
            $this->warn("  ⚠ ELAVULT FORDÍTÁS: {$j['elavult_osszes']} mező vár EMBERI átnézésre (a román oldalon addig a magyar látszik).");
        }
        foreach ($j['figyelmeztetesek'] as $f) {
            $this->warn('  figyelem: ' . $f);
        }

        return $j['allapot'] === 'hiba' ? self::FAILURE : self::SUCCESS;
    }
}
