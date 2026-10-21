<?php

namespace App\Support;

use RuntimeException;

/**
 * Téma-adat → CSS. EGYETLEN forrás minden téma-tulajdonú tokenhez (szín, betű, forma, mozgás, firka).
 * A 2026-os frontend/src/lib/theme-css.ts (themeToCss) átirata, szándékos eltérésekkel:
 *  · SZIGORÚ: ha egy kötelező mező hiányzik, hibával áll meg — nincs csendes beépített tartalék
 *    (CLAUDE.md 2/c). 2026-ban a generátor a hiányzó mezőt kihagyta, és a lap CSS-e „pótolta”.
 *  · A „Komponensek” szekció megszűnt: a „MA” jelvény színei a színek közé kerültek
 *    (colors.badgeMaBg / badgeMaFg), a fejléc üveghatása a fejléchez (header.navbarBlur).
 *  · A kategóriacsoport-színek (colors.groups, --grp-*) MEGSZŰNTEK (tulajdonosi döntés, 2026-10-21):
 *    a csoport-fogalmat 2026 júliusában elhagyták, a hét szín azóta gazdátlan volt, és betűre
 *    egyezett a firka-palettával. Díszítőszín a --doodle-* tokenekből jön.
 *  · VILÁGOS mód: a LIGHT_COLORS tokeneknek, az akcentnek és a fénynek `light` fele is van (a 2026-os
 *    beküldő űrlap világos palettája) → `:root[data-theme="light"]{…}`. A többi token közös.
 * A kimenet determinisztikus: ugyanabból az adatból betűre ugyanaz a CSS (erre épül a fájlnév-lenyomat).
 * A JS-párja: public/js/tema-css.js — BETŰRE ugyanazt kell adnia; a szerkesztő ezt minden
 * betöltéskor ellenőrzi, és eltérésnél figyelmeztet (az élő előnézet ebből fut, szerver nélkül).
 */
class ThemeCss
{
    /**
     * Az önhostolt betűk (public/fonts) — a stack végi rendszer-betű pótolja a román ș/ț-t, ahol hiányzik.
     * `wght`: a TÉNYLEG meglévő vastagság-tartomány, `italic`: van-e valódi dőlt változat, `wdth`: van-e
     * szélesség-tengely. Ezekből számol a generátor, hogy SOHA ne kérjünk nem létező vastagságot/dőlést —
     * mert akkor a böngésző mesterségesen rajzolja meg, és Androidon körvonalas lesz a betű (a Tingler
     * Printtel 2026-ban konkrét hiba volt).
     */
    public const FONTS = [
        'Archivo' => ['stack' => "'Archivo',sans-serif", 'preload' => 'fonts/Archivo-var-latin.woff2', 'wght' => [100, 900], 'italic' => false, 'wdth' => true],
        'Instrument Serif' => ['stack' => "'Instrument Serif',serif", 'preload' => 'fonts/InstrumentSerif-italic-latin.woff2', 'wght' => [400, 400], 'italic' => true, 'wdth' => false],
        'Hanken Grotesk' => ['stack' => "'Hanken Grotesk',system-ui,'Segoe UI',Roboto,sans-serif", 'preload' => 'fonts/HankenGrotesk-400-latin.woff2', 'wght' => [400, 800], 'italic' => false, 'wdth' => false],
        'Tingler Print' => ['stack' => "'Tingler Print',system-ui,'Segoe UI',Roboto,sans-serif", 'preload' => 'fonts/TinglerPrint-Regular.woff2', 'wght' => [400, 400], 'italic' => false, 'wdth' => false],
    ];

    public const DENSITY_MUL = ['compact' => 0.85, 'default' => 1, 'airy' => 1.15];

