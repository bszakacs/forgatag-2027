<?php

namespace App\Services;

use App\Enums\VolunteerStatus;
use App\Models\VolunteerApplication;
use App\Support\JelentkezesLevelek;
use App\Support\Onkentes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Az önkéntes-jelentkezés fogadása (2026: volunteer-api handleSubmit) — mezők, szabályok, hibaszövegek BETŰRE a 2026-osból.
 *
 * 🔴 KISKORÚAK ADATAI. A két dokumentum (szülői beleegyezés, kézzel írt motivációs levél) a ZÁRT tárba kerül
 * (storage/app/private/onkentesek/<év>/<véletlen név>), SOHA nem a nyilvános médiatárba. A fájl típusát a TARTALMÁBÓL döntjük
 * el (nem a böngésző állításából, nem a kiterjesztésből), a nevét mi adjuk.
 *
 * A KORHATÁR (Beállítások, ma 16) alatt mindkét fájl KÖTELEZŐ; felette opcionális, de ha feltölti, a formátumot és a méretet
 * akkor is ellenőrizzük. Ha a mentés nem sikerül, a már eltárolt fájlokat töröljük (ne maradjon gazdátlan személyes adat).
 */
class OnkentesJelentkezes
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $fajlok  'szuloi' / 'motivacios'
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function fogad(array $data, array $fajlok): array
    {
        $r = $this->ellenoriz($data, $fajlok);
        if ($r['errors'] !== []) {
            return [422, ['ok' => false, 'errors' => $r['errors']]];
        }

        $tarolt = [];
        try {
            foreach ($r['fajlok'] as $kulcs => $f) {
                $tarolt[$kulcs] = $this->tarol($f['file'], $f['ext']) + ['mime' => $f['mime']];
            }
        } catch (Throwable $e) {
            $this->takarit($tarolt);
            Log::error('ÖNKÉNTES: a dokumentum mentése nem sikerült. ' . $e->getMessage());

            return [502, ['ok' => false, 'errors' => ['A dokumentum feltöltése nem sikerült. Próbáld újra.']]];
        }

        try {
            $jelentkezes = VolunteerApplication::create([
                'year' => Onkentes::ev(),
                'full_name' => $r['full_name'],
                'age' => $r['age'],
                'email' => $r['email'],
                'was_volunteer' => $r['was_volunteer'],
                'prev_experience' => $r['was_volunteer'] ? ($r['prev_experience'] ?: null) : null,
                'motivation' => $r['motivation'],
                'available_days' => $r['days'],
                'areas' => $r['areas'],
                'has_license' => $r['has_license'],
                'health_notes' => $r['health_notes'],
                'dietary' => $r['dietary'] !== [] ? $r['dietary'] : null,
                'dietary_other' => $r['dietary_other'] ?: null,
                'emergency_name' => $r['emergency_name'],
                'emergency_phone' => $r['emergency_phone'],
                'parental_consent_path' => $tarolt['szuloi']['ut'] ?? null,
                'parental_consent_mime' => $tarolt['szuloi']['mime'] ?? null,
                'motivation_letter_path' => $tarolt['motivacios']['ut'] ?? null,
                'motivation_letter_mime' => $tarolt['motivacios']['mime'] ?? null,
                'privacy_consent' => true,
                'privacy_text' => Onkentes::HOZZAJARULAS,
                'status' => VolunteerStatus::New,
            ]);
        } catch (Throwable $e) {
            $this->takarit($tarolt);
            Log::error('ÖNKÉNTES: a jelentkezés mentése nem sikerült. ' . $e->getMessage());

            return [502, ['ok' => false, 'errors' => ['A beküldés mentése nem sikerült. Próbáld újra később.']]];
        }

        JelentkezesLevelek::onkentesKoordinatornak($jelentkezes);

        return [200, ['ok' => true, 'id' => $jelentkezes->id]];
    }

    /** @return array{ut: string} */
    private function tarol(UploadedFile $f, string $ext): array
    {
        $ut = Storage::disk('local')->putFileAs(
            Onkentes::MAPPA . '/' . Onkentes::ev(),
            $f,
            Str::random(40) . '.' . $ext,
        );
        if (! is_string($ut) || $ut === '' || ! Storage::disk('local')->exists($ut)) {
            throw new \RuntimeException('a fájl nem került a zárt tárba');
        }

        return ['ut' => $ut];
    }

    /** @param array<string, array{ut: string}> $tarolt */
    private function takarit(array $tarolt): void
    {
        foreach ($tarolt as $t) {
            Storage::disk('local')->delete($t['ut']);
        }
    }

    /**
     * A 2026-os validate(), betűre ugyanazokkal a hibaszövegekkel. A napok és a területek CSAK a Beállításokban megadottak
     * közül fogadhatók el (ami nincs a listán, az kimarad — 2026-ban is).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $fajlok
     * @return array<string, mixed>
     */
    private function ellenoriz(array $data, array $fajlok): array
    {
        $errors = [];
        $fullName = self::egysor($data['full_name'] ?? '');
        if (mb_strlen($fullName) < 2) {
            $errors[] = 'Add meg a teljes neved.';
        }

        $ageRaw = trim((string) ($data['age'] ?? ''));
        $age = preg_match('/^\d{1,3}$/', $ageRaw) ? (int) $ageRaw : null;
        if ($age === null || $age <= 0 || $age > 120) {
            $errors[] = 'Add meg az életkorod (szám).';
            $age = null;
        }

        $email = trim((string) ($data['email'] ?? ''));
        if (! preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email) || mb_strlen($email) > 190) {
            $errors[] = 'Adj meg érvényes e-mail címet.';
        }

        $motivation = self::tobbsor($data['motivation'] ?? '');
        if (mb_strlen($motivation) < 5) {
            $errors[] = 'Írd le röviden, miért szeretnél önkéntes lenni.';
        }

        $napErtekek = array_column(Onkentes::napok(), 'value');
        $days = array_values(array_unique(array_filter(array_map('strval', (array) ($data['available_days'] ?? [])), fn ($d) => in_array($d, $napErtekek, true))));
        if ($days === []) {
            $errors[] = 'Válassz legalább egy napot, amikor tudsz jönni.';
        }

        $teruletek = Onkentes::teruletek();
        $areas = array_values(array_unique(array_filter(array_map('strval', (array) ($data['areas'] ?? [])), fn ($a) => in_array($a, $teruletek, true))));
        if ($areas === []) {
            $errors[] = 'Válassz legalább egy területet.';
        }

        $dietary = array_values(array_unique(array_filter(array_map('strval', (array) ($data['dietary'] ?? [])), fn ($d) => in_array($d, Onkentes::ETREND, true))));

        $healthNotes = self::tobbsor($data['health_notes'] ?? '');
        if ($healthNotes === '') {
            $errors[] = 'Írd be az egészségügyi tudnivalókat (ha nincs, írd: „nincs").';
        }

        $emergencyName = self::egysor($data['emergency_name'] ?? '');
        $emergencyPhone = self::egysor($data['emergency_phone'] ?? '');
        if ($emergencyName === '') {
            $errors[] = 'Add meg a vészhelyzeti kontakt nevét.';
        }
        if ($emergencyPhone === '') {
            $errors[] = 'Add meg a vészhelyzeti kontakt telefonszámát.';
        }

        if (! filter_var($data['privacy_consent'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $errors[] = 'Az adatkezelési hozzájárulás elfogadása kötelező.';
        }

        // A korhatár alatt: mindkét fájl kötelező (+ formátum/méret). Felette opcionális, de ha mégis feltölt, ellenőrizzük.
        $ellenorzott = [];
        $kiskoru = $age !== null && $age < Onkentes::korhatar();
        foreach (['szuloi' => 'szülői beleegyezés', 'motivacios' => 'motivációs levél'] as $kulcs => $felirat) {
            $f = $fajlok[$kulcs] ?? null;
            if ($f === null && ! $kiskoru) {
                continue;
            }
            if ($f === null || ! $f->isValid()) {
                $errors[] = "A(z) {$felirat} feltöltése kötelező.";

                continue;
            }
            // A típus a TARTALOMBÓL (finfo a fájl bájtjain) — nem a böngésző állításából, nem a kiterjesztésből.
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $f->getRealPath());
            $hiba = false;
            if (! isset(Onkentes::FAJL_TIPUSOK[$mime])) {
                $errors[] = "A(z) {$felirat} csak PDF, JPG vagy PNG lehet.";
                $hiba = true;
            }
            if ($f->getSize() > Onkentes::FAJL_MAX_BYTES) {
                $errors[] = "A(z) {$felirat} legfeljebb " . Onkentes::FAJL_MAX_MB . ' MB lehet.';
                $hiba = true;
            }
            if (! $hiba) {
                $ellenorzott[$kulcs] = ['file' => $f, 'mime' => $mime, 'ext' => Onkentes::FAJL_TIPUSOK[$mime]];
            }
        }

        $wasVolunteer = filter_var($data['was_volunteer'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'errors' => $errors,
            'full_name' => mb_substr($fullName, 0, 120),
            'age' => $age,
            'email' => $email,
            'was_volunteer' => $wasVolunteer,
            'prev_experience' => self::tobbsor($data['prev_experience'] ?? ''),
            'motivation' => $motivation,
            'days' => $days,
            'areas' => $areas,
            'dietary' => $dietary,
            'dietary_other' => mb_substr(self::egysor($data['dietary_other'] ?? ''), 0, 255),
            'has_license' => filter_var($data['has_license'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'health_notes' => $healthNotes,
            'emergency_name' => mb_substr($emergencyName, 0, 120),
            'emergency_phone' => mb_substr($emergencyPhone, 0, 40),
            'fajlok' => $ellenorzott,
        ];
    }

    /** 2026: clean() — egy sorba. */
    private static function egysor(mixed $s): string
    {
        if (! is_string($s)) {
            return '';
        }

        return trim((string) preg_replace('/[ \t]+/u', ' ', (string) preg_replace('/[\r\n]+/u', ' ', $s)));
    }

    /** 2026: clean(s, {multiline}) — a sortörés marad, legfeljebb egy üres sor. */
    private static function tobbsor(mixed $s): string
    {
        if (! is_string($s)) {
            return '';
        }
        $s = str_replace("\r\n", "\n", $s);
        $s = (string) preg_replace('/[ \t]+/u', ' ', $s);

        return trim((string) preg_replace('/\n{3,}/u', "\n\n", $s));
    }
}
