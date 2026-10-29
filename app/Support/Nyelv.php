<?php

namespace App\Support;

/**
 * A publikus oldal két nyelve: magyar az alap (gyökér), román a /ro/ előtag alatt (mint 2026-ban).
 * A nyelvet az útvonal adja (App\Http\Middleware\PublikusNyelv állítja be).
 */
class Nyelv
{
    public const NYELVEK = ['hu', 'ro'];

    public static function aktualis(): string
    {
        return app()->getLocale() === 'ro' ? 'ro' : 'hu';
    }

    public static function masik(?string $nyelv = null): string
    {
        return ($nyelv ?? self::aktualis()) === 'ro' ? 'hu' : 'ro';
    }

    /** Belső útvonal az adott nyelven: '/rolunk' → '/ro/rolunk', '/' → '/ro'. Külső címhez nem nyúl. */
    public static function ut(string $ut, ?string $nyelv = null): string
    {
        if (! str_starts_with($ut, '/') || str_starts_with($ut, '//')) {
            return $ut;
        }
        if (($nyelv ?? self::aktualis()) !== 'ro') {
            return $ut;
        }

        return $ut === '/' ? '/ro' : '/ro' . $ut;
    }

    /** Kétnyelvű mező: románul a román, ha van — különben a magyar (CLAUDE.md 5.: üres hely nem lehet). */
    public static function valaszt(?string $hu, ?string $ro, ?string $nyelv = null): string
    {
        return (($nyelv ?? self::aktualis()) === 'ro' && filled($ro)) ? (string) $ro : (string) $hu;
    }

    /** Mint a valaszt(), de szerkesztőből jövő HTML-hez: az üres bekezdés („<p></p>”) is üresnek számít. */
    public static function valasztHtml(?string $hu, ?string $ro, ?string $nyelv = null): string
    {
        return (($nyelv ?? self::aktualis()) === 'ro' && trim(strip_tags((string) $ro)) !== '') ? (string) $ro : (string) $hu;
    }

    /** Dátum a nyelv szokása szerint: „2026. augusztus 3.” / „3 august 2026”. */
    public static function datum(\DateTimeInterface $d, ?string $nyelv = null): string
    {
        $nyelv ??= self::aktualis();
        $c = \Carbon\CarbonImmutable::instance($d)->locale($nyelv);

        return $nyelv === 'ro' ? $c->isoFormat('D MMMM YYYY') : $c->isoFormat('YYYY. MMMM D.');
    }
}
