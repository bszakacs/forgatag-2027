<?php

namespace App\Support;

use RuntimeException;

/**
 * Téma-adat → CSS. EGYETLEN forrás minden téma-tulajdonú tokenhez (szín, betű, forma, mozgás, firka).
 * A 2026-os frontend/src/lib/theme-css.ts (themeToCss) átirata, két szándékos eltéréssel:
 *  · SZIGORÚ: ha egy kötelező mező hiányzik, hibával áll meg — nincs csendes beépített tartalék
 *    (CLAUDE.md 2/c). 2026-ban a generátor a hiányzó mezőt kihagyta, és a lap CSS-e „pótolta”.
 *  · A „Komponensek” szekció megszűnt: a „MA” jelvény színei a színek közé kerültek
 *    (colors.badgeMaBg / badgeMaFg), a fejléc üveghatása a fejléchez (header.navbarBlur).
 * A kimenet determinisztikus: ugyanabból az adatból betűre ugyanaz a CSS (erre épül a fájlnév-lenyomat).
 */
class ThemeCss
{
    /** Az önhostolt betűk (public/fonts) — a stack végi rendszer-betű pótolja a román ș/ț-t, ahol hiányzik. */
    public const FONTS = [
        'Archivo' => ['stack' => "'Archivo',sans-serif", 'preload' => 'fonts/Archivo-var-latin.woff2'],
        'Instrument Serif' => ['stack' => "'Instrument Serif',serif", 'preload' => 'fonts/InstrumentSerif-italic-latin.woff2'],
        'Hanken Grotesk' => ['stack' => "'Hanken Grotesk',system-ui,'Segoe UI',Roboto,sans-serif", 'preload' => 'fonts/HankenGrotesk-400-latin.woff2'],
        'Tingler Print' => ['stack' => "'Tingler Print',system-ui,'Segoe UI',Roboto,sans-serif", 'preload' => 'fonts/TinglerPrint-Regular.woff2'],
    ];

    private const DENSITY_MUL = ['compact' => 0.85, 'default' => 1, 'airy' => 1.15];

    /** Színtokenek: CSS-név => adat-útvonal (sötét-only: a pár `dark` fele). */
    private const COLORS = [
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

    private const DOODLE_COLORS = ['green', 'lime', 'orange', 'red', 'pink', 'yellow', 'blue', 'purple'];

    /**
     * A firka-dekor jelölése a lapokon (2027-es szerződés, minden lapra ugyanaz):
     *  · `[data-decor="hero"]` — a nyitókép/fejléc-sáv dekor-rétege,
     *  · `[data-decor="ambient"]` — a lapon szórt dekor-réteg,
     *  · `.decor` — egy firka-elem bármelyik rétegben,
     *  · `.pc-doodle` — a fotó nélküli kártyák sarok-firkája.
     */
    private const D_LAYERS = '[data-decor]';

    private const D_HERO = '[data-decor="hero"]';

    private const D_ITEMS = '[data-decor] .decor';

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
        $szin = function (string $path) use ($kell) {
            $v = $kell($path . '.dark');

            return is_string($v) ? self::tiszta($v, $path) : null;
        };

        foreach (self::COLORS as $var => $path) {
            $vars[$var] = $szin($path);
        }
        foreach ((array) data_get($doc, 'colors.groups', []) as $slug => $p) {
            if (! preg_match('/^[a-z0-9-]+$/', (string) $slug)) {
                throw new RuntimeException("Téma: érvénytelen kategóriacsoport-kulcs: „{$slug}”.");
            }
            $vars["--grp-{$slug}"] = $szin("colors.groups.{$slug}");
        }

        // Akcent-gradiens. A `-text` változat szándékosan kétállomású (a harmadik, sötét stop a
        // gradienses szöveg végét olvashatatlanná tenné).
        $acc = $kell('colors.accent.dark');
        $angle = $kell('colors.accent.angle');
        $from = $szin('colors.accentGradFrom');
        if (is_array($acc) && count($acc) === 3 && $angle !== null && $from !== null) {
            [$a, $b, $c] = array_map(fn ($x) => self::tiszta((string) $x, 'colors.accent.dark'), $acc);
            $deg = self::szam($angle) . 'deg';
            $vars += [
                '--accent-a' => $a, '--accent-b' => $b, '--accent-c' => $c,
                '--accent-angle' => $deg,
                '--accent-grad-from' => $from,
                '--accent-grad' => "linear-gradient({$deg},{$from} 0%,{$b} 55%,{$c} 100%)",
                '--accent-grad-text' => "linear-gradient({$deg},{$from},{$b})",
            ];
        } elseif ($acc !== null) {
            $hianyzik[] = 'colors.accent.dark (3 szín)';
        }

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
        if ($wght !== null) {
            $vars['--head-wght'] = self::szam($wght);
            $vars['--head-wght-2'] = self::szam(max(100, $wght - 20));
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
            $extra[] = self::D_LAYERS . '{display:none}';
        } elseif ($dd === 'subtle') {
            $extra[] = '[data-decor="ambient"] .decor:nth-child(2n){display:none}';
        } elseif ($dd !== null && $dd !== 'default') {
            throw new RuntimeException("Téma: ismeretlen firka-sűrűség: „{$dd}”.");
        }
        if ($kell('doodles.zones.hero') === false) {
            $extra[] = self::D_HERO . '{display:none}';
        }
        if ($kell('doodles.zones.noPhotoCards') === false) {
            $extra[] = '.pc-doodle{display:none}';
        }
        if ($lessMotion) {
            // A firka-lebegést a lap JS-e inline is írhatja — a kikapcsolás csak !important-tal biztos.
            $extra[] = self::D_ITEMS . '{animation:none!important}';
        }

        $vars['--navbar-blur'] = self::szam($kell('header.navbarBlur')) . 'px';
        $vars['--logo-header'] = self::szam($kell('logo.headerSize')) . 'px';
        $vars['--logo-header-mobile'] = self::szam($kell('logo.headerSizeMobile')) . 'px';

        if ($hianyzik) {
            throw new RuntimeException('Téma: hiányzó kötelező mező(k): ' . implode(', ', array_unique($hianyzik)) . '.');
        }

        $sor = [];
        foreach ($vars as $k => $v) {
            $sor[] = "{$k}:{$v}";
        }

        return '/* Forgatag OS — generált téma-CSS (App\Support\ThemeCss). Kézzel ne szerkeszd. */' . "\n"
            . ':root{' . implode(';', $sor) . "}\n"
            . ($extra ? implode("\n", $extra) . "\n" : '');
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

    private static function szam(mixed $v): string
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
