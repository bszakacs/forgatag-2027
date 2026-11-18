<?php

namespace App\Services\Forditas;

use Anthropic\Client;
use Anthropic\Messages\Message;
use RuntimeException;

/**
 * A Claude API-s fordító (hivatalos Anthropic PHP SDK). A 2026-os tooling/translate.mjs translateBatch és
 * tenyEllenorzes átirata: több mező EGY hívásban, kulcsonként visszaazonosítva (nem sorrend szerint), strukturált
 * (JSON-séma szerinti) válasszal; a rendszerprompt gyorsítótárazva (minden csomagnál ugyanaz).
 *
 * Az átmeneti hibákat (429, 5xx, hálózat) az SDK maga próbálja újra (maxRetries). Minden más hiba kivétel —
 * a futtató ilyenkor semmit nem ír ki az adott csomagból, és a hibát látható helyre teszi.
 */
final class ClaudeForditogep implements Forditogep
{
    private const ELLENORZO_PROMPT = <<<'TXT'
    Magyar→román fordításokat ellenőrzöl egy fesztivál programjaihoz. KÉT kérdésre válaszolsz mindegyik tételnél:

    1. HOZZÁTOLDÁS: van a román szövegben olyan tényállítás, ami a magyar forrásban NINCS benne?
    2. KIHAGYÁS: van a magyar forrásban olyan tényadat, ami a román szövegből KIESETT?

