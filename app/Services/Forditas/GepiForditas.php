<?php

namespace App\Services\Forditas;

use App\Enums\ProgramStatus;
use App\Enums\TranslatableField;
use App\Enums\TranslationStatus;
use App\Models\ProgramItem;
use App\Models\ProgramItemTranslation;
use App\Support\Forditas\VedettNevek;
use App\Support\Settings;
use App\Support\Titkok;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A GÉPI FORDÍTÁS futása (HU → RO) — EGY helyen (CLAUDE.md 5., PROMPT-19). A 2026-os tooling/translate.mjs átirata.
 *
 * HATÓKÖR: CSAK a programok címe, leírása, jegyinformációja (TranslatableField), a ProgramStatus::translatableValues()
 * állapotú programokon — a vázlaton is, hogy a koordinátor egy körben lássa a magyart és a románt. Saját tartalomhoz
 * (hír, lap, kategória, helyszín, menü) NEM nyúl: ez a lekérdezés csak a program_item_translations táblát írja.
 *
 * AZ EMBERI FORDÍTÁS SÉRTHETETLEN: a reviewed / approved / outdated sort a gép nem írja felül. Ha egy átnézett sor
 * magyar forrása megváltozott, a sor `outdated` lesz, és ÚGY MARAD, amíg ember hozzá nem nyúl.
 *
 * NÉGY GARANCIA (2026-ból): egyszerre egy futás (zár) · üres futásnál NINCS API-hívás · kulcs nélkül csendben kimarad ·
 * hibánál nem dönt be semmit, de a hiba LÁTSZIK (a Beállítások lapon, a nyitólapon és a naplóban).
 * A változatlan szöveget nem fordítja újra (a forrás lenyomata: source_text_hash).
 *
 * Indítás: (1) a program mentésekor, a válasz elküldése UTÁN (utemez — beküldés, a beküldő javítása, admin-módosítás);
 * (2) a pótló futás (forgatag:forditas, cPanel cron); (3) a Beállítások „Futtatás most” gombja.
 */
final class GepiForditas
{
    private const ZAR = 'forgatag-forditas';

    /** A futási jelentés helye: a settings tábla egy sora (nem szerkeszthető blokk, nem kerül a Beállítások űrlapjába). */
    public const JELENTES = 'translation_run';

    /** @var array<int, true> a kérés végén fordítandó programok */
    private static array $utemezett = [];

    public function __construct(private readonly Forditogep $gep) {}

    public static function vanKulcs(): bool
    {
        return Titkok::van('anthropic_api_key');   // admin (Titkos beállítások) > szerveri .env
    }

    /**
     * A program magyar szövege megváltozott (a ProgramItem `saved` eseménye hívja). Az ÁTNÉZETT/JÓVÁHAGYOTT sor
     * azonnal `outdated` lesz — nem a következő futásig vár: addig se látszódjon a román oldalon a régi szöveg.
     * A gépi sort nem jelöljük (azt a gép újrafordítja; addig a publikus oldal a lenyomat-eltérés miatt nem mutatja).
     *
     * @param  list<TranslatableField>  $mezok
     */
    public static function forrasValtozott(ProgramItem $program, array $mezok): int
    {
        $db = 0;
        foreach ($mezok as $mezo) {
            $h = ProgramItemTranslation::hashSource($program->{$mezo->value});
            $program->translations()->where('field', $mezo->value)
                ->whereIn('status', [TranslationStatus::Reviewed->value, TranslationStatus::Approved->value])
                ->get()
                ->each(function (ProgramItemTranslation $t) use ($h, &$db) {
                    if ($t->source_text_hash !== $h) {
                        $t->update(['status' => TranslationStatus::Outdated]);
                        $db++;
                    }
                });
        }

        return $db;
    }

