<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A szervezői beállítások olvasása/írása (settings tábla, blokkonként egy sor).
 * Kulcs: „blokk.mező”, pl. Settings::get('submission.open').
 * Hiányzó blokk esetén hibával áll meg — nincs csendes alapérték, ami eltakarná a hiányt.
 */
class Settings
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = DB::table('settings')->pluck('value', 'key')
                ->map(fn ($v) => json_decode($v, true) ?? [])
                ->all();
        }

        return self::$cache;
    }

    /** @return array<string, mixed> */
    public static function block(string $block): array
    {
        $all = self::all();
        if (! array_key_exists($block, $all)) {
            throw new RuntimeException("Hiányzik a(z) „{$block}” beállítás-blokk (php artisan forgatag:torzsadatok tölti fel).");
        }

        return $all[$block];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        [$block, $field] = explode('.', $key, 2) + [1 => null];

        return $field === null ? self::block($block) : data_get(self::block($block), $field, $default);
    }

    /** @param array<string, array<string, mixed>> $blocks blokknév => mezők */
    public static function save(array $blocks): void
    {
        DB::transaction(function () use ($blocks) {
            foreach ($blocks as $block => $value) {
                DB::table('settings')->updateOrInsert(
                    ['key' => $block],
                    ['value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'updated_at' => now()],
                );
            }
        });
        self::$cache = null;
    }

    public static function has(string $block): bool
    {
        return array_key_exists($block, self::all());
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