    Ami HOZZÁTOLDÁS:
    - kibontott rövidítés (a magyar „ELTE-HTK"-t ír, a román kiírja, melyik kar/intézet lehet)
    - intézmény-, személy-, együttes- vagy helyszínnév, ami a forrásban nem szerepel
    - dátum, időpont, időtartam, ár, korhatár, létszám, évszám, sorszám, ami a forrásban nem szerepel
    - magyarázat, pontosítás, „segítő" kiegészítés, ami a forrásból nem következik

    Ami KIHAGYÁS:
    - a magyarban szereplő dátum, időpont, ár, korhatár, létszám, helyszín, név, elérhetőség, amit a román nem említ
    - egy egész mondat vagy felsorolás-elem, ami tényadatot hordoz, és nincs meg románul
    - feltétel vagy megkötés („előzetes regisztráció szükséges", „csak 6 éves kortól"), ami kimaradt

    Ami EGYIK SEM (ne jelezd):
    - stílus, szórend, mondathossz, tömörítés, szinonima, természetesebb megfogalmazás
    - köznevek és általános kifejezések rendes lefordítása
    - a román nyelvtan megkövetelte alakok (határozott alak, ragozás, egyeztetés)
    - tulajdonnév meghagyása magyarul, vagy a szervezők által megadott román alakja
    - ami a magyar forrásban MÁS SZAVAKKAL, de benne van
    - díszítő jelző vagy hangulatfestő szó eltérése, ha tényadatot nem érint

    Ha nem vagy biztos benne, hogy tényleg tényadatról van szó, NE jelezd — a téves riasztás elveszi a hitelét az igaziaknak. Amit jelzel, ahhoz idézd a KONKRÉT szövegrészt, és írd meg egy rövid tagmondatban, mi a baj. Magyarul írj.
    TXT;

    private ?Client $kliens = null;

    /** @var array{hivas: int, be: int, ki: int, gyorsitotarbol: int} */
    private array $hasznalat = ['hivas' => 0, 'be' => 0, 'ki' => 0, 'gyorsitotarbol' => 0];

    public function fordit(array $tetelek, string $rendszerPrompt): array
    {
        $sema = [
            'type' => 'object',
            'properties' => [
                'translations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => ['type' => 'string', 'description' => 'A bemeneti tétel kulcsa, változatlanul.'],
                            'ro' => ['type' => 'string', 'description' => 'A román fordítás.'],
                        ],
                        'required' => ['key', 'ro'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['translations'],
            'additionalProperties' => false,
        ];
        $bemenet = array_map(fn ($k, $hu) => ['key' => (string) $k, 'hu' => $hu], array_keys($tetelek), $tetelek);

        $adat = $this->hivas(
            config('forditas.modell'),
            $rendszerPrompt,
            'Fordítsd le románra az alábbi tételek "hu" mezőjét. Minden tételhez pontosan egy fordítást adj vissza, ugyanazzal a "key" értékkel.'
                . "\n\n" . json_encode($bemenet, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            $sema,
        );

        $kulcsonkent = [];
        foreach ((array) ($adat['translations'] ?? []) as $t) {
            if (is_array($t) && isset($t['key'], $t['ro']) && is_string($t['ro'])) {
                $kulcsonkent[(string) $t['key']] = $t['ro'];
            }
        }
        // Nem csúsztatunk: ha egy kulcs hiányzik, inkább leállunk, mint hogy rossz mezőbe kerüljön a fordítás.
        $ki = [];
        foreach (array_keys($tetelek) as $k) {
            $ro = $kulcsonkent[(string) $k] ?? null;
            if ($ro === null || trim($ro) === '') {
                throw new RuntimeException('Hiányzó vagy üres fordítás a(z) ' . $k . ' tételre (' . count($kulcsonkent) . '/' . count($tetelek) . ' érkezett).');
            }
            $ki[$k] = $ro;
        }

        return $ki;
    }

    public function ellenoriz(array $parok): array
    {
        $sema = [
            'type' => 'object',
            'properties' => [
                'checks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'key' => ['type' => 'string', 'description' => 'A bemeneti tétel kulcsa, változatlanul.'],
                            'hozzatoldasok' => [
                                'type' => 'array',
                                'description' => 'Üres lista, ha a román szöveg rendben van.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'tipus' => ['type' => 'string', 'enum' => ['hozzátoldás', 'kihagyás'], 'description' => 'Melyik fajta baj.'],
                                        'leiras' => ['type' => 'string', 'description' => 'Az idézett szövegrész, majd rövid indoklás.'],
                                    ],
                                    'required' => ['tipus', 'leiras'],
                                    'additionalProperties' => false,
                                ],
                            ],
                        ],
                        'required' => ['key', 'hozzatoldasok'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['checks'],
            'additionalProperties' => false,
        ];
        $bemenet = array_map(fn ($k, $p) => ['key' => (string) $k, 'hu' => $p['hu'], 'ro' => $p['ro']], array_keys($parok), $parok);

        $adat = $this->hivas(
            config('forditas.ellenorzo_modell'),
            self::ELLENORZO_PROMPT,
            'Ellenőrizd az alábbi fordításpárokat. Minden tételhez pontosan egy választ adj vissza, ugyanazzal a "key" értékkel.'
                . "\n\n" . json_encode($bemenet, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            $sema,
        );

        $ki = array_fill_keys(array_map('strval', array_keys($parok)), []);
        foreach ((array) ($adat['checks'] ?? []) as $c) {
            $k = (string) ($c['key'] ?? '');
            if (! array_key_exists($k, $ki)) {
                continue; // ismeretlen kulcsra nem tippelünk
            }
            foreach ((array) ($c['hozzatoldasok'] ?? []) as $h) {
                $leiras = trim((string) ($h['leiras'] ?? ''));
                if ($leiras !== '') {
                    $ki[$k][] = (($h['tipus'] ?? '') === 'kihagyás' ? 'KIHAGYÁS: ' : 'HOZZÁTOLDÁS: ') . $leiras;
                }
            }
        }

        return $ki;
    }

    public function hasznalat(): array
    {
        return $this->hasznalat;
    }

    /** @return array<string, mixed> a válasz JSON-ja */
    private function hivas(string $modell, string $rendszer, string $uzenet, array $sema): array
    {
        $valasz = $this->kliens()->messages->create(
            maxTokens: (int) config('forditas.max_tokens'),
            messages: [['role' => 'user', 'content' => $uzenet]],
            model: $modell,
            system: [['type' => 'text', 'text' => $rendszer, 'cacheControl' => ['type' => 'ephemeral']]],
            outputConfig: [
                'effort' => config('forditas.effort'),
                'format' => ['type' => 'json_schema', 'schema' => $sema],
            ],
        );
        $this->szamol($valasz);

        // A válasz épségét MINDIG ellenőrizzük, mielőtt a tartalomhoz nyúlnánk.
        if ($valasz->stopReason === 'refusal') {
            throw new RuntimeException('A modell elutasította a kérést (' . ($valasz->stopDetails?->category ?? 'ismeretlen ok') . '). Ezt a programot kézzel kell fordítani.');
        }
        if ($valasz->stopReason === 'max_tokens') {
            throw new RuntimeException('Elfogyott a válasz-keret (max_tokens) — a fordítás csonka lenne. Csökkentsd a FORDITAS_CSOMAG_KARAKTER értékét.');
        }
        foreach ($valasz->content as $blokk) {
            if ($blokk->type === 'text') {
                $adat = json_decode($blokk->text, true);
                if (! is_array($adat)) {
                    throw new RuntimeException('A modell válasza nem érvényes JSON: ' . mb_substr($blokk->text, 0, 200));
                }

                return $adat;
            }
        }
        throw new RuntimeException('A modell nem adott szöveges választ.');
    }

    private function kliens(): Client
    {
        $kulcs = (string) config('forditas.kulcs');
        if ($kulcs === '') {
            throw new RuntimeException('Nincs ANTHROPIC_API_KEY a szerveri .env-ben.');
        }

        return $this->kliens ??= new Client(apiKey: $kulcs, requestOptions: ['maxRetries' => 3, 'timeout' => 180.0]);
    }

    private function szamol(Message $valasz): void
    {
        $this->hasznalat['hivas']++;
        $this->hasznalat['be'] += $valasz->usage->inputTokens + (int) $valasz->usage->cacheReadInputTokens + (int) $valasz->usage->cacheCreationInputTokens;
        $this->hasznalat['ki'] += $valasz->usage->outputTokens;
        $this->hasznalat['gyorsitotarbol'] += (int) $valasz->usage->cacheReadInputTokens;
    }
}
