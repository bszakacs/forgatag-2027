<?php

namespace App\Support;

/**
 * Szöveg-normálizálás beküldés előtt: emoji-tiltás, szóköz-összevonás, CSUPA NAGYBETŰ → normál.
 * A 2026-os submit-api clean() függvényének átvétele; a böngészőbeli párja: public/js/bekuldes.js
 * (normText) — a kettő szabálya azonos.
 */
class TextCleaner
{
    private const EMOJI = '/[\p{Extended_Pictographic}\x{1F1E6}-\x{1F1FF}]/u';

    public static function clean(mixed $s, bool $multiline = false): string
    {
        if (! is_string($s)) {
            return '';
        }
        $out = self::stripEmoji($s);
        $out = $multiline ? str_replace("\r\n", "\n", $out) : preg_replace('/[\r\n]+/', ' ', $out);
        $out = self::collapseWs($out);

        return self::fixAllCaps($out);
    }

    public static function stripEmoji(string $s): string
    {
        // A U+FE0F (emoji-változatjelölő) magában is ottmaradna, ezt külön vesszük ki.
        return str_replace("\u{FE0F}", '', (string) preg_replace(self::EMOJI, '', $s));
    }

    public static function collapseWs(string $s): string
    {
        $s = preg_replace('/[ \t]+/', ' ', $s);
        $s = preg_replace('/\s*\n\s*\n\s*/', "\n\n", $s);
        $s = preg_replace('/\n{3,}/', "\n\n", $s);

        return trim($s);
    }

    /** Ha a szöveg szinte csak nagybetű (és van benne legalább néhány betű), normálra hozzuk. */
    public static function fixAllCaps(string $s): string
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $s);
        if (mb_strlen($letters) < 4) {
            return $s;
        }
        $upper = preg_match_all('/\p{Lu}/u', $s);
        $lower = preg_match_all('/\p{Ll}/u', $s);
        if ($upper >= 4 && $lower === 0) {
            $low = mb_strtolower($s, 'UTF-8');

            // mondatkezdő nagybetű
            return preg_replace_callback(
                '/(^\s*\p{L})|([.!?]\s+\p{L})/u',
                fn (array $m) => mb_strtoupper($m[0], 'UTF-8'),
                $low,
            );
        }

        return $s;
    }
}
