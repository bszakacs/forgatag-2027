<?php

namespace App\Support;

use RuntimeException;

/**
 * A /tema szerkesztő leírása — EGY helyen: a szekciók, a vezérlők feliratai és súgói (a 2026-os
 * frontend/src/lib/theme.ts SECTIONS átvétele), a csúszkák tartományai és a kontraszt-szabályok.
 * Ebből épül a felület (JSON-ként a böngészőnek), és ebből ellenőriz a szerver is publikáláskor —
 * a böngészőnek nem hiszünk vakon.
 *
 * Eltérések 2026-hoz képest: a „Kategóriacsoportok” szekció MEGSZŰNT (tulajdonosi döntés, 2026-10-21),
 * a „Komponensek” is (a „MA” jelvény a színek közé, az üveghatás a fejléchez került); a fájl-mezőknek
 * nincs „üres = beépített” állapota (CLAUDE.md 2/c); a színpároknak világos fele is van, ahol a
 * témában világos érték létezik (ThemeCss::LIGHT_COLORS).
 */
class ThemeEditorSpec
{
    public const FONT_HEAD = [
        ['value' => 'Tingler Print', 'label' => 'Tingler Print (gyári — kézírásos)'],
        ['value' => 'Archivo', 'label' => 'Archivo (variable wdth/wght)'],
        ['value' => 'Hanken Grotesk', 'label' => 'Hanken Grotesk'],
    ];

    public const FONT_ACCENT = [
        ['value' => 'Tingler Print', 'label' => 'Tingler Print (gyári — kézírásos)'],
        ['value' => 'Instrument Serif', 'label' => 'Instrument Serif (dőlt talpas)'],
    ];

    public const FONT_BODY = [
        ['value' => 'Hanken Grotesk', 'label' => 'Hanken Grotesk (gyári)'],
        ['value' => 'Archivo', 'label' => 'Archivo'],
    ];

    /** Akcent-presetek (2026, THEME-EDITOR.md 3. szekció) — csak a sötét hármast állítják. */
    public const ACCENT_PRESETS = [
        ['name' => 'Forgatag-narancs (gyári)', 'dark' => ['#FFB84D', '#F5911E', '#E37A0C']],
        ['name' => 'Napfény', 'dark' => ['#F8C21C', '#F5891C', '#FF5A38']],
        ['name' => 'Neon-éj', 'dark' => ['#9BE8FF', '#8B9BFF', '#E85D9E']],
    ];

    /** A fájl-mezők: adat-útvonal => tárbeli hely-név (Theme::storeAsset) és elfogadott kiterjesztések. */
    public const MEDIA = [
        'assets.logo' => ['slot' => 'logo', 'ext' => ['png', 'webp', 'svg']],
        'assets.logoHeader' => ['slot' => 'logo-fejlec', 'ext' => ['png', 'webp', 'svg']],
        'assets.favicon' => ['slot' => 'favicon', 'ext' => ['png', 'ico']],
    ];

