<?php

namespace App\Services;

use App\Enums\OlympicsStatus;
use App\Models\OlympicsTeam;
use App\Support\JelentkezesLevelek;
use App\Support\Olimpia;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A Családi Olimpia jelentkezés fogadása (2026: olympics-api handleSubmit) — mezők, szabályok, hibaszövegek BETŰRE a 2026-osból.
 *
 * 🔴 A SZÍN-FOGLALÁS ATOMI, és NEM itt dől el: az `olympics_teams` (year, color_key) egyedi kulcsa. Két egyidejű beküldésből a
 * másodikat maga az adatbázis utasítja vissza → 409, emberi üzenet, FRISS színlista; a beírt adatok a böngészőben maradnak.
 * Az elvitt színt ezért szándékosan NEM ellenőrizzük előre („előbb megnézem, aztán beírom” — a két lépés közé beférne a
 * másik beküldés); a döntés az adatbázisé.
 *
 * KAPACITÁS: a korlátig `applied`, utána `waitlist`, a jelentkezés sorrendjében (`id`). A számolás + beszúrás egy zár alatt fut
 * (adatbázis-alapú zár — két beküldés nem számolhat egyszerre); utána a sorrend-javítás (helyreTesz) mégis végignézi az évet,
 * és ha két jelentkezés valahogy mégis versenyezett, a KÉSŐBBIT várólistára teszi. A választ a javítás UTÁNI állapot adja.
 */
