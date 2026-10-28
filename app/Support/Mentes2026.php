<?php

namespace App\Support;

use RuntimeException;

/**
 * A 2026-os PostgreSQL-mentés olvasója (MENTES-LELTAR.md) — EGY helyen; a fejlesztőgépen futó csomagolók
 * (forgatag:mediatar-csomag, forgatag:tartalom-csomag) ezt használják. A szerveren nem fut (a mentés nincs ott).
 */
class Mentes2026
{
    /**
     * A kért táblák sorai a mentésből (a `COPY ... FROM stdin` blokkokból).
     *
     * @param  list<string>  $kell
     * @return array<string, list<array<string, ?string>>>
     *
     * @throws RuntimeException ha a mentés csonka, vagy egy kért tábla hiányzik
     */
    public static function tablak(string $mentes, array $kell): array
    {
        $dump = glob("{$mentes}/adatbazis/*.sql.gz")[0] ?? throw new RuntimeException("nincs .sql.gz a(z) {$mentes}/adatbazis mappában");
        $out = [];
        $cur = null;
        $cols = [];
        $zart = false;
        $h = gzopen($dump, 'r') ?: throw new RuntimeException("nem nyitható meg: {$dump}");
        while (($line = gzgets($h)) !== false) {
            $line = rtrim($line, "\n");
            if ($cur === null) {
                if (preg_match('/^COPY public\.(\w+) \((.*)\) FROM stdin;$/', $line, $m) && in_array($m[1], $kell, true)) {
                    $cur = $m[1];
                    $cols = array_map(fn ($c) => trim($c, ' "'), explode(',', $m[2]));
                    $out[$cur] = [];
                } elseif (str_contains($line, 'PostgreSQL database dump complete')) {
                    $zart = true;
                }

                continue;
            }
            if ($line === '\\.') {
                $cur = null;

                continue;
            }
            $vals = array_map(fn ($v) => $v === '\\N' ? null : preg_replace_callback('/\\\\(.)/', fn ($x) => ['n' => "\n", 't' => "\t", 'r' => "\r"][$x[1]] ?? $x[1], $v), explode("\t", $line));
            $out[$cur][] = array_combine($cols, $vals);
        }
        gzclose($h);
        if (! $zart) {
            throw new RuntimeException('a mentés nem ér véget a „dump complete” jellel — csonka lehet.');
        }
        foreach ($kell as $k) {
            $out[$k] ?? throw new RuntimeException("a mentésben nincs „{$k}” tábla");
        }

        return $out;
    }
}
