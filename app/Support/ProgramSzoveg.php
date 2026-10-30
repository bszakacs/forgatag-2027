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

    /**
     * A beküldött SIMA szöveg (leírás, jegyinfó) HTML-ként: előbb escape, aztán bekezdések (üres sor), sortörés,
     * és a http(s) címek kattinthatók. A beküldött szövegben HTML nem lehet — ami ott áll, szövegként látszik.
     */
    public static function html(string $szoveg): HtmlString
    {
        $bekezdesek = preg_split('/\n\s*\n/u', trim(str_replace(["\r\n", "\r"], "\n", $szoveg))) ?: [];
        $html = collect($bekezdesek)->filter(fn ($b) => trim($b) !== '')->map(function (string $b): string {
            $e = nl2br(e(trim($b)), false);
            $e = preg_replace_callback('~https?://[^\s<]+[^\s<.,;:!?)\]]~u',
                fn ($m) => '<a href="' . $m[0] . '" target="_blank" rel="noopener nofollow">' . $m[0] . '</a>', $e);

            return "<p>{$e}</p>";
        })->implode('');

        return new HtmlString($html);
    }

    /** Egysoros szöveg kattintható címekkel, a címek helyén a felirat (2026: a jegyinfó-chip a felugró ablakban). */
    public static function linkek(string $szoveg, string $felirat): HtmlString
    {
        $e = e(trim(preg_replace('/\s+/u', ' ', $szoveg)));

        return new HtmlString(preg_replace_callback('~https?://[^\s<]+[^\s<.,;:!?)\]]~u',
            fn ($m) => '<a href="' . $m[0] . '" target="_blank" rel="noopener nofollow">' . e($felirat) . '</a>', $e));
    }

    /** Rövid változat a listába: a címek helyén a felirat, szóhatáron vágva (2026: a kártyán sose álljon csonka URL). */
    public static function rovid(string $szoveg, int $max, string $linkFelirat): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', preg_replace('~https?://\S+~u', $linkFelirat, $szoveg)));

        return Str::limit($t, $max, '…', preserveWords: true);
    }
}