class OlimpiaJelentkezes
{
    private const ZAR = 'olimpia-jelentkezes';

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: array<string, mixed>} HTTP-kód + válasz
     */
    public function fogad(array $data): array
    {
        $allapot = Olimpia::allapot();
        $r = $this->ellenoriz($data, $allapot);
        if ($r['errors'] !== []) {
            return [422, ['ok' => false, 'errors' => $r['errors']] + self::friss()];
        }

        $zar = Cache::lock(self::ZAR, 20);
        try {
            $zar->block(10);
        } catch (LockTimeoutException) {
            return [503, ['ok' => false, 'errors' => ['Most sokan jelentkeznek egyszerre. Próbáld újra pár másodperc múlva — a beírt adatok megmaradnak.']]];
        }

        try {
            // A státuszt a zár alatt, a FRISS állapotból számoljuk.
            $statusz = Olimpia::allapot()['varolista'] ? OlympicsStatus::Waitlist : OlympicsStatus::Applied;
            $szin = Olimpia::szin($r['color']);
            try {
                $csapat = OlympicsTeam::create([
                    'year' => Olimpia::ev(),
                    'team_name' => $r['team_name'],
                    'color_key' => $szin['key'],
                    'color_name' => $szin['name'],
                    'color_hex' => $szin['hex'],
                    'members' => $r['members'],
                    'captain_phone' => $r['captain_phone'],
                    'captain_email' => $r['captain_email'],
                    'declaration' => true,
                    'declaration_text' => Olimpia::NYILATKOZAT,
                    'status' => $statusz,
                ]);
            } catch (UniqueConstraintViolationException) {
                // A színt a beküldés PILLANATÁBAN vitte el más (2026-os szöveg). Friss lista; a beírt adatok a lapon maradnak.
                return [409, [
                    'ok' => false,
                    'colorTaken' => true,
                    'errors' => ["A(z) „{$szin['name']}\" színt az előbb elvitte egy másik csapat. Válassz másik színt — a beírt adatok megmaradnak."],
                ] + self::friss()];
            }
        } finally {
            $zar->release();
        }

        self::helyreTesz($csapat->year);
        $csapat->refresh();
        $varolista = $csapat->status === OlympicsStatus::Waitlist;

        // A levelek a mentés UTÁN; a hibájuk nem fordulhat át sikertelen jelentkezésbe (Notifier soha nem dob, de naplóz).
        JelentkezesLevelek::olimpiaVisszaigazolas($csapat);
        JelentkezesLevelek::olimpiaKoordinatornak($csapat);

        return [200, self::sikerValasz($varolista, $csapat->id)];
    }

    /** A siker válasza (a méz-bödön is ezt kapja — a robot ne tudja megkülönböztetni). @return array<string, mixed> */
    public static function sikerValasz(bool $varolista, ?int $id = null): array
    {
        $max = Olimpia::maxCsapat();

        return [
            'ok' => true,
            'id' => $id,
            'waitlist' => $varolista,
            'message' => $varolista
                ? "A jelentkezést megkaptuk, de a {$max} induló hely már betelt — a csapat VÁRÓLISTÁRA került. Ha valaki visszalép, a sorban következő csapatot értesítjük."
                : 'A jelentkezést megkaptuk, a csapat elindul a Családi Olimpián. A részletekről a csapatkapitányt e-mailben/telefonon értesítjük.',
        ];
    }

    /**
     * SORREND-JAVÍTÁS (2026: „kapacitás-korrekció”): az év csapatai a jelentkezés sorrendjében; aki a korlát UTÁN van, és az
     * űrlap tette `applied`-ba, az várólistára kerül. CSAK lefokoz, sosem léptet elő (a felszabadult helyre a koordinátor hívja
     * be a következőt — 2026-ban is így); az adminban kézzel állított státuszhoz (status_manual) nem nyúl, de beleszámol.
     *
     * @return int ennyi csapatot tett át várólistára (0 = rendben volt)
     */
    public static function helyreTesz(int $ev): int
    {
        $max = Olimpia::maxCsapat();
        $javitott = 0;
        DB::transaction(function () use ($ev, $max, &$javitott) {
            $indulo = 0;
            $csapatok = OlympicsTeam::query()->where('year', $ev)->where('status', '!=', OlympicsStatus::Withdrawn->value)
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($csapatok as $c) {
                if ($c->status !== OlympicsStatus::Applied) {
                    continue;
                }
                if ($indulo >= $max && ! $c->status_manual) {
                    $c->update(['status' => OlympicsStatus::Waitlist]);
                    $javitott++;

                    continue;
                }
                $indulo++;
            }
        });
        if ($javitott > 0) {
            // Ritka eset (a zár mellett elvileg nem fordul elő) — de ne legyen néma.
            Log::warning("OLIMPIA: a sorrend-javítás {$javitott} csapatot várólistára tett ({$ev}).");
        }

        return $javitott;
    }

    /** A friss színlista és kapacitás a böngészőnek (HTML-darabként — ugyanaz a sablon rajzolja, mint az első betöltéskor). */
    public static function friss(): array
    {
        $allapot = Olimpia::allapot();

        return [
            'colorsHtml' => view('jelentkezes._szinek', ['allapot' => $allapot])->render(),
            'capacityHtml' => view('jelentkezes._kapacitas', ['allapot' => $allapot])->render(),
        ];
    }

    /**
     * A 2026-os validate(), betűre ugyanazokkal a hibaszövegekkel.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $allapot
     * @return array{errors: list<string>, team_name: string, members: list<array{name: ?string, age: ?int}>, captain_phone: string, captain_email: string, color: string}
     */
    private function ellenoriz(array $data, array $allapot): array
    {
        $errors = [];
        $teamName = self::tisztit($data['team_name'] ?? '');
        if (mb_strlen($teamName) < 2) {
            $errors[] = 'Add meg a csapat nevét.';
        }

        // 7 tag: 1–5 kötelező, 6–7 opcionális. Ha egy opcionális tagnál csak az egyik mező van kitöltve, az HIBA
        // (különben félkész tag kerülne az adatbázisba).
        $members = [];
        for ($i = 1; $i <= Olimpia::TAGOK; $i++) {
            $name = self::tisztit($data["member{$i}_name"] ?? '');
            $ageRaw = trim((string) ($data["member{$i}_age"] ?? ''));
            $age = $ageRaw === '' ? null : (preg_match('/^\d{1,3}$/', $ageRaw) ? (int) $ageRaw : false);
            $joKor = is_int($age) && $age >= 1 && $age <= 120;
            if ($i <= Olimpia::KOTELEZO_TAGOK) {
                if (mb_strlen($name) < 2) {
                    $errors[] = "{$i}. csapattag: add meg a nevet.";
                }
                if (! $joKor) {
                    $errors[] = "{$i}. csapattag: add meg az életkort (szám).";
                }
            } elseif ($name !== '' || $age !== null) {
                if (mb_strlen($name) < 2) {
                    $errors[] = "{$i}. csapattag: ha megadod az életkort, a nevet is add meg.";
                }
                if (! $joKor) {
                    $errors[] = "{$i}. csapattag: ha megadod a nevet, az életkort is add meg.";
                }
            }
            $members[] = ['name' => $name !== '' ? $name : null, 'age' => $joKor ? $age : null];
        }

        $phone = self::tisztit($data['captain_phone'] ?? '');
        if (! preg_match('/^[+()\d][\d\s()\/.-]{5,}$/', $phone)) {
            $errors[] = 'Add meg a csapatkapitány telefonszámát.';
        }
        $email = trim((string) ($data['captain_email'] ?? ''));
        if (! preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email) || mb_strlen($email) > 190) {
            $errors[] = 'Adj meg érvényes e-mail címet (csapatkapitány).';
        }

        // A szín: létezik-e és választható-e (kikapcsolt). Hogy ELVITTÉK-E, azt az adatbázis dönti el (lásd fent).
        $colorKey = trim((string) ($data['color'] ?? ''));
        $color = collect($allapot['szinek'])->firstWhere('key', $colorKey);
        if ($color === null) {
            $errors[] = 'Válassz csapatszínt.';
        } elseif (! $color['active']) {
            $errors[] = "A(z) „{$color['name']}\" szín most nem választható — válassz másikat.";
        }

        if (! filter_var($data['declaration'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $errors[] = 'A nyilatkozat elfogadása kötelező.';
        }

        return [
            'errors' => $errors,
            'team_name' => mb_substr($teamName, 0, 120),
            'members' => $members,
            'captain_phone' => mb_substr($phone, 0, 40),
            'captain_email' => $email,
            'color' => $colorKey,
        ];
    }

    /** 2026: clean() — egy sorba, a többszörös szóköz egyre. */
    private static function tisztit(mixed $s): string
    {
        if (! is_string($s)) {
            return '';
        }

        return trim((string) preg_replace('/[ \t]+/u', ' ', (string) preg_replace('/[\r\n]+/u', ' ', $s)));
    }
}