    /**
     * A program fordítása a kérés VÉGÉN (a válasz elküldése után — a beküldő nem vár rá). Egy kérésben bárhány program
     * egyetlen futásba gyűlik. Kulcs nélkül nem ütemez (nincs mit tenni; a nyitólap kiírja, hogy nincs kulcs).
     */
    public static function utemez(int $programId): void
    {
        // Kulcs nélkül nincs mit tenni. Parancssorban (a telepítés behozatalai) sem: ott a pótló futás veszi fel —
        // különben egy 180 programos behozatal a telepítés részeként fordítana.
        if (! self::vanKulcs() || (app()->runningInConsole() && ! app()->runningUnitTests())) {
            return;
        }
        self::$utemezett[$programId] = true;
        // NEVESÍTETT halasztás: egy kérésben akárhány mentés EGY futást ad (az azonos nevű felülírja az előzőt).
        defer(function () {
            $idk = array_keys(self::$utemezett);
            self::$utemezett = [];
            if ($idk === []) {
                return;
            }
            @set_time_limit(600);
            ignore_user_abort(true);
            try {
                app(self::class)->futtat($idk, 'mentés');
            } catch (Throwable $e) {
                Log::error('FORDÍTÁS: a mentés utáni futás hibával állt meg: ' . $e->getMessage());
            }
        }, 'forgatag-forditas');
    }

    /**
     * Egy futás. Ha $programIdk adott, csak azokat nézi (mentés utáni futás); különben mindet (pótló futás).
     *
     * @param  list<int>|null  $programIdk
     * @return array<string, mixed> a jelentés (ugyanez kerül a settings táblába és a naplóba)
     */
    public function futtat(?array $programIdk = null, string $indito = 'kézi', ?int $limit = null, bool $proba = false): array
    {
        $j = [
            'indito' => $indito, 'kezdet' => now()->toIso8601String(), 'vege' => null, 'allapot' => null,
            'hatokor' => $programIdk === null ? 'minden program' : count($programIdk) . ' program',
            'naprakesz' => 0, 'emberi' => 0, 'elavult_most' => 0, 'elavult_osszes' => 0, 'forditando' => 0,
            'forditva' => 0, 'jelzett' => 0, 'api_hivas' => 0, 'token_be' => 0, 'token_ki' => 0, 'koltseg_usd' => 0.0,
            'figyelmeztetesek' => [], 'hiba' => null,
        ];

        $elotte = $this->gep->hasznalat();                       // a jelentés CSAK ennek a futásnak a hívásait számolja
        $zar = Cache::lock(self::ZAR, 1800);
        if (! $zar->get()) {
            $j['allapot'] = 'fut_mar';
            Log::info('FORDÍTÁS: már fut egy fordítás (zár) — most kihagyom.');

            return $j;
        }

        try {
            $teendo = $this->osszegyujt($programIdk, $j, $proba);
            $j['elavult_osszes'] = ProgramItemTranslation::query()->where('status', TranslationStatus::Outdated->value)->count();
            $j['forditando'] = count($teendo);
            if ($limit !== null && $limit > 0) {
                $teendo = array_slice($teendo, 0, $limit);
            }

            if ($teendo === []) {
                $j['allapot'] = 'nincs_teendo';                 // ÜRES FUTÁS: API-hívás NINCS (a gépet meg sem szólítjuk)
            } elseif ($proba) {
                $j['allapot'] = 'proba';
            } elseif (! self::vanKulcs()) {
                $j['allapot'] = 'nincs_kulcs';                  // csendben kimarad — a nyitólap kiírja
            } else {
                $this->fordit($teendo, $j);
                $j['allapot'] ??= 'kesz';
            }
        } catch (Throwable $e) {
            $j['allapot'] = 'hiba';
            $j['hiba'] = $e->getMessage();
        } finally {
            $zar->release();
        }

        $h = $this->gep->hasznalat();
        $j['api_hivas'] = $h['hivas'] - $elotte['hivas'];
        $j['token_be'] = $h['be'] - $elotte['be'];
        $j['token_ki'] = $h['ki'] - $elotte['ki'];
        $h = ['be' => $j['token_be'], 'ki' => $j['token_ki']];
        $j['koltseg_usd'] = round(($h['be'] * (float) config('forditas.ar_be') + $h['ki'] * (float) config('forditas.ar_ki')) / 1_000_000, 4);
        $j['vege'] = now()->toIso8601String();

        $this->naploz($j);
        if (! $proba) {
            Settings::save([self::JELENTES => $j]);
        }

        return $j;
    }

    /** @return array<string, mixed>|null az utolsó futás jelentése */
    public static function utolsoFutas(): ?array
    {
        return Settings::has(self::JELENTES) ? Settings::block(self::JELENTES) : null;
    }

