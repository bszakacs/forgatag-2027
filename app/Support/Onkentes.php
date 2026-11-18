<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Az önkéntes-jelentkezés beállításai — EGY helyen (Beállítások → „Önkéntesek” blokk, `volunteer`).
 *
 * ⚠ Az ÖNKÉNTES-NAPOK SAJÁT LISTA, nem a programnapok: van benne egy „nulladik nap” (a nyitás előtti) és egy „utómunkálatok”
 * nap is (2026: aug. 25. kedd – nulladik nap … aug. 31. hétfő – utómunkálatok, a fájlba égetve). A szervezők a Beállításokban
 * adják meg, a napokhoz írt megjegyzéssel együtt.
 */
class Onkentes
{
    /**
     * Az adatkezelési hozzájárulás — SZÓ SZERINT a 2026-os űrlap szövege. A rekordba is lementjük (bizonyíték) — nem
     * szerkeszthető, nem fogalmazható át.
     */
    public const HOZZAJARULAS = 'Hozzájárulok, hogy a Vásárhelyi Forgatag szervezői a megadott adataimat az önkéntes-szervezés céljából kezeljék.';

    /** Étkezési érzékenység — a 2026-os választék (az „Egyéb” szabad szöveg külön). */
    public const ETREND = ['Vegán', 'Vegetáriánus', 'Laktózérzékeny', 'Gluténérzékeny'];

    /** A feltölthető dokumentum: PDF/JPG/PNG, legfeljebb 10 MB (2026: FILE_RULES). A típust a TARTALOMBÓL döntjük el. */
    public const FAJL_TIPUSOK = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public const FAJL_MAX_MB = 10;

    public const FAJL_MAX_BYTES = self::FAJL_MAX_MB * 1024 * 1024;

    /** A zárt tár (storage/app/private) mappája — soha nem a nyilvános médiatár. */
    public const MAPPA = 'onkentesek';

    public static function nyitva(): bool
    {
        return (bool) Settings::get('volunteer.open', false);
    }

    public static function zartUzenet(): string
    {
        return (string) Settings::get('volunteer.closed_message');
    }

    /** Ez alatti életkorban a szülői hozzájárulás ÉS a motivációs levél kötelező (2026: 16). */
    public static function korhatar(): int
    {
        return max(1, (int) Settings::get('volunteer.minor_age', 16));
    }

    public static function ev(): int
    {
        return Festival::year();
    }

    /**
     * Az önkéntes-napok időrendben, a 2026-os felirattal: „aug. 25. (kedd – nulladik nap)”.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function napok(): array
    {
        $napok = array_filter((array) Settings::get('volunteer.days', []), fn ($n) => filled($n['date'] ?? null));
        usort($napok, fn ($a, $b) => strcmp((string) $a['date'], (string) $b['date']));

        return array_values(array_map(fn ($n) => [
            'value' => CarbonImmutable::parse($n['date'])->toDateString(),
            'label' => self::napFelirat(CarbonImmutable::parse($n['date']), (string) ($n['note'] ?? '')),
        ], $napok));
    }

    public static function napFelirat(CarbonImmutable $nap, string $megjegyzes = ''): string
    {
        $d = $nap->locale('hu');

        return $d->isoFormat('MMM') . ' ' . $d->day . '. (' . Festival::weekday($nap, 'hu')
            . (filled(trim($megjegyzes)) ? ' – ' . trim($megjegyzes) : '') . ')';
    }

    /** Egy tárolt dátum felirata (az adminnak) — a jelenlegi listából, ha már nincs benne, a puszta dátumból. */
    public static function napFeliratDatumbol(string $datum): string
    {
        foreach (self::napok() as $n) {
            if ($n['value'] === $datum) {
                return $n['label'];
            }
        }

        return self::napFelirat(CarbonImmutable::parse($datum));
    }

    /** A segítési területek (2026: AREAS — most a Beállításokban). @return list<string> */
    public static function teruletek(): array
    {
        return array_values(array_filter(array_map('trim', (array) Settings::get('volunteer.areas', [])), fn ($s) => $s !== ''));
    }

    /**
     * Az önkéntes-napok időszaka a bevezetőhöz: „2027. augusztus 24–30.” (a 2026-os „2026. augusztus 25–31.” alakja).
     * Ha a napok két hónapra esnek: „2027. július 31. – augusztus 2.”. Napok nélkül null.
     */
    public static function idoszak(): ?string
    {
        $napok = self::napok();
        if ($napok === []) {
            return null;
        }
        $a = CarbonImmutable::parse($napok[0]['value'])->locale('hu');
        $b = CarbonImmutable::parse($napok[array_key_last($napok)]['value'])->locale('hu');
        if ($a->equalTo($b)) {
            return $a->year . '. ' . $a->isoFormat('MMMM') . ' ' . $a->day . '.';
        }

        return $a->month === $b->month
            ? $a->year . '. ' . $a->isoFormat('MMMM') . ' ' . $a->day . '–' . $b->day . '.'
            : $a->year . '. ' . $a->isoFormat('MMMM') . ' ' . $a->day . '. – ' . $b->isoFormat('MMMM') . ' ' . $b->day . '.';
    }

    /**
     * A tárhely PHP-ja elbírja-e a két 10 MB-os dokumentumot egy kérésben. Ha nem, a nagy fájl a szerverig sem jut el —
     * a Beállításokban kiírjuk (néma hiba tilos), ne élesben derüljön ki.
     */
    public static function feltoltesiKorlatFigyelmeztetes(): ?string
    {
        $bajt = fn (string $v) => (int) $v * match (strtoupper(substr(trim($v), -1))) {
            'G' => 1024 ** 3, 'M' => 1024 ** 2, 'K' => 1024, default => 1,
        };
        $file = $bajt((string) ini_get('upload_max_filesize'));
        $post = $bajt((string) ini_get('post_max_size'));
        $kell = 2 * self::FAJL_MAX_BYTES + 1024 * 1024;

        if ($file >= self::FAJL_MAX_BYTES && ($post === 0 || $post >= $kell)) {
            return null;
        }

        return 'FIGYELEM: a tárhely PHP-ja most legfeljebb ' . ini_get('upload_max_filesize') . ' fájlt / ' . ini_get('post_max_size')
            . ' kérést fogad, a 16 év alatti jelentkező pedig két, egyenként ' . self::FAJL_MAX_MB . ' MB-os dokumentumot is feltölthet. '
            . 'A cPanel „Select PHP Version” → Options lapján: upload_max_filesize legalább 12M, post_max_size legalább 24M.';
    }
}
