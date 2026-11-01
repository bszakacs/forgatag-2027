<?php

namespace App\Support;

use App\Enums\TranslatableField;
use App\Enums\TranslationStatus;
use App\Models\ProgramItem;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * A program szövege a publikus oldalon, nyelv szerint (CLAUDE.md 5.) — EGY helyen.
 *
 * Románul a legjobb MEGJELENÍTHETŐ fordítás: approved → reviewed → machine_translated. Az `outdated` KIMARAD
 * (a magyar forrás változott, a régi román téves adatot hordozhat) — ott, és ahol nincs fordítás, a magyar látszik.
 * A `gepi` jelzi, ha a látszó román szöveg át nem nézett gépi fordítás (a program lapja ezt diszkréten kiírja).
 * A fordításokat a hívó töltse be előre (`with('translations')`), különben soronként egy lekérdezés lenne.
 */
final class ProgramSzoveg
{
    /** @return array{szoveg: string, gepi: bool} */
    public static function mezo(ProgramItem $program, TranslatableField $mezo, ?string $nyelv = null): array
    {
        $eredeti = (string) $program->{$mezo->value};
        if (($nyelv ?? Nyelv::aktualis()) !== 'ro') {
            return ['szoveg' => $eredeti, 'gepi' => false];
        }

        $legjobb = $program->translations
            ->filter(fn ($t) => $t->locale === 'ro' && $t->field === $mezo && $t->status->publicRank() > 0
                && trim((string) $t->translated_text) !== '')
            ->sortByDesc(fn ($t) => $t->status->publicRank())
            ->first();

        return $legjobb === null
            ? ['szoveg' => $eredeti, 'gepi' => false]
            : ['szoveg' => (string) $legjobb->translated_text, 'gepi' => $legjobb->status === TranslationStatus::MachineTranslated];
    }

    public static function cim(ProgramItem $program): string
    {
        return self::mezo($program, TranslatableField::Title)['szoveg'];
    }

    /*
     * A BEKÜLDÖTT SIMA SZÖVEG a 2026-os szabályokkal (lib/programfinder fullText/teaser + lib/linkify) — betűre:
     * a markdown-jelölők kiesnek, a bekezdés (üres sor) megmarad; a http(s) cím kattintható, a felirata a megadott
     * címke vagy a domain („pelda.hu/…”); a mondatvégi írásjel nem része a címnek. HTML a beküldött szövegben nem lehet:
     * minden szövegrész escape-elve megy ki, <a>-t csak mi építünk.
     */
    private const URL_RE = '~https?://[^\s<>"\'`]+~iu';

    private const TRAIL_RE = '~[.,;:!?)\]}»"\'…]+$~u';

    /** 2026: fullText — a felugró ablak teljes leírása (bekezdések megmaradnak). */
    public static function teljes(string $src): string
    {
        $t = str_replace(["\r\n", "\r"], "\n", $src);
        $t = preg_replace('/[*_#>`]/u', ' ', $t);
        $t = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $t);
        $t = preg_replace('/[ \t]+/u', ' ', $t);
        $t = preg_replace('/ *\n */u', "\n", $t);
        $t = preg_replace('/\n{3,}/u', "\n\n", $t);

        return trim($t);
    }

    /** A leírás bekezdésekre bontva, kattintható címekkel (2026: lib/program-modal `leiras`). */
    public static function html(string $szoveg): HtmlString
    {
        return new HtmlString(collect(preg_split('/\n{2,}/u', self::teljes($szoveg)) ?: [])
            ->map(fn ($b) => trim($b))->filter(fn ($b) => $b !== '')
            ->map(fn ($b) => '<p>' . self::linkek($b) . '</p>')->implode(''));
    }

    /** Szöveg kattintható címekkel (2026: fgLink.into); `$cimke`: a link felirata (pl. „Jegyvásárlás”), különben a domain. */
    public static function linkek(string $szoveg, ?string $cimke = null): HtmlString
    {
        $ki = '';
        foreach (self::reszek($szoveg) as [$fajta, $t]) {
            $ki .= $fajta === 'szoveg' ? e($t)
                : '<a class="fg-link" href="' . e($t) . '" target="_blank" rel="noopener noreferrer" title="' . e($t) . '">' . e(self::linkFelirat($t, $cimke)) . '</a>';
        }

        return new HtmlString($ki);
    }

    /** 2026: teaser(shortenLinks(...)) — a kártya-chip rövid szövege: a linkek helyén a felirat, szóhatáron vágva. */
    public static function rovid(string $szoveg, int $max, ?string $cimke = null): string
    {
        $t = trim(collect(self::reszek($szoveg))->map(fn ($r) => $r[0] === 'szoveg' ? $r[1] : self::linkFelirat($r[1], $cimke))->implode(''));
        $t = trim(preg_replace('/\s+/u', ' ', preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', preg_replace('/[*_#>`]/u', ' ', $t))));
        if (mb_strlen($t) <= $max) {
            return $t;
        }
        $c = mb_substr($t, 0, $max);
        $vag = max((int) mb_strrpos($c, ' '), (int) floor($max * 0.6));

        return preg_replace('/[,;:.\-–—]+$/u', '', mb_substr($c, 0, $vag)) . '…';
    }

    /** @return list<array{0: string, 1: string}> ['szoveg'|'link', tartalom] */
    private static function reszek(string $src): array
    {
        $ki = [];
        $utolso = 0;
        preg_match_all(self::URL_RE, $src, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[0] as [$url, $kezd]) {
            if ($kezd < $utolso) {
                continue;
            }
            $vagott = preg_replace(self::TRAIL_RE, '', $url);
            if ($kezd > $utolso) {
                $ki[] = ['szoveg', substr($src, $utolso, $kezd - $utolso)];
            }
            $ki[] = ['link', $vagott];
            $utolso = $kezd + strlen($vagott);
        }
        if ($utolso < strlen($src)) {
            $ki[] = ['szoveg', substr($src, $utolso)];
        }

        return $ki;
    }

    private static function linkFelirat(string $href, ?string $cimke): string
    {
        if ($cimke) {
            return $cimke;
        }
        $u = parse_url($href);
        if (! isset($u['host'])) {
            return mb_strlen($href) > 40 ? mb_substr($href, 0, 37) . '…' : $href;
        }
        $tovabb = (($u['path'] ?? '') !== '' && ($u['path'] ?? '') !== '/') || isset($u['query']) || isset($u['fragment']);

        return preg_replace('/^www\./', '', $u['host']) . ($tovabb ? '/…' : '');
    }
}
