<?php

namespace App\Filament\Widgets;

use App\Models\ProgramItem;
use App\Models\User;
use App\Services\Forditas\GepiForditas;
use App\Support\MediaLapok;
use App\Support\SubmissionForm;
use App\Support\Tesztadat;
use Filament\Widgets\Widget;

/**
 * Piros figyelmeztetések a nyitólapon — amit NEM szabad némán hagyni (CLAUDE.md 3.):
 *  · a beküldő űrlap extra mezői nem létező kategóriára hivatkoznak (5. adósság — törölt/átnevezett kategória),
 *  · a Videó archívum lapon a harmadik utáni videós blokk nem látszik (a lap 2026 óta három sávos — MediaLapok::SAVOK),
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
        if (($tobblet = MediaLapok::tobbletBlokkok()) > 0) {
            $u[] = [
                'cim' => "A Videó archívum lapon {$tobblet} videós blokk nem látszik",
                'szoveg' => 'A lap a 2026-os felépítés szerint három sávot mutat: az első három videós blokkot (Aftermovie-k, Vásárhely színe-java, '
                    . 'Mesterségem). A negyedik és a további blokkok videói nem jelennek meg. Javítás: Tartalom → Aloldalak → Videó archívum — '
                    . 'a videókat a meglévő három blokk valamelyikébe kell tenni.',
            ];
        }
        // GÉPI FORDÍTÁS (PROMPT-19): a hiba, a kikapcsolt gép és az ELAVULT fordítás nem lehet néma.
        $futas = GepiForditas::utolsoFutas();
        if (($futas['allapot'] ?? null) === 'hiba') {
            $u[] = [
                'cim' => 'A gépi fordítás legutóbbi futása hibával állt meg',
                'szoveg' => ($futas['hiba'] ?? '') . ' — ami a hiba előtt elkészült, az bent van; a többit a következő futás pótolja. Részletek: Beállítások → Gépi fordítás.',
            ];
        }
        if (! GepiForditas::vanKulcs()) {
            $u[] = [
                'cim' => 'A gépi fordítás ki van kapcsolva',
                'szoveg' => 'Nincs ANTHROPIC_API_KEY a szerveri .env-ben, ezért a beküldött programokhoz nem készül román fordítás (a román oldalon a magyar szöveg látszik). A kulcsot a tulajdonos írja be.',
            ];
        }
        if (($elavult = ProgramItem::query()->withOutdatedTranslation()->count()) > 0) {
            $u[] = [
                'cim' => "{$elavult} program román fordítása ELAVULT",
                'szoveg' => 'A magyar szöveg a fordítás óta megváltozott; a román oldalon addig a magyar látszik. Programok → „⚠ Elavult fordítás” fül → a program lapján „Román fordítás”.',
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