    /** @return list<array<string, mixed>> */
    public static function sections(): array
    {
        $c = fn (string $path, string $label, array $extra = []) => ['kind' => 'colorPair', 'path' => $path, 'label' => $label,
            'light' => self::hasLight($path)] + $extra;
        $ct = fn (string $against, float $min, string $label, ?string $behind = null) => array_filter(
            ['against' => $against, 'min' => $min, 'label' => $label, 'behind' => $behind], fn ($v) => $v !== null);

        return [
            ['id' => 'identity', 'title' => '1 · Identitás',
                'note' => 'Mindhárom fájl kötelező — üres nem lehet. Csere: új feltöltés; vissza: „Gyári”.',
                'controls' => [
                    ['kind' => 'media', 'path' => 'assets.logo', 'label' => 'Logó (fő)', 'hint' => 'PNG/SVG, átlátszó háttérrel'],
                    ['kind' => 'media', 'path' => 'assets.logoHeader', 'label' => 'Fejléc-logó', 'hint' => 'a fejlécben ez látszik (lehet ugyanaz, mint a fő logó)'],
                    ['kind' => 'media', 'path' => 'assets.favicon', 'label' => 'Favicon (böngészőfül-ikon)', 'hint' => 'négyzetes PNG, legalább 64×64'],
                ]],
            ['id' => 'header', 'title' => '2 · Fejléc (logó és gomb)',
                'note' => 'A fejléc SÁVJA nem nő a logóval: a nagy logó lelóg a sávból, rá a nyitóképre (a v7-arculat szerint).',
                'controls' => [
                    ['kind' => 'slider', 'path' => 'logo.headerSize', 'label' => 'Logó magassága (asztali)', 'min' => 40, 'max' => 200, 'step' => 2, 'unit' => 'px', 'hint' => 'a fejléc sávja 80 px marad; a logó ez alá lóg'],
                    ['kind' => 'slider', 'path' => 'logo.headerSizeMobile', 'label' => 'Logó magassága (mobil)', 'min' => 32, 'max' => 140, 'step' => 2, 'unit' => 'px', 'hint' => '720 px alatti kijelzőn érvényes; a sáv ott 66 px'],
                    ['kind' => 'textBi', 'path' => 'header.ctaLabel', 'label' => 'Fejléc-gomb felirata', 'hint' => 'üresen a beépített felirat marad', 'max' => 40],
                    ['kind' => 'toggle', 'path' => 'header.showLangSwitch', 'label' => 'HU|RO nyelvváltó a fejlécben', 'hint' => 'kikapcsolva nem jelenik meg'],
                    ['kind' => 'toggle', 'path' => 'header.showSearch', 'label' => 'Kereső a fejlécben', 'hint' => 'kikapcsolva nem jelenik meg'],
                    ['kind' => 'slider', 'path' => 'header.navbarBlur', 'label' => 'Fejléc üveghatás (blur)', 'min' => 0, 'max' => 24, 'step' => 1, 'unit' => 'px', 'hint' => 'a sáv áttetsző háttere mögötti elmosás'],
                ]],
            ['id' => 'base-colors', 'title' => '3 · Színek — Alapok',
                'note' => 'A WCAG-őr élőben számol — 4.5:1 alatt a publikálás blokkolva (csak írásos felülbírálással mehet ki). A „Világos” oszlop a beküldő űrlap világos módjáé (◐).',
                'controls' => [
                    $c('colors.bg', 'Oldal-alap (--bg)'),
                    $c('colors.bgRaise', 'Emelt sáv / footer (--bg-raise)'),
                    $c('colors.panel', 'Szekció-lap (--panel)'),
                    $c('colors.card', 'Kártya (--card)', ['hint' => 'rgba() is lehet']),
                    $c('colors.cardHover', 'Kártya hover (--card-hover)'),
                    $c('colors.line', 'Vonal (--line)'),
                    $c('colors.lineStrong', 'Erős vonal (--line-strong)'),
                    $c('colors.fg', 'Szöveg (--fg)', ['contrast' => [$ct('colors.bg', 4.5, 'fg ↔ bg')]]),
                    $c('colors.fgMuted', 'Halvány szöveg (--fg-muted)', ['contrast' => [$ct('colors.panel', 4.5, 'fg-muted ↔ panel'), $ct('colors.bg', 4.5, 'fg-muted ↔ bg')]]),
                    $c('colors.fgFaint', 'Leghalványabb (--fg-faint)', ['hint' => 'csak ≥14px félkövér', 'contrast' => [$ct('colors.bg', 3, 'fg-faint ↔ bg (≥3:1)')]]),
                    $c('colors.lapBg', 'Nappali lap (--lap-bg)'),
                    $c('colors.lapCard', 'Nappali kártya (--lap-card)'),
                    $c('colors.lapFg', 'Nappali szöveg (--lap-fg)', ['contrast' => [$ct('colors.lapBg', 4.5, 'lap-fg ↔ lap-bg')]]),
                    $c('colors.lapMuted', 'Nappali halvány (--lap-muted)', ['contrast' => [$ct('colors.lapBg', 4.5, 'lap-muted ↔ lap-bg')]]),
                    $c('colors.lapLine', 'Nappali vonal (--lap-line)'),
                    $c('colors.focus', 'Fókusz (--focus)', ['contrast' => [$ct('colors.bg', 3, 'fókusz ↔ bg (≥3:1)'), $ct('colors.panel', 3, 'fókusz ↔ panel (≥3:1)')]]),
                    $c('colors.ok', 'OK / Ingyenes (--ok)', ['contrast' => [$ct('colors.bg', 3, 'ok ↔ bg (≥3:1)')]]),
                    $c('colors.warn', 'Figyelem / MA (--warn)', ['contrast' => [$ct('colors.bg', 3, 'warn ↔ bg (≥3:1)')]]),
                    $c('colors.danger', 'Hiba / Elmarad (--danger)', ['contrast' => [$ct('colors.bg', 3, 'danger ↔ bg (≥3:1)')]]),
                    $c('colors.badgeMaBg', '„MA” badge háttér'),
                    $c('colors.badgeMaFg', '„MA” badge szöveg', ['contrast' => [$ct('colors.badgeMaBg', 4.5, 'MA-szöveg ↔ MA-háttér')]]),
                ]],
            ['id' => 'accent', 'title' => '4 · Színek — Akcent és gradiens',
                'note' => 'Három fogantyús gradiens (CTA-k, dőlt hangsúlyszavak). Élő CTA-előnézet jobbra.',
                'controls' => [
                    ['kind' => 'accentTrio', 'path' => 'colors.accent', 'label' => 'Akcent-gradiens (a → b → c)', 'light' => true],
                    $c('colors.accentGradFrom', 'Gradiens indító színe', ['hint' => 'a CTA-gombok gradiense ezzel kezdődik']),
                    ['kind' => 'slider', 'path' => 'colors.accent.angle', 'label' => 'Gradiens szöge', 'min' => 0, 'max' => 360, 'step' => 5, 'unit' => '°'],
                    $c('colors.accentContrast', 'CTA-szöveg (--accent-contrast)'),
                ]],
            ['id' => 'glow', 'title' => '5 · Fény (glow)',
                'controls' => [
                    ['kind' => 'sliderPair', 'path' => 'glow.alpha', 'label' => 'Glow-intenzitás', 'min' => 0, 'max' => 1, 'step' => 0.05],
                    ['kind' => 'slider', 'path' => 'glow.blur', 'label' => 'Glow-elmosás', 'min' => 40, 'max' => 140, 'step' => 5, 'unit' => 'px'],
                ]],
            ['id' => 'type', 'title' => '6 · Tipográfia',
                'note' => 'A lista kurált és glifkészlet-ellenőrzött (teljes HU+RO: ő ű ș ț ă ✓). Új betű felvétele önhostolást igényel. A vastagság csak a betű valódi tartományában állítható (a Tingler Print csak 400-as — más vastagság Androidon körvonalas lenne).',
                'controls' => [
                    ['kind' => 'select', 'path' => 'type.head', 'label' => 'Címsor-betű', 'options' => self::FONT_HEAD],
                    ['kind' => 'select', 'path' => 'type.accent', 'label' => 'Hangsúly-betű (italic serif)', 'options' => self::FONT_ACCENT],
                    ['kind' => 'select', 'path' => 'type.body', 'label' => 'Törzs-betű', 'options' => self::FONT_BODY],
                    ['kind' => 'slider', 'path' => 'type.headWdth', 'label' => 'Címsor-szélesség (wdth)', 'min' => 100, 'max' => 125, 'step' => 1, 'font' => 'wdth'],
                    ['kind' => 'slider', 'path' => 'type.headWght', 'label' => 'Címsor-vastagság (wght)', 'min' => 100, 'max' => 900, 'step' => 10, 'font' => 'wght'],
                    ['kind' => 'slider', 'path' => 'type.scale', 'label' => 'Betűméret-skála', 'min' => 0.85, 'max' => 1.15, 'step' => 0.01, 'unit' => '×'],
                    ['kind' => 'slider', 'path' => 'type.headTracking', 'label' => 'Betűköz címsorokban', 'min' => -0.04, 'max' => 0, 'step' => 0.005, 'unit' => 'em'],
                ]],
            ['id' => 'shape', 'title' => '7 · Forma és térköz',
                'controls' => [
                    ['kind' => 'slider', 'path' => 'shape.rLap', 'label' => 'Lap-lekerekítés (--r-lap)', 'min' => 0, 'max' => 48, 'step' => 2, 'unit' => 'px'],
                    ['kind' => 'slider', 'path' => 'shape.rCard', 'label' => 'Kártya-lekerekítés (--r-card)', 'min' => 0, 'max' => 32, 'step' => 2, 'unit' => 'px'],
                    ['kind' => 'slider', 'path' => 'shape.rField', 'label' => 'Mező-lekerekítés (--r-field)', 'min' => 0, 'max' => 24, 'step' => 1, 'unit' => 'px'],
                    ['kind' => 'radio', 'path' => 'shape.borderW', 'label' => 'Kártya-keretvastagság', 'options' => [
                        ['value' => 1, 'label' => '1px'], ['value' => 1.5, 'label' => '1.5px'], ['value' => 2, 'label' => '2px']]],
                    ['kind' => 'radio', 'path' => 'space.density', 'label' => 'Térköz-sűrűség', 'options' => [
                        ['value' => 'compact', 'label' => 'Kompakt'], ['value' => 'default', 'label' => 'Alap'], ['value' => 'airy', 'label' => 'Levegős']]],
                    ['kind' => 'slider', 'path' => 'space.contentMax', 'label' => 'Tartalom max-szélesség', 'min' => 1040, 'max' => 1320, 'step' => 20, 'unit' => 'px'],
                ]],
            ['id' => 'motion', 'title' => '8 · Mozgás',
                'note' => 'prefers-reduced-motion esetén minden automatikusan kikapcsol.',
                'controls' => [
                    ['kind' => 'radio', 'path' => 'motion.level', 'label' => 'Mozgás-mennyiség', 'options' => [
                        ['value' => 'full', 'label' => 'Teljes'], ['value' => 'subtle', 'label' => 'Finom'], ['value' => 'minimal', 'label' => 'Minimális']]],
                    ['kind' => 'slider', 'path' => 'motion.dur1', 'label' => 'Mikrointerakció-sebesség', 'min' => 0.1, 'max' => 0.4, 'step' => 0.02, 'unit' => 's'],
                    ['kind' => 'slider', 'path' => 'motion.dur2', 'label' => 'Reveal-sebesség', 'min' => 0.3, 'max' => 1, 'step' => 0.05, 'unit' => 's'],
                    ['kind' => 'slider', 'path' => 'motion.pulseDur', 'label' => '„MA” pulzálás', 'min' => 1.5, 'max' => 5, 'step' => 0.1, 'unit' => 's'],
                    ['kind' => 'toggle', 'path' => 'motion.doodleFloat', 'label' => 'Firka-lebegés', 'hint' => 'a kezdőlap lebegő firkái'],
                ]],
            ['id' => 'doodles', 'title' => '9 · Dekor (firkák)',
                'note' => 'A kezdőlap firkái a landing designból jönnek: a nyitókép két szélén lengő motívumok + a lapon szórt, lassan lefelé sodródó firkák. Ezek a vezérlők azt szabályozzák, ami ott van — új réteg nem kerül be.',
                'controls' => [
                    ['kind' => 'radio', 'path' => 'doodles.density', 'label' => 'Firka-sűrűség', 'hint' => 'Visszafogott = a szórt firkák fele; Nincs = a kezdőlap összes firkája eltűnik', 'options' => [
                        ['value' => 'none', 'label' => 'Nincs'], ['value' => 'subtle', 'label' => 'Visszafogott'], ['value' => 'default', 'label' => 'Alap']]],
                    ['kind' => 'slider', 'path' => 'doodles.opacity', 'label' => 'Firka-átlátszóság', 'min' => 0, 'max' => 1, 'step' => 0.05, 'hint' => 'a gyári 0,72 a design szerinti látvány'],
                    ['kind' => 'toggle', 'path' => 'doodles.zones.hero', 'label' => 'Nyitókép szélein', 'hint' => 'a hero két oldalán lengő motívumok'],
                    ['kind' => 'toggle', 'path' => 'doodles.zones.noPhotoCards', 'label' => 'Fotó nélküli kártyákon', 'hint' => 'a program-kártyák sarok-firkája'],
                    ['kind' => 'color', 'path' => 'doodles.palette.green', 'label' => 'Firka-szín · zöld'],
                    ['kind' => 'color', 'path' => 'doodles.palette.lime', 'label' => 'Firka-szín · lime'],
                    ['kind' => 'color', 'path' => 'doodles.palette.orange', 'label' => 'Firka-szín · narancs'],
                    ['kind' => 'color', 'path' => 'doodles.palette.red', 'label' => 'Firka-szín · piros'],
                    ['kind' => 'color', 'path' => 'doodles.palette.pink', 'label' => 'Firka-szín · pink'],
                    ['kind' => 'color', 'path' => 'doodles.palette.yellow', 'label' => 'Firka-szín · sárga'],
                    ['kind' => 'color', 'path' => 'doodles.palette.blue', 'label' => 'Firka-szín · kék'],
                    ['kind' => 'color', 'path' => 'doodles.palette.purple', 'label' => 'Firka-szín · lila'],
                    ['kind' => 'motifs', 'path' => 'doodles.motifs', 'label' => 'Firka-motívumok (6 hely)', 'slots' => Theme::MOTIF_SLOTS,
                        'hint' => 'a kezdőlap első hat firka-motívuma · mind feltöltött .svg fájl, üres hely nincs · vissza: „Gyári”'],
                ]],
            ['id' => 'analytics', 'title' => '10 · Analitika',
                'note' => 'Google Tag Manager. A konténer-azonosítót (GTM-XXXXXXX) a saját GTM-fiókodban találod. A GA4 magán a GTM-en belül fut, ezért ide csak a GTM-azonosító kell. Üresen hagyva semmilyen mérőkód nem töltődik be.',
                'controls' => [
                    ['kind' => 'text', 'path' => 'analytics.gtmId', 'label' => 'GTM konténer-azonosító', 'hint' => 'pl. GTM-XXXXXXX (üres = nincs mérés)', 'pattern' => '^(GTM-[A-Z0-9]{4,12})?$'],
                ]],
            ['id' => 'social', 'title' => '11 · Social linkek',
                'note' => 'A lábléc social ikonjai. Csak a kitöltött platformok ikonja jelenik meg (üres mező = nincs ikon), a linkek új lapon nyílnak. Az ikon a sablonból jön, az arculati színnel.',
                'controls' => [
                    ['kind' => 'text', 'path' => 'social.facebook', 'label' => 'Facebook URL', 'hint' => 'pl. https://facebook.com/vasarhelyiforgatag (üres = nincs)', 'pattern' => '^(https://\S+)?$'],
                    ['kind' => 'text', 'path' => 'social.instagram', 'label' => 'Instagram URL', 'hint' => 'pl. https://instagram.com/… (üres = nincs)', 'pattern' => '^(https://\S+)?$'],
                    ['kind' => 'text', 'path' => 'social.youtube', 'label' => 'YouTube URL', 'hint' => 'pl. https://youtube.com/@… (üres = nincs)', 'pattern' => '^(https://\S+)?$'],
                    ['kind' => 'text', 'path' => 'social.tiktok', 'label' => 'TikTok URL', 'hint' => 'pl. https://tiktok.com/@… (üres = nincs)', 'pattern' => '^(https://\S+)?$'],
                ]],
        ];
    }