    /** Színtokenek: CSS-név => adat-útvonal (a pár `dark` fele; a világos fél: LIGHT_COLORS). */
    public const COLORS = [
        '--bg' => 'colors.bg', '--bg-raise' => 'colors.bgRaise', '--panel' => 'colors.panel',
        '--card' => 'colors.card', '--card-hover' => 'colors.cardHover',
        '--line' => 'colors.line', '--line-strong' => 'colors.lineStrong',
        '--fg' => 'colors.fg', '--fg-muted' => 'colors.fgMuted', '--fg-faint' => 'colors.fgFaint',
        '--lap-bg' => 'colors.lapBg', '--lap-card' => 'colors.lapCard', '--lap-fg' => 'colors.lapFg',
        '--lap-muted' => 'colors.lapMuted', '--lap-line' => 'colors.lapLine',
        '--accent-contrast' => 'colors.accentContrast',
        '--focus' => 'colors.focus', '--ok' => 'colors.ok', '--warn' => 'colors.warn', '--danger' => 'colors.danger',
        '--badge-ma-bg' => 'colors.badgeMaBg', '--badge-ma-fg' => 'colors.badgeMaFg',
    ];

    /** Azok a színtokenek, amelyeknek világos módban saját értékük van (`.light` — kötelező). */
    public const LIGHT_COLORS = [
        '--bg', '--bg-raise', '--panel', '--card', '--card-hover', '--line', '--line-strong',
        '--fg', '--fg-muted', '--fg-faint', '--accent-contrast', '--focus', '--ok', '--warn', '--danger',
    ];

    public const DOODLE_COLORS = ['green', 'lime', 'orange', 'red', 'pink', 'yellow', 'blue', 'purple'];

    /*
     * A firka-dekor jelölése a lapokon (2027-es szerződés, minden lapra ugyanaz):
     *  · `[data-decor="hero"]` — a nyitókép/fejléc-sáv dekor-rétege,
     *  · `[data-decor="ambient"]` — a lapon szórt dekor-réteg,
     *  · `.decor` — egy firka-elem bármelyik rétegben,
     *  · `.pc-doodle` — a fotó nélküli kártyák sarok-firkája.
     */

