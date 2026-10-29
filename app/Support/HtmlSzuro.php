<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * A szerkesztőben írt HTML (hírek, aloldal-blokkok) publikus megjelenítése — CSAK ezen át.
 * Engedélyezett címkék listája: ami nincs rajta (script, style, iframe, on*-attribútum, javascript: link,
 * inline stílus), az kimarad. A szerkesztő (App\Filament\Forms\Szovegszerkeszto) ezeknél többet nem is ad.
 */
class HtmlSzuro
{
    private static ?HtmlSanitizer $szuro = null;

    public static function tisztit(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        return (self::$szuro ??= self::epit())->sanitize((string) $html);
    }

    private static function epit(): HtmlSanitizer
    {
        $c = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener');
        foreach (['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'hr'] as $cimke) {
            $c = $c->allowElement($cimke);
        }

        return new HtmlSanitizer($c->allowElement('a', ['href', 'target']));
    }
}
