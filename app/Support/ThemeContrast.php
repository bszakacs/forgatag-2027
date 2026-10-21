<?php

namespace App\Support;

/**
 * WCAG-kontraszt a témára (a 2026-os theme-css.ts szín-segédeinek átirata). A szabályok a
 * ThemeEditorSpec vezérlőin vannak (`contrast`); a szerkesztő élőben ugyanezt számolja
 * (public/js/tema-szerkeszto.js), a szerver publikáláskor ÚJRA — a böngészőnek nem hiszünk vakon.
 * Mindkét módot nézi (sötét, és világos, ha a szabály valamelyik színének van világos értéke).
 */
class ThemeContrast
{
    /** @return array{0: float, 1: float, 2: float, 3: float}|null */
    public static function parse(string $input): ?array
    {
        $s = trim($input);
        if (preg_match('/^#([0-9a-f]{3})$/i', $s, $m)) {
            $h = $m[1];

            return [hexdec($h[0] . $h[0]), hexdec($h[1] . $h[1]), hexdec($h[2] . $h[2]), 1.0];
        }
        if (preg_match('/^#([0-9a-f]{6})([0-9a-f]{2})?$/i', $s, $m)) {
            $h = $m[1];

            return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2)), isset($m[2]) ? hexdec($m[2]) / 255 : 1.0];
        }
        if (preg_match('/^rgba?\(([\d.]+)[ ,]+([\d.]+)[ ,]+([\d.]+)(?:[ ,\/]+([\d.]+))?\)$/i', $s, $m)) {
            return [(float) $m[1], (float) $m[2], (float) $m[3], isset($m[4]) && $m[4] !== '' ? (float) $m[4] : 1.0];
        }

        return null;
    }

    /** Fél-átlátszó szín ráfektetése egy alapra. */
    private static function composite(array $top, array $base): array
    {
        $a = $top[3] + $base[3] * (1 - $top[3]);
        if ($a == 0) {
            return [0, 0, 0, 0];
        }
        $ch = fn ($i) => ($top[$i] * $top[3] + $base[$i] * $base[3] * (1 - $top[3])) / $a;

        return [$ch(0), $ch(1), $ch(2), $a];
    }

    private static function lum(array $c): float
    {
        $f = function ($v) {
            $x = $v / 255;

            return $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $f($c[0]) + 0.7152 * $f($c[1]) + 0.0722 * $f($c[2]);
    }

    public static function ratio(string $fg, string $bg, ?string $behind = null): ?float
    {
        $f = self::parse($fg);
        $b = self::parse($bg);
        if ($f === null || $b === null) {
            return null;
        }
        if ($b[3] < 1 && $behind !== null && ($bh = self::parse($behind)) !== null) {
            $b = self::composite($b, $bh);
        }
        if ($f[3] < 1) {
            $f = self::composite($f, $b);
        }
        $l1 = self::lum($f);
        $l2 = self::lum($b);

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    /**
     * A szabálysértések listája (üres = minden rendben).
     *
     * @param  array<string, mixed>  $data
     * @return list<array{label: string, mode: string, ratio: float|null, min: float}>
     */
    public static function failures(array $data): array
    {
        $ki = [];
        foreach (ThemeEditorSpec::controls() as $c) {
            foreach ($c['contrast'] ?? [] as $r) {
                $modok = ['dark'];
                if (data_get($data, $c['path'] . '.light') !== null || data_get($data, $r['against'] . '.light') !== null) {
                    $modok[] = 'light';
                }
                foreach ($modok as $m) {
                    $fg = self::resolve($data, $c['path'], $m);
                    $bg = self::resolve($data, $r['against'], $m);
                    $behind = isset($r['behind']) ? self::resolve($data, $r['behind'], $m) : self::resolve($data, 'colors.bg', $m);
                    $ratio = ($fg !== null && $bg !== null) ? self::ratio($fg, $bg, $behind) : null;
                    if ($ratio === null || $ratio + 1e-9 < $r['min']) {
                        $ki[] = ['label' => $r['label'], 'mode' => $m, 'ratio' => $ratio === null ? null : round($ratio, 2), 'min' => (float) $r['min']];
                    }
                }
            }
        }

        return $ki;
    }

    /** Egy szín a megadott módban (a világos fél hiányában a közös/sötét érték). */
    private static function resolve(array $data, string $path, string $mode): ?string
    {
        $v = data_get($data, "{$path}.{$mode}") ?? data_get($data, "{$path}.dark");

        return is_string($v) ? $v : null;
    }
}
