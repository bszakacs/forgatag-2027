<?php

namespace App\Support;

use RuntimeException;

/**
 * A `public/` alatti CSS- és JS-fájlok címe GYORSÍTÓTÁR-TÖRŐ jelöléssel — EGY helyen (2026-11-01).
 *
 * A szerver a statikus fájlokat napokig gyorsítótárazza (élesben mérve: `max-age=604800`, 7 nap), ezért egy stílus-
 * módosítás csak akkor ér el a látogatóhoz, ha a fájl CÍME is változik. A téma-CSS ezt a fájlnévben kapja (a
 * tartalom lenyomata, Theme::cssUrl); minden más publikus CSS/JS itt kapja ugyanezt a címsorban: `?v=<a TARTALOM
 * lenyomata>`. A tartalomból és nem a módosítás idejéből — az idő telepítéskor nem megbízható (változatlan fájlnál is
 * változhat, és fordítva), a tartalom igen.
 *
 * Használat a sablonban: `@eszkoz('css/programkereso.css')` (a direktíva: AppServiceProvider); PHP-ban `Eszkoz::url()`.
 * Hiányzó fájl → HIBA (néma hiba tilos: egy elgépelt név különben csak egy stílus nélküli lapként látszana).
 * A tests/Feature/EszkozTest.php őrzi, hogy sablonban ne maradjon ezen kívüli CSS/JS-hivatkozás.
 */
final class Eszkoz
{
    /** @var array<string, string> kérésenként egyszer számolt lenyomatok */
    private static array $lenyomat = [];

    public static function url(string $ut): string
    {
        return asset($ut) . '?v=' . self::lenyomat($ut);
    }

    public static function lenyomat(string $ut): string
    {
        return self::$lenyomat[$ut] ??= substr((string) (@md5_file(public_path($ut))
            ?: throw new RuntimeException("Hiányzó publikus fájl: public/{$ut}")), 0, 12);
    }
}