    /**
     * Mi a teendő? Közben az átnézett sorokon a lenyomatot is ellenőrzi (elavult → `outdated`), ahogy 2026-ban.
     *
     * @return list<array{program: int, mezo: TranslatableField, hu: string, hash: string, cim: string}>
     */
    private function osszegyujt(?array $programIdk, array &$j, bool $proba): array
    {
        $teendo = [];
        $q = ProgramItem::query()->whereIn('status', ProgramStatus::translatableValues())
            ->with(['translations' => fn ($t) => $t->where('locale', 'ro')])->orderBy('id');
        if ($programIdk !== null) {
            $q->whereIn('id', $programIdk);
        }

        foreach ($q->lazy(100) as $program) {
            foreach (TranslatableField::cases() as $mezo) {
                $hu = trim((string) $program->{$mezo->value});
                $sor = $program->translations->first(fn ($t) => $t->field === $mezo);
                $h = ProgramItemTranslation::hashSource($hu);

                if ($hu === '') {
                    // A forrás kiürült (pl. törölték a jegyinformációt): a gépi sor megy, az emberi elavul.
                    if ($sor && ! $proba) {
                        if ($sor->status->isMachineWritable()) {
                            $sor->delete();
                        } elseif ($sor->status !== TranslationStatus::Outdated) {
                            $sor->update(['status' => TranslationStatus::Outdated]);
                        }
                    }

                    continue;
                }

                if ($sor && in_array($sor->status, [TranslationStatus::Reviewed, TranslationStatus::Approved], true)) {
                    if ($sor->source_text_hash !== null && $sor->source_text_hash !== $h) {
                        $j['elavult_most']++;
                        $proba || $sor->update(['status' => TranslationStatus::Outdated]);
                    } else {
                        $j['emberi']++;
                        if ($sor->source_text_hash === null && ! $proba) {
                            $sor->update(['source_text_hash' => $h]);   // a jövőbeli követéshez
                        }
                    }

                    continue;
                }
                if ($sor && $sor->status === TranslationStatus::Outdated) {
                    continue;                                       // emberre vár — a gép nem nyúl hozzá
                }
                if ($sor && $sor->source_text_hash === $h) {
                    $j['naprakesz']++;                              // gépi és naprakész: NEM fordítjuk újra

                    continue;
                }

                $teendo[] = ['program' => $program->id, 'mezo' => $mezo, 'hu' => $hu, 'hash' => $h, 'cim' => $program->title];
            }
        }

        return $teendo;
    }

    /** @param list<array{program: int, mezo: TranslatableField, hu: string, hash: string, cim: string}> $teendo */
    private function fordit(array $teendo, array &$j): void
    {
        $szabalyok = VedettNevek::beallitasbol();
        $prompt = $szabalyok->forditoPrompt();

        foreach ($this->csomagok($teendo) as $csomag) {
            $tetelek = [];
            foreach ($csomag as $i => $t) {
                $tetelek["t{$i}"] = $t['hu'];
            }
            try {
                $ro = $this->gep->fordit($tetelek, $prompt);
            } catch (Throwable $e) {
                // Ebből a csomagból SEMMI nem íródik ki; a már kiírt csomagok érvényesek. A hiba látszik.
                $j['allapot'] = 'hiba';
                $j['hiba'] = 'A fordítás megállt („' . mb_substr($csomag[array_key_first($csomag)]['cim'], 0, 60) . '” csomagjánál): ' . $e->getMessage();

                return;
            }

            $tenyek = [];
            if (config('forditas.ellenorzes')) {
                try {
                    $parok = [];
                    foreach ($csomag as $i => $t) {
                        $parok["t{$i}"] = ['hu' => $t['hu'], 'ro' => $ro["t{$i}"]];
                    }
                    $tenyek = $this->gep->ellenoriz($parok);
                } catch (Throwable $e) {
                    // Az ellenőrzés kimaradása nem ok arra, hogy a fordítás elvesszen — de látszódjon.
                    $j['figyelmeztetesek'][] = 'A tényadat-ellenőrzés egy csomagnál kimaradt: ' . $e->getMessage();
                }
            }

            foreach ($csomag as $i => $t) {
                $jegyzet = [...$szabalyok->ellenoriz($t['hu'], $ro["t{$i}"]), ...($tenyek["t{$i}"] ?? [])];
                if ($this->kiir($t, $ro["t{$i}"], $jegyzet)) {
                    $j['forditva']++;
                    if ($jegyzet !== []) {
                        $j['jelzett']++;
                        $j['figyelmeztetesek'][] = '„' . mb_substr($t['cim'], 0, 60) . '” (' . $t['mezo']->label() . '): ' . $jegyzet[0];
                    }
                }
            }
        }
    }