    /** A böngészőnek küldött teljes leírás. @return array<string, mixed> */
    public static function forClient(): array
    {
        return [
            'sections' => self::sections(),
            'fonts' => ThemeCss::FONTS, // a JS-generátor is ebből dolgozik (stack, vastagság, dőlt) — egy forrás
            'presets' => self::ACCENT_PRESETS,
            'media' => self::MEDIA,
            'lightColors' => ThemeCss::LIGHT_COLORS,
            'colorPaths' => ThemeCss::COLORS,
            'doodleColors' => ThemeCss::DOODLE_COLORS,
            'density' => ThemeCss::DENSITY_MUL,
        ];
    }

    private static function hasLight(string $path): bool
    {
        if ($path === 'colors.accentGradFrom') {
            return true;
        }
        $var = array_search($path, ThemeCss::COLORS, true);

        return $var !== false && in_array($var, ThemeCss::LIGHT_COLORS, true);
    }

    /** @return list<array<string, mixed>> */
    public static function controls(): array
    {
        return collect(self::sections())->flatMap(fn ($s) => $s['controls'])->values()->all();
    }

    /**
     * A téma-adat szerkezeti és érték-ellenőrzése. A megengedett SZERKEZET a kezdő téma
     * (seed/tema/tema.json): ismeretlen kulcs hiba — így egy kivezetett mező (pl. colors.groups,
     * components) importtal sem tud visszacsempészni magát. Az értéktartományok a vezérlőkből jönnek.
     *
     * @param  array<string, mixed>  $data
     */
    public static function validate(array $data): void
    {
        $hibak = [];
        self::szerkezet($data, self::template(), '', $hibak);

        foreach (self::controls() as $c) {
            $p = $c['path'];
            $v = data_get($data, $p);
            switch ($c['kind']) {
                case 'slider':
                    if (! is_int($v) && ! is_float($v)) {
                        $hibak[] = "{$c['label']}: számot vártam";
                    } elseif ($v < $c['min'] - 1e-9 || $v > $c['max'] + 1e-9) {
                        $hibak[] = "{$c['label']}: {$v} kívül esik a {$c['min']}–{$c['max']} tartományon";
                    }
                    break;
                case 'sliderPair':
                    foreach (['dark', 'light'] as $m) {
                        $x = data_get($v, $m);
                        if (! is_int($x) && ! is_float($x) || $x < $c['min'] || $x > $c['max']) {
                            $hibak[] = "{$c['label']} ({$m}): {$c['min']}–{$c['max']} közötti szám kell";
                        }
                    }
                    break;
                case 'toggle':
                    if (! is_bool($v)) {
                        $hibak[] = "{$c['label']}: igen/nem érték kell";
                    }
                    break;
                case 'radio':
                case 'select':
                    if (! in_array($v, array_column($c['options'], 'value'), true)) {
                        $hibak[] = "{$c['label']}: nem választható érték (" . json_encode($v, JSON_UNESCAPED_UNICODE) . ')';
                    }
                    break;
                case 'text':
                    if (! is_string($v) || ! preg_match('#' . str_replace('#', '\#', $c['pattern']) . '#u', $v)) {
                        $hibak[] = "{$c['label']}: érvénytelen érték („" . (is_string($v) ? $v : json_encode($v)) . '”)';
                    }
                    break;
                case 'textBi':
                    foreach (['hu', 'ro'] as $l) {
                        $x = data_get($v, $l);
                        if (! is_string($x) || mb_strlen($x) > $c['max']) {
                            $hibak[] = "{$c['label']} ({$l}): legfeljebb {$c['max']} karakteres szöveg";
                        }
                    }
                    break;
                case 'color':
                    if (! is_string($v) || ThemeContrast::parse($v) === null) {
                        $hibak[] = "{$c['label']}: nem színérték";
                    }
                    break;
                case 'colorPair':
                    foreach ($c['light'] ? ['dark', 'light'] : ['dark'] as $m) {
                        $x = data_get($v, $m);
                        if (! is_string($x) || ThemeContrast::parse($x) === null) {
                            $hibak[] = "{$c['label']} (" . ($m === 'dark' ? 'sötét' : 'világos') . '): nem színérték';
                        }
                    }
                    break;
                case 'accentTrio':
                    foreach (['dark', 'light'] as $m) {
                        $x = data_get($v, $m);
                        if (! is_array($x) || count($x) !== 3 || collect($x)->contains(fn ($y) => ! is_string($y) || ThemeContrast::parse($y) === null)) {
                            $hibak[] = "{$c['label']} (" . ($m === 'dark' ? 'sötét' : 'világos') . '): három színérték kell';
                        }
                    }
                    break;
            }
        }
        if (! is_string($data['name'] ?? null) || mb_strlen($data['name']) > 80) {
            $hibak[] = 'A téma neve: legfeljebb 80 karakteres szöveg kell';
        }

        if ($hibak) {
            throw new RuntimeException('A téma nem menthető: ' . implode(' · ', $hibak) . '.');
        }
    }

