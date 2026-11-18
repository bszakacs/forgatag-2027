<?php

namespace App\Enums;

/**
 * Az admin-felhasználók szerepkörei (CLAUDE.md 4.) és a jogosultsági mátrix — EGY helyen.
 * A policy-k (app/Policies) és a lapok csak ezeket a kérdéseket teszik fel; szerepkörnevet sehol
 * máshol nem hasonlítunk.
 *
 * A kategóriagazda SOR-SZINTŰ szűkítése NEM itt van, hanem a modellen (App\Models\Scopes\RowAccess):
 * itt csak az dől el, hogy MIT tehet, az ott, hogy MELYIK sorokon.
 */
enum UserRole: string
{
    case SuperAdmin = 'superadmin';
    case Coordinator = 'coordinator';
    case CategoryOwner = 'category_owner';
    case Translator = 'translator';
    case ThemeEditor = 'theme_editor';
    case ReadOnly = 'read_only';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Szuperadmin',
            self::Coordinator => 'Programkoordinátor (jóváhagyó)',
            self::CategoryOwner => 'Kategóriagazda',
            self::Translator => 'Fordító',
            self::ThemeEditor => 'Arculat-szerkesztő',
            self::ReadOnly => 'Csak olvasó',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Mindent lát és mindent kezel, az admin-felhasználókat is.',
            self::Coordinator => 'Minden programot lát, jóváhagy és visszaküld; kezeli a programgazdákat és a Beállításokat.',
            self::CategoryOwner => 'CSAK a saját kategóriái programjait látja; átnézi, jóváhagyásra küldi vagy visszaküldi őket. Jóváhagyni nem tud.',
            self::Translator => 'Minden programot lát (olvasásra), és a programok román fordítását szerkeszti, átnézi, jóváhagyja. Mást nem módosíthat.',
            self::ThemeEditor => 'CSAK az arculat-szerkesztőhöz (Arculat / /admin/tema) fér hozzá, semmi máshoz.',
            self::ReadOnly => 'Minden programot lát, de semmit nem módosíthat.',
        };
    }

    /** @return array<string, string> érték → felirat (űrlapokhoz) */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }

    public function canSeePrograms(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Coordinator, self::CategoryOwner, self::Translator, self::ReadOnly], true);
    }

    /** Jóváhagyásra küldés és visszaküldés javításra. */
    public function canReviewPrograms(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Coordinator, self::CategoryOwner], true);
    }

    /** A közzététel joga: csak a jóváhagyó. */
    public function canApprovePrograms(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Coordinator], true);
    }

    /**
     * A programok román fordításának szerkesztése és státusza (átnézve / jóváhagyva) — a program lapján, egy képernyőn a
     * magyarral (CLAUDE.md 5.). A kategóriagazda NEM: a fordítás közös nyelvi munka, nem a kategória gazdájáé.
     */
    public function canTranslate(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Coordinator, self::Translator], true);
    }

    public function canManageProgramManagers(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Coordinator], true);
    }

    /**
     * Titkos beállítások (API-kulcs, később reCAPTCHA, SMTP-jelszó — App\Support\Titkok): CSAK a szuperadmin. A koordinátor
     * sem: a kulcs pénzt költ, nem csak adatot ad.
     */
    public function canManageSecrets(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function canManageUsers(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function canEditSettings(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Coordinator], true);
    }

    /**
     * A médiatár és a képes tartalmak (galériák, támogatók) kezelése. A kategóriagazda NEM: a médiatár nem
     * sor-szintű, egy közös tár — benne más kategóriák programjainak borítói is.
     */
    public function canManageMedia(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Coordinator], true);
    }

    /**
     * A „Tartalom” menücsoport: hírek, menü, aloldalak (a galériák és támogatók a canManageMedia alatt — ugyanaz
     * a kör). A kategóriagazda NEM: a saját tartalom nem kategóriához kötött, közös.
     */
    public function canManageContent(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Coordinator], true);
    }

    /**
     * A helyszínek térkép-adata (/admin/terkep-szerkeszto, PROMPT-18). A helyszín közös törzsadat (nem kategóriához kötött),
     * ezért ugyanaz a kör, mint a közös tartalomnál. Az arculat-szerkesztő NEM (PROMPT-18, kifejezetten).
     */
    public function canEditVenues(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Coordinator], true);
    }

    /** Az arculat (/admin/tema) szerkesztése és publikálása. */
    public function canEditTheme(): bool
    {
        return in_array($this, [self::SuperAdmin, self::ThemeEditor], true);
    }

    /** Az irányítópult (az admin nyitólapja) — az arculat-szerkesztő a /tema-ra érkezik helyette. */
    public function canSeeDashboard(): bool
    {
        return $this !== self::ThemeEditor;
    }

    /** Sor-szintű szűkítés alatt áll-e (csak a hozzárendelt kategóriák programjai). */
    public function isRowRestricted(): bool
    {
        return $this === self::CategoryOwner;
    }
}
