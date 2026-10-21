<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * A SOR-SZINTŰ JOG egyetlen forrása (CLAUDE.md 4.): melyik kategóriák programjait láthatja és
 * írhatja az éppen dolgozó admin-felhasználó.
 *
 * A szűkítés MODELL-szinten hat (ProgramScope + a gyerek-modellek ProgramChildScope-ja, és az írás-őr
 * a modellek saving/deleting eseményében), ezért minden felületre egyszerre érvényes: lista, globális
 * kereső, kapcsolat-kezelők, közvetlen cím, fotó-útvonal, export, import — azokra is, amelyek még nem
 * készültek el. Felületenkénti felülírás TILOS: az kihagyható, és a kihagyás néma adatszivárgás.
 *
 * Szivárgás-biztos alapállás: a szűkítés MINDEN kérésben hat, ahol az alapértelmezett őr belépett
 * felhasználója kategóriagazda. A beküldő lapok (BekuldoBelepve) a 'bekuldo' őrre állnak át — ott a
 * programgazda saját szűkítése érvényes, nem egy ugyanabban a böngészőben belépett admin fiókjáé.
 *
 * ⚠ Belépett felhasználó NÉLKÜL (parancssor, ütemező, sorba tett feladat) NINCS szűkítés. Ezért
 * export/import CSAK szinkron (QUEUE_CONNECTION=sync) futhat, vagy a feladatnak magának kell a
 * felhasználót beállítania (Auth::setUser) — lásd CLAUDE.md 4.
 */
final class RowAccess
{
    public const TILTVA = 'Ehhez a kategóriához nincs jogosultságod.';

    /** @return list<int>|null a látható kategóriák; null = nincs szűkítés */
    public static function categoryIds(): ?array
    {
        $u = Auth::user();
        if (! $u instanceof User || ! $u->role?->isRowRestricted()) {
            return null;
        }

        return $u->ownedCategoryIds(); // üres lista = SEMMIT nem lát (zárva, nem nyitva)
    }

    public static function allowsCategory(?int $categoryId): bool
    {
        $ids = self::categoryIds();

        return $ids === null || ($categoryId !== null && in_array($categoryId, $ids, true));
    }

    /** Írás-őr: idegen kategóriájú sort a szűkített felhasználó nem hozhat létre, nem módosíthat, nem törölhet. */
    public static function assertCategory(?int $categoryId): void
    {
        if (! self::allowsCategory($categoryId)) {
            throw new AuthorizationException(self::TILTVA);
        }
    }
}
