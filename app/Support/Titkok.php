<?php

namespace App\Support;

use App\Models\SecretSetting;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * TITKOS BEÁLLÍTÁSOK — EGY helyen (API-kulcs; később reCAPTCHA, SMTP-jelszó). Három szabály:
 *
 *  1. CSAK SZUPERADMIN látja és állítja (UserRole::canManageSecrets) — a kulcs pénzt költ, nem csak adatot ad.
 *  2. ÍRHATÓ, DE NEM OLVASHATÓ: a felület soha nem kapja meg az értéket, csak hogy be van-e állítva, ki és mikor
 *     cserélte (allapot). Az értéket csak a kód kéri el (ertek), a felhasználás helyén.
 *  3. EGYÉRTELMŰ ELSŐBBSÉG: az ADMINBAN megadott érték NYER; a szerveri .env csak tartalék, ha az adminban nincs.
 *     (Így a lejárt kulcsot a fájlhoz nem férő kolléga is lecserélheti.) Hogy melyik van használatban, a felület kiírja.
 *
 * Tárolás: secret_settings, a Laravel titkosításával (APP_KEY). ⚠ A titkosított érték a mentésbe is bekerül — a
 * mentés az APP_KEY-jel együtt visszafejthető, ezért a mentést titokként kell kezelni (ALLAPOT.md).
 *
 * Új titok = új sor a KULCSOK-ban: felirat, súgó, és a .env-tartalék config-kulcsa (NEM env() — az a gyorsítótárazott
 * beállításnál üres lenne).
 */
final class Titkok
{
    public const KULCSOK = [
        'anthropic_api_key' => [
            'felirat' => 'Claude API-kulcs (gépi fordítás)',
            'sugo' => 'A console.anthropic.com → API Keys oldalon készül („sk-ant-…”). Ezzel fizet a gépi fordítás.',
            'config' => 'forditas.kulcs',
            'env' => 'ANTHROPIC_API_KEY',
        ],
    ];

    /** A titok értéke a FELHASZNÁLÁS helyén (soha nem a felületen). Admin > .env > null. */
    public static function ertek(string $kulcs): ?string
    {
        self::ismert($kulcs);
        $sor = SecretSetting::query()->where('key', $kulcs)->first();
        if ($sor) {
            try {
                $v = trim((string) $sor->value);
            } catch (DecryptException) {
                // Néma hiba tilos: NEM esünk vissza csendben a .env-re (az már egy másik kulcs lenne).
                throw new RuntimeException('A(z) „' . self::KULCSOK[$kulcs]['felirat'] . '” nem fejthető vissza (megváltozott az APP_KEY?). Add meg újra: Beállítások → Titkos beállítások.');
            }
            if ($v !== '') {
                return $v;
            }
        }
        $env = trim((string) config(self::KULCSOK[$kulcs]['config']));

        return $env !== '' ? $env : null;
    }

    /** Van-e használható érték (visszafejtés nélkül — a nyitólap figyelmeztetése ne törjön el tőle). */
    public static function van(string $kulcs): bool
    {
        return self::forras($kulcs) !== null;
    }

    /** @return 'admin'|'env'|null melyik érték van használatban */
    public static function forras(string $kulcs): ?string
    {
        self::ismert($kulcs);
        if (SecretSetting::query()->where('key', $kulcs)->exists()) {
            return 'admin';
        }

        return trim((string) config(self::KULCSOK[$kulcs]['config'])) !== '' ? 'env' : null;
    }

    /**
     * A felületnek: CSAK az állapot, az érték soha.
     *
     * @return array{felirat: string, sugo: string, forras: ?string, env_is: bool, cserelve: ?\Illuminate\Support\Carbon, ki: ?string, hiba: ?string, szoveg: string}
     */
    public static function allapot(string $kulcs): array
    {
        self::ismert($kulcs);
        $k = self::KULCSOK[$kulcs];
        $sor = SecretSetting::query()->where('key', $kulcs)->first();
        $envIs = trim((string) config($k['config'])) !== '';
        $hiba = null;
        if ($sor) {
            try {
                $sor->value;   // csak a visszafejthetőség próbája — az érték nem hagyja el ezt a metódust
            } catch (Throwable) {
                $hiba = 'A tárolt érték NEM fejthető vissza (megváltozott az APP_KEY?) — add meg újra.';
            }
        }
        $forras = $sor ? 'admin' : ($envIs ? 'env' : null);
        $szoveg = match ($forras) {
            'admin' => 'Be van állítva itt, az adminban — cserélve: ' . $sor->updated_at->timezone(config('app.timezone'))->format('Y. m. d. H:i')
                . ($sor->updated_by ? ' (' . $sor->updated_by . ')' : '') . '. EZ VAN HASZNÁLATBAN.'
                . ($envIs ? ' A szerveri .env-ben is van érték (' . $k['env'] . '), de az adminban megadott nyer.' : ''),
            'env' => 'Az adminban nincs megadva — a szerveri .env értéke (' . $k['env'] . ') van használatban. Ha itt megadod, az fog nyerni.',
            default => 'NINCS BEÁLLÍTVA — sem itt, sem a szerveri .env-ben (' . $k['env'] . ').',
        };

        return ['felirat' => $k['felirat'], 'sugo' => $k['sugo'], 'forras' => $forras, 'env_is' => $envIs,
            'cserelve' => $sor?->updated_at, 'ki' => $sor?->updated_by, 'hiba' => $hiba, 'szoveg' => $szoveg];
    }

    /** Új érték (csere). Csak szuperadmin. Az érték nem kerül naplóba, csak a tény. */
    public static function beallit(string $kulcs, string $ertek, User $ki): void
    {
        self::ismert($kulcs);
        abort_unless($ki->role->canManageSecrets(), 403);
        $ertek = trim($ertek);
        if ($ertek === '') {
            throw new RuntimeException('Üres értéket nem lehet menteni. Törléshez a „Törlés” gombot használd.');
        }
        SecretSetting::query()->updateOrCreate(['key' => $kulcs], ['value' => $ertek, 'updated_by' => $ki->name]);
        Log::info("TITOK: „{$kulcs}” beállítva / cserélve ({$ki->name}).");
    }

    /** Törlés: ezután a .env értéke (ha van) lép életbe. Csak szuperadmin. */
    public static function torol(string $kulcs, User $ki): void
    {
        self::ismert($kulcs);
        abort_unless($ki->role->canManageSecrets(), 403);
        SecretSetting::query()->where('key', $kulcs)->delete();
        Log::info("TITOK: „{$kulcs}” törölve az adminból ({$ki->name}).");
    }

    private static function ismert(string $kulcs): void
    {
        if (! array_key_exists($kulcs, self::KULCSOK)) {
            throw new RuntimeException("Ismeretlen titkos beállítás: {$kulcs}");
        }
    }
}