    /** A megengedett szerkezet (a kezdő téma, a magyarázó `_` kulcsok nélkül). @return array<string, mixed> */
    public static function template(): array
    {
        static $t = null;
        if ($t === null) {
            $t = json_decode((string) file_get_contents(base_path('seed/tema/tema.json')), true);
            $t = array_filter((array) $t, fn ($k) => ! str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_KEY);
        }

        return $t;
    }

    /** @param list<string> $hibak */
    private static function szerkezet(mixed $v, mixed $minta, string $ut, array &$hibak): void
    {
        $objektum = fn ($x) => is_array($x) && ! array_is_list($x);
        if (! $objektum($minta)) {
            return; // levél (érték vagy lista) — az értéket a vezérlő-szabályok nézik
        }
        if (! $objektum($v)) {
            $hibak[] = "„{$ut}” mezőcsoport hiányzik vagy rossz alakú";

            return;
        }
        foreach (array_keys($v) as $k) {
            if (! array_key_exists($k, $minta)) {
                $hibak[] = 'ismeretlen mező: „' . ltrim("{$ut}.{$k}", '.') . '”';
            }
        }
        foreach ($minta as $k => $m) {
            if (! array_key_exists($k, $v)) {
                $hibak[] = 'hiányzó mező: „' . ltrim("{$ut}.{$k}", '.') . '”';
            } else {
                self::szerkezet($v[$k], $m, ltrim("{$ut}.{$k}", '.'), $hibak);
            }
        }
    }
}
