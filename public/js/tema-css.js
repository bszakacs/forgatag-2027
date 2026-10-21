/* ============================================================
   Téma-adat → CSS, a BÖNGÉSZŐBEN — az App\Support\ThemeCss::generate BETŰHŰ párja.
   Az Arculat-szerkesztő élő előnézete ebből fut, szerver-körfordulás nélkül.
   A táblázatokat (betűk, színtokenek, világos tokenek, firka-színek, sűrűség) a szerver adja
   (ThemeEditorSpec::forClient) — itt csak a logika van. A szerkesztő minden betöltéskor
   összeveti ennek a kimenetét a szerverével; eltérésnél figyelmeztet (néma hiba tilos).
   HA A PHP-T MÓDOSÍTOD, EZT IS — és fordítva.
   ============================================================ */
(function () {
  'use strict';

  function get(o, path) {
    return path.split('.').reduce(function (a, k) { return a == null ? undefined : a[k]; }, o);
  }

  // PHP: rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.') ?: '0'
  function szam(v) {
    if (v === null || v === undefined) return '';
    if (typeof v !== 'number' || !isFinite(v)) throw new Error('Téma: számot vártam, ezt kaptam: ' + JSON.stringify(v));
    var s = v.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
    return s === '' ? '0' : s;
  }

  function tiszta(v, path) {
    v = String(v).trim();
    if (v === '' || /[;{}<>\\]|url\s*\(/i.test(v)) throw new Error('Téma: érvénytelen érték a(z) ' + path + ' mezőben: „' + v + '”.');
    return v;
  }

  function blokk(vars) {
    return Object.keys(vars).map(function (k) { return k + ':' + vars[k]; }).join(';');
  }

  /** @param doc a téma-adat · @param spec ThemeEditorSpec::forClient() */
  function generate(doc, spec) {
    var FONTS = spec.fonts, COLORS = spec.colorPaths, LIGHT = spec.lightColors;
    var vars = {}, extra = [], hianyzik = [];

    function kell(path) {
      var v = get(doc, path);
      if (v === null || v === undefined || v === '' || (Array.isArray(v) && v.length === 0)) hianyzik.push(path);
      return v;
    }
    function szin(path, mod) {
      mod = mod || 'dark';
      var v = kell(path + '.' + mod);
      return typeof v === 'string' ? tiszta(v, path + '.' + mod) : null;
    }
    function add(target, obj) { Object.keys(obj).forEach(function (k) { if (!(k in target)) target[k] = obj[k]; }); }

    vars['color-scheme'] = 'dark';
    Object.keys(COLORS).forEach(function (v) { vars[v] = szin(COLORS[v]); });

    var angle = kell('colors.accent.angle');
    var deg = angle !== null && angle !== undefined ? szam(angle) + 'deg' : '';
    function akcent(mod) {
      var acc = kell('colors.accent.' + mod);
      var from = szin('colors.accentGradFrom', mod);
      if (!Array.isArray(acc) || acc.length !== 3) { hianyzik.push('colors.accent.' + mod + ' (3 szín)'); return {}; }
      var a = tiszta(acc[0], 'colors.accent.' + mod), b = tiszta(acc[1], 'colors.accent.' + mod), c = tiszta(acc[2], 'colors.accent.' + mod);
      return {
        '--accent-a': a, '--accent-b': b, '--accent-c': c,
        '--accent-grad-from': from,
        '--accent-grad': 'linear-gradient(' + deg + ',' + from + ' 0%,' + b + ' 55%,' + c + ' 100%)',
        '--accent-grad-text': 'linear-gradient(' + deg + ',' + from + ',' + b + ')'
      };
    }
    add(vars, akcent('dark'));
    vars['--accent-angle'] = deg;
    vars['--link'] = 'var(--accent-a)';

    vars['--lap-band-bg'] = 'linear-gradient(180deg,'
      + 'color-mix(in srgb,var(--lap-bg) 92%,var(--doodle-purple)) 0%,'
      + 'color-mix(in srgb,var(--lap-bg) 85%,var(--doodle-purple)) 100%)';
    vars['--lap-band-line-top'] = 'color-mix(in srgb,var(--lap-bg) 50%,transparent)';

    vars['--glow-alpha'] = szam(kell('glow.alpha.dark'));
    vars['--glow-blur'] = szam(kell('glow.blur')) + 'px';

    [['head', '--font-head'], ['accent', '--font-accent'], ['body', '--font-body']].forEach(function (p) {
      var nev = kell('type.' + p[0]);
      if (nev !== null && nev !== undefined) {
        if (!FONTS[nev]) throw new Error('Téma: a(z) „' + nev + '” betű nincs önhostolva (public/fonts). Választható: ' + Object.keys(FONTS).join(', ') + '.');
        vars[p[1]] = FONTS[nev].stack;
      }
    });
    var wdth = kell('type.headWdth'), wght = kell('type.headWght'), track = kell('type.headTracking');
    if (wdth !== null && wdth !== undefined) {
      vars['--head-wdth'] = szam(wdth);
      vars['--head-wdth-2'] = szam(Math.max(100, wdth - 2));
    }
    var headFont = FONTS[get(doc, 'type.head')] || null;
    if (wght !== null && wght !== undefined && headFont) {
      var min = headFont.wght[0], max = headFont.wght[1];
      if (wght < min || wght > max) throw new Error('Téma: a(z) „' + get(doc, 'type.head') + '” címbetű csak ' + min + '–' + max + ' vastagságban létezik, a téma ' + wght + '-at kér (Androidon körvonalas lenne).');
      vars['--head-wght'] = szam(wght);
      vars['--head-wght-2'] = szam(Math.max(min, wght - 20));
    }
    var accentFont = FONTS[get(doc, 'type.accent')] || null;
    if (accentFont) {
      vars['--accent-wght'] = szam(Math.max(accentFont.wght[0], Math.min(accentFont.wght[1], 400)));
      vars['--accent-style'] = accentFont.italic ? 'italic' : 'normal';
    }
    vars['--type-scale'] = szam(kell('type.scale'));
    if (track !== null && track !== undefined) {
      vars['--head-track'] = szam(track) + 'em';
      vars['--head-track-2'] = szam(Math.min(0, track + 0.005)) + 'em';
    }

    [['rLap', '--r-lap'], ['rCard', '--r-card'], ['rField', '--r-field'], ['borderW', '--border-w']].forEach(function (p) {
      vars[p[1]] = szam(kell('shape.' + p[0])) + 'px';
    });

    var density = kell('space.density');
    if (density !== null && density !== undefined && !(density in spec.density)) throw new Error('Téma: ismeretlen térköz-sűrűség: „' + density + '”.');
    vars['--space-mul'] = szam(spec.density[density] !== undefined ? spec.density[density] : 1);
    vars['--content-max'] = szam(kell('space.contentMax')) + 'px';

    var level = kell('motion.level');
    if (level !== null && level !== undefined && ['full', 'subtle', 'minimal'].indexOf(level) < 0) throw new Error('Téma: ismeretlen mozgás-mennyiség: „' + level + '”.');
    vars['--dur-1'] = szam(kell('motion.dur1')) + 's';
    vars['--dur-2'] = szam(kell('motion.dur2')) + 's';
    vars['--stagger'] = szam(kell('motion.stagger')) + 'ms';
    vars['--pulse-dur'] = szam(kell('motion.pulseDur')) + 's';
    var flt = kell('motion.doodleFloat');
    var lessMotion = flt === false || level === 'subtle' || level === 'minimal';
    if (lessMotion) vars['--doodle-float-dur'] = '0.001s';
    if (level === 'minimal') extra.push('*,*::before,*::after{animation-duration:.001s!important;animation-iteration-count:1!important;transition-duration:0s!important}');

    vars['--doodle-opacity'] = szam(kell('doodles.opacity'));
    spec.doodleColors.forEach(function (k) {
      var v = kell('doodles.palette.' + k);
      vars['--doodle-' + k] = typeof v === 'string' ? tiszta(v, 'doodles.palette.' + k) : null;
    });
    var dd = kell('doodles.density');
    if (dd === 'none') extra.push('[data-decor]{display:none}');
    else if (dd === 'subtle') extra.push('[data-decor="ambient"] .decor:nth-child(2n){display:none}');
    else if (dd !== null && dd !== undefined && dd !== 'default') throw new Error('Téma: ismeretlen firka-sűrűség: „' + dd + '”.');
    if (kell('doodles.zones.hero') === false) extra.push('[data-decor="hero"]{display:none}');
    if (kell('doodles.zones.noPhotoCards') === false) extra.push('.pc-doodle{display:none}');
    if (lessMotion) extra.push('[data-decor] .decor{animation:none!important}');

    vars['--navbar-blur'] = szam(kell('header.navbarBlur')) + 'px';
    vars['--logo-header'] = szam(kell('logo.headerSize')) + 'px';
    vars['--logo-header-mobile'] = szam(kell('logo.headerSizeMobile')) + 'px';

    var light = { 'color-scheme': 'light' };
    LIGHT.forEach(function (v) { light[v] = szin(COLORS[v], 'light'); });
    add(light, akcent('light'));
    light['--link'] = 'var(--accent-c)';
    light['--glow-alpha'] = szam(kell('glow.alpha.light'));

    if (hianyzik.length) {
      throw new Error('Téma: hiányzó kötelező mező(k): ' + hianyzik.filter(function (x, i) { return hianyzik.indexOf(x) === i; }).join(', ') + '.');
    }

    return '/* Forgatag OS — generált téma-CSS (App\\Support\\ThemeCss). Kézzel ne szerkeszd. */\n'
      + ':root{' + blokk(vars) + '}\n'
      + ':root[data-theme="light"]{' + blokk(light) + '}\n'
      + (extra.length ? extra.join('\n') + '\n' : '');
  }

  window.FgThemeCss = { generate: generate, get: get };
})();
