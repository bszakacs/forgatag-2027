<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\SubmissionForm;
use App\Support\Tesztadat;
use Filament\Widgets\Widget;

/**
 * Piros figyelmeztetések a nyitólapon — amit NEM szabad némán hagyni (CLAUDE.md 3.):
 *  · a beküldő űrlap extra mezői nem létező kategóriára hivatkoznak (5. adósság — törölt/átnevezett kategória),
 *  · tesztadat van az adatbázisban (élesítés előtt törlendő: Beállítások → Tesztadatok).
 * Csak az látja, aki tenni is tud ellene (UserRole::canEditSettings), és csak ha van mit mutatni.
 */
class Figyelmeztetesek extends Widget
{
    protected static ?int $sort = -10;

    /** Azonnal, a lappal együtt jelenjen meg (ne utólag töltődjön) — figyelmeztetésnél ez a lényeg. */
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.figyelmeztetesek';

    public static function canView(): bool
    {
        $u = auth('web')->user();

        return $u instanceof User && $u->role->canEditSettings() && self::uzenetek() !== [];
    }

    /** @return list<array{cim: string, szoveg: string}> */
    public static function uzenetek(): array
    {
        $u = [];
        if (($rossz = SubmissionForm::unknownSlugs()) !== []) {
            $u[] = [
                'cim' => 'A beküldő űrlap nem létező kategóriára hivatkozik',
                'szoveg' => 'Ezeknek a kategóriáknak az extra mezői hatástalanok, mert a kategória nincs meg (törölték vagy átnevezték): '
                    . implode(', ', $rossz) . '. A beküldő ezeknél csak az alapmezőket kapja. Javítás: a kategória visszaállítása, vagy a fejlesztő igazítja az űrlap leírását (App\Support\SubmissionForm).',
            ];
        }
        if (($db = Tesztadat::total()) > 0) {
            $u[] = [
                'cim' => "Tesztadat van az adatbázisban ({$db} db)",
                'szoveg' => Tesztadat::summary() . ' — tesztelésre behozva, TESZT jelvénnyel. Élesítés előtt törlendő: Beállítások → Tesztadatok.',
            ];
        }

        return $u;
    }
}