    /** @param array<string, mixed> $doc */
    public static function generate(array $doc): string
    {
        $vars = [];
        $extra = [];
        $hianyzik = [];

        $kell = function (string $path) use ($doc, &$hianyzik) {
            $v = data_get($doc, $path);
            if ($v === null || $v === '' || $v === []) {
                $hianyzik[] = $path;
            }

            return $v;
        };
        $szin = function (string $path, string $mod = 'dark') use ($kell) {
            $v = $kell($path . '.' . $mod);

            return is_string($v) ? self::tiszta($v, $path . '.' . $mod) : null;
        };

        $vars['color-scheme'] = 'dark';
        foreach (self::COLORS as $var => $path) {
            $vars[$var] = $szin($path);
        }

        // Akcent-gradiens (mindkét módra). A `-text` változat szándékosan kétállomású (a harmadik,
        // sötét stop a gradienses szöveg végét olvashatatlanná tenné).
        $angle = $kell('colors.accent.angle');
        $deg = $angle !== null ? self::szam($angle) . 'deg' : '';
        $akcent = function (string $mod) use ($kell, $szin, $deg, &$hianyzik): array {
            $acc = $kell("colors.accent.{$mod}");
            $from = $szin('colors.accentGradFrom', $mod);
            if (! is_array($acc) || count($acc) !== 3) {
                $hianyzik[] = "colors.accent.{$mod} (3 szín)";

                return [];
            }
            [$a, $b, $c] = array_map(fn ($x) => self::tiszta((string) $x, "colors.accent.{$mod}"), array_values($acc));

            return [
                '--accent-a' => $a, '--accent-b' => $b, '--accent-c' => $c,
                '--accent-grad-from' => $from,
                '--accent-grad' => "linear-gradient({$deg},{$from} 0%,{$b} 55%,{$c} 100%)",
                '--accent-grad-text' => "linear-gradient({$deg},{$from},{$b})",
            ];
        };
        $vars += $akcent('dark');
        $vars['--accent-angle'] = $deg;
        // A link színe: sötéten az „a” akcent, világoson a „c” (a 2026-os beküldő űrlap szerint —
        // a világos „a” fehéren nem éri el a 4.5:1-et).
        $vars['--link'] = 'var(--accent-a)';

        // „Nappali lap” sáv: a lap- és firka-tokenekből keverve (a /tema erre is hat).
        $vars['--lap-band-bg'] = 'linear-gradient(180deg,'
            . 'color-mix(in srgb,var(--lap-bg) 92%,var(--doodle-purple)) 0%,'
            . 'color-mix(in srgb,var(--lap-bg) 85%,var(--doodle-purple)) 100%)';
        $vars['--lap-band-line-top'] = 'color-mix(in srgb,var(--lap-bg) 50%,transparent)';

        $vars['--glow-alpha'] = self::szam($kell('glow.alpha.dark'));
        $vars['--glow-blur'] = self::szam($kell('glow.blur')) . 'px';

        foreach (['head' => '--font-head', 'accent' => '--font-accent', 'body' => '--font-body'] as $k => $var) {
            $nev = $kell("type.{$k}");
            if ($nev !== null) {
                $vars[$var] = self::FONTS[$nev]['stack']
                    ?? throw new RuntimeException("Téma: a(z) „{$nev}” betű nincs önhostolva (public/fonts). Választható: " . implode(', ', array_keys(self::FONTS)) . '.');
            }
        }
        $wdth = $kell('type.headWdth');
        $wght = $kell('type.headWght');
        $track = $kell('type.headTracking');
        if ($wdth !== null) {
            $vars['--head-wdth'] = self::szam($wdth);
            $vars['--head-wdth-2'] = self::szam(max(100, $wdth - 2));
        }
        // Vastagság CSAK a betű valódi tartományán belül. A címbetűnél a téma rossz értéke HIBA (nem
        // igazítjuk csendben); a kisebb címsorok „-2” változata a tartományba szorul (Tinglernél 400 marad).
        $headFont = self::FONTS[data_get($doc, 'type.head')] ?? null;
        if ($wght !== null && $headFont !== null) {
            [$min, $max] = $headFont['wght'];
            if ($wght < $min || $wght > $max) {
                throw new RuntimeException('Téma: a(z) „' . data_get($doc, 'type.head') . "” címbetű csak {$min}–{$max} vastagságban létezik, a téma {$wght}-at kér (Androidon körvonalas lenne).");
            }
            $vars['--head-wght'] = self::szam($wght);
            $vars['--head-wght-2'] = self::szam(max($min, $wght - 20));
        }
        // A hangsúly-betű (kiemelt szavak) vastagsága és dőlése: a betű saját adottsága, nem a környező
        // szövegé — különben egy 800-as sorban a Tingler is 800-at kapna.
        $accentFont = self::FONTS[data_get($doc, 'type.accent')] ?? null;
        if ($accentFont !== null) {
            $vars['--accent-wght'] = self::szam(max($accentFont['wght'][0], min($accentFont['wght'][1], 400)));
            $vars['--accent-style'] = $accentFont['italic'] ? 'italic' : 'normal';
        }
        $vars['--type-scale'] = self::szam($kell('type.scale'));
        if ($track !== null) {
            $vars['--head-track'] = self::szam($track) . 'em';
            $vars['--head-track-2'] = self::szam(min(0, $track + 0.005)) . 'em';
        }

        foreach (['rLap' => '--r-lap', 'rCard' => '--r-card', 'rField' => '--r-field', 'borderW' => '--border-w'] as $k => $var) {
            $vars[$var] = self::szam($kell("shape.{$k}")) . 'px';
        }

        $density = $kell('space.density');
        if ($density !== null && ! isset(self::DENSITY_MUL[$density])) {
            throw new RuntimeException("Téma: ismeretlen térköz-sűrűség: „{$density}”.");
        }
        $vars['--space-mul'] = self::szam(self::DENSITY_MUL[$density] ?? 1);
        $vars['--content-max'] = self::szam($kell('space.contentMax')) . 'px';

        $level = $kell('motion.level');
        if ($level !== null && ! in_array($level, ['full', 'subtle', 'minimal'], true)) {
            throw new RuntimeException("Téma: ismeretlen mozgás-mennyiség: „{$level}”.");
        }
        $vars['--dur-1'] = self::szam($kell('motion.dur1')) . 's';
        $vars['--dur-2'] = self::szam($kell('motion.dur2')) . 's';
        $vars['--stagger'] = self::szam($kell('motion.stagger')) . 'ms';
        $vars['--pulse-dur'] = self::szam($kell('motion.pulseDur')) . 's';
        $float = $kell('motion.doodleFloat');
        $lessMotion = $float === false || $level === 'subtle' || $level === 'minimal';
        if ($lessMotion) {
            $vars['--doodle-float-dur'] = '0.001s';
        }
        if ($level === 'minimal') {
            $extra[] = '*,*::before,*::after{animation-duration:.001s!important;animation-iteration-count:1!important;transition-duration:0s!important}';
        }

        $vars['--doodle-opacity'] = self::szam($kell('doodles.opacity'));
        foreach (self::DOODLE_COLORS as $k) {
            $v = $kell("doodles.palette.{$k}");
            $vars["--doodle-{$k}"] = is_string($v) ? self::tiszta($v, "doodles.palette.{$k}") : null;
        }
        $dd = $kell('doodles.density');
        if ($dd === 'none') {
            $extra[] = '[data-decor]{display:none}';
        } elseif ($dd === 'subtle') {
            $extra[] = '[data-decor="ambient"] .decor:nth-child(2n){display:none}';
        } elseif ($dd !== null && $dd !== 'default') {
            throw new RuntimeException("Téma: ismeretlen firka-sűrűség: „{$dd}”.");
        }
        if ($kell('doodles.zones.hero') === false) {
            $extra[] = '[data-decor="hero"]{display:none}';
        }
        if ($kell('doodles.zones.noPhotoCards') === false) {
            $extra[] = '.pc-doodle{display:none}';
        }
        if ($lessMotion) {
            // A firka-lebegést a lap JS-e inline is írhatja — a kikapcsolás csak !important-tal biztos.
            $extra[] = '[data-decor] .decor{animation:none!important}';
        }

        $vars['--navbar-blur'] = self::szam($kell('header.navbarBlur')) . 'px';
        $vars['--logo-header'] = self::szam($kell('logo.headerSize')) . 'px';
        $vars['--logo-header-mobile'] = self::szam($kell('logo.headerSizeMobile')) . 'px';

        // VILÁGOS mód: csak a módfüggő tokenek (a többi a :root-ból öröklődik).
        $light = ['color-scheme' => 'light'];
        foreach (self::LIGHT_COLORS as $var) {
            $light[$var] = $szin(self::COLORS[$var], 'light');
        }
        $light += $akcent('light');
        $light['--link'] = 'var(--accent-c)';
        $light['--glow-alpha'] = self::szam($kell('glow.alpha.light'));

        if ($hianyzik) {
            throw new RuntimeException('Téma: hiányzó kötelező mező(k): ' . implode(', ', array_unique($hianyzik)) . '.');
        }

        return '/* Forgatag OS — generált téma-CSS (App\Support\ThemeCss). Kézzel ne szerkeszd. */' . "\n"
            . ':root{' . self::blokk($vars) . "}\n"
            . ':root[data-theme="light"]{' . self::blokk($light) . "}\n"
            . ($extra ? implode("\n", $extra) . "\n" : '');
    }

    /** @param array<string, string> $v */
    private static function blokk(array $v): string
    {
        $sor = [];
        foreach ($v as $k => $x) {
            $sor[] = "{$k}:{$x}";
        }

        return implode(';', $sor);
    }

    /** Szín/érték: CSS-be csak ártalmatlan karakter mehet (nincs `;`, `{`, `}`, `<`, url()). */
    private static function tiszta(string $v, string $path): string
    {
        $v = trim($v);
        if ($v === '' || preg_match('/[;{}<>\\\\]|url\s*\(/i', $v)) {
            throw new RuntimeException("Téma: érvénytelen érték a(z) {$path} mezőben: „{$v}”.");
        }

        return $v;
    }

    public static function szam(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (! is_int($v) && ! is_float($v)) {
            throw new RuntimeException('Téma: számot vártam, ezt kaptam: ' . json_encode($v));
        }
        // Determinisztikus, rövid alak (0.5 → 0.5, 80 → 80, -0.015 → -0.015).
        return rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.') ?: '0';
    }
}
