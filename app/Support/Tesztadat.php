<?php

namespace App\Support;

use App\Models\Media;
use App\Models\NewsItem;
use App\Models\ProgramItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A TESZTADAT egyetlen nyilvántartása (tulajdonosi szabály, 2026-10-28): ami tesztelésre jön be (2026-os hír,
 * 2026-os program), `is_test` jelölést kap — az adminban TESZT jelvénnyel látszik, a nyitólapon figyelmeztetés
 * jelzi, hogy van, és EGY lépésben törölhető (Beállítások → „Tesztadatok”, vagy `php artisan forgatag:tesztadat-torles`).
 *
 * Miért fontos: élesítés előtt az adatbázist ürítjük — ha a tesztadatot kézzel kellene vadászni, a
 * legrosszabb eset egy kint maradt tavalyi teszthír az éles oldalon.
 * Új teszt-hordozó gyűjtemény = új sor a counts()-ban ÉS a purge()-ban (a teszt ellenőrzi, hogy mind nullára fut).
 */
class Tesztadat
{
    /** @return array<string, int> felirat → darab */
    public static function counts(): array
    {
        return [
            'hír' => NewsItem::where('is_test', true)->count(),
            // A sor-szintű szűkítés itt nem rejthet el semmit: a törlés mindenre vonatkozik.
            'program' => ProgramItem::withoutGlobalScopes()->where('is_test', true)->count(),
        ];
    }

    public static function total(): int
    {
        return array_sum(self::counts());
    }

    /** Emberi összegzés: „3 hír, 0 program”. */
    public static function summary(): string
    {
        return collect(self::counts())->map(fn (int $db, string $mi) => "{$db} {$mi}")->implode(', ');
    }

    /**
     * Minden tesztadat törlése, egy tranzakcióban. A teszt-programok időpontjai, fordításai és naplója az
     * adatbázis kaszkádjával megy (a napló append-only modellje így nem akad el); a zárt tárbeli fotójuk is.
     * A borítóképük a médiatárban marad, ha máshol is használják; ha nem, az is törlődik.
     *
     * @return array<string, int> mit törölt
     */
    public static function purge(): array
    {
        $torolve = self::counts();

        $fotok = [];
        $boritok = [];
        DB::transaction(function () use (&$fotok, &$boritok): void {
            $programok = ProgramItem::withoutGlobalScopes()->where('is_test', true);
            $fotok = (clone $programok)->whereNotNull('photo_path')->pluck('photo_path')->all();
            $boritok = array_merge(
                (clone $programok)->whereNotNull('cover_media_id')->pluck('cover_media_id')->all(),
                NewsItem::where('is_test', true)->whereNotNull('cover_media_id')->pluck('cover_media_id')->all(),
            );
            // Lekérdezés-szintű törlés: nem fut a modell őre, de a belépett felhasználó joga itt már eldőlt
            // (Beállítások lap / parancssor).
            $programok->toBase()->delete();
            NewsItem::where('is_test', true)->toBase()->delete();
        });

        foreach ($fotok as $ut) {
            Storage::disk('local')->delete($ut);
        }
        // A behozott (import2026) borítót NEM töröljük: a 2026-os képanyag része, a médiatárban marad.
        foreach (Media::whereIn('id', array_unique($boritok))->where('origin', '!=', 'import2026')->get() as $m) {
            if ($m->usages() === []) {
                $m->delete();
            }
        }

        return $torolve;
    }
}