    /**
     * Egy mező kiírása — közben a sort ÚJRA olvassuk zárolva: ha azóta ember nyúlt hozzá, vagy a magyar szöveg
     * megint változott, nem írunk (az emberi munka sérthetetlen; a változást a következő futás veszi fel).
     */
    private function kiir(array $t, string $ro, array $jegyzet): bool
    {
        return DB::transaction(function () use ($t, $ro, $jegyzet) {
            $program = ProgramItem::query()->whereKey($t['program'])->lockForUpdate()->first();
            if (! $program || ProgramItemTranslation::hashSource($program->{$t['mezo']->value}) !== $t['hash']) {
                return false;
            }
            $sor = $program->translations()->where('locale', 'ro')->where('field', $t['mezo']->value)->lockForUpdate()->first();
            if ($sor && ! $sor->status->isMachineWritable()) {
                return false;
            }
            $adat = [
                'translated_text' => $ro, 'source_text_hash' => $t['hash'],
                'status' => TranslationStatus::MachineTranslated,
                // MINDIG felülíródik (üresre is): egy korábbi riasztás ne ragadjon egy azóta rendben lévő szövegen.
                'check_note' => $jegyzet === [] ? null : implode("\n", $jegyzet),
            ];
            $sor ? $sor->update($adat) : $program->translations()->create(['locale' => 'ro', 'field' => $t['mezo']] + $adat);

            return true;
        });
    }

    /** Csomagok darabszám ÉS karakterszám szerint. @return list<list<array>> */
    private function csomagok(array $teendo): array
    {
        $ki = [];
        $cur = [];
        $kar = 0;
        foreach ($teendo as $t) {
            if ($cur !== [] && (count($cur) >= (int) config('forditas.csomag_tetel') || $kar + mb_strlen($t['hu']) > (int) config('forditas.csomag_karakter'))) {
                $ki[] = $cur;
                $cur = [];
                $kar = 0;
            }
            $cur[] = $t;
            $kar += mb_strlen($t['hu']);
        }
        if ($cur !== []) {
            $ki[] = $cur;
        }

        return $ki;
    }

    private function naploz(array $j): void
    {
        $sor = 'FORDÍTÁS (' . $j['indito'] . ', ' . $j['hatokor'] . '): ' . self::allapotSzoveg($j)
            . " · naprakész {$j['naprakesz']} · emberi megőrizve {$j['emberi']} · most elavult {$j['elavult_most']}"
            . " · elavult összesen {$j['elavult_osszes']} · lefordítva {$j['forditva']}/{$j['forditando']} · jelzett {$j['jelzett']}"
            . " · API-hívás: {$j['api_hivas']} (be {$j['token_be']}, ki {$j['token_ki']} token, kb. {$j['koltseg_usd']} USD)";
        $j['allapot'] === 'hiba' ? Log::error($sor . ' · HIBA: ' . $j['hiba']) : Log::info($sor);
        foreach ($j['figyelmeztetesek'] as $f) {
            Log::warning('FORDÍTÁS — figyelem: ' . $f);
        }
    }

    /** Emberi nyelven, egy helyen (napló, Beállítások, nyitólap). */
    public static function allapotSzoveg(array $j): string
    {
        return match ($j['allapot'] ?? null) {
            'kesz' => 'kész',
            'nincs_teendo' => 'nem volt mit fordítani (API-hívás nem történt)',
            'nincs_kulcs' => 'KIMARADT: nincs Claude API-kulcs (Beállítások → Titkos beállítások, vagy a szerveri .env)',
            'fut_mar' => 'kimaradt: egy másik fordítás épp futott',
            'proba' => 'próbafutás (nem írt, nem hívott API-t)',
            'hiba' => 'HIBA: ' . ($j['hiba'] ?? 'ismeretlen'),
            default => 'ismeretlen',
        };
    }
}
