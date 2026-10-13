<?php

namespace App\Services;

use App\Enums\EntryType;
use App\Enums\ProgramSource;
use App\Enums\ProgramStatus;
use App\Enums\ReviewAction;
use App\Models\Category;
use App\Models\ProgramItem;
use App\Models\ProgramManager;
use App\Models\Venue;
use App\Support\BekuldesLevelek;
use App\Support\Festival;
use App\Support\SubmissionForm;
use App\Support\TextCleaner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A programbeküldés szerver-oldali magja: validáció (config-vezérelt, a 2026-os submit-api
 * validate() függvényének átvétele, változatlan hibaüzenetekkel), mentés és az időpont-sorok
 * összehangolása. Új beküldés és a beküldő javítása ugyanezen az úton megy.
 *
 * Fordítás: a fordítandó mezők köre az App\Enums\TranslatableField (cím, leírás, jegyinformáció) —
 * ebben a fázisban fordítást nem hívunk.
 */
class ProgramSubmission
{
    /** A fotók helye a NEM nyilvános tárban. */
    public const PHOTO_DIR = 'bekuldesek';

    /** @var list<string> */
    public array $errors = [];

    /** Igaz, ha a hiba a fotó FELTÖLTÉSÉN / mentésén múlt (nem a beküldő adatán) — erről a koordinátor is jelzést kap. */
    public bool $photoFailed = false;

    /** A fotóhiba oka a koordinátornak (műszaki szöveg, a beküldő nem látja). */
    public string $photoFailReason = '';

    /** @var array<string, mixed> a megtisztított, mentésre kész adatok */
    private array $clean = [];

    private ?UploadedFile $photo = null;

    private ?string $photoMime = null;

    public function __construct(
        private ProgramManager $manager,
        private Category $category,
        private ?ProgramItem $existing = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  a beküldött mezők
     */
    public function validate(array $data, ?UploadedFile $photo): bool
    {
        $errors = [];

        $title = TextCleaner::clean($data['title'] ?? null);
        if (mb_strlen($title) < 5) {
            $errors[] = 'A cím legalább 5 karakter legyen.';
        }
        $venueId = (string) ($data['venue'] ?? '');
        if ($venueId === '' || ! Venue::whereKey($venueId)->exists()) {
            $errors[] = 'Válassz érvényes helyszínt.';
        }

        // Szervező(k) — programonként, kötelező. Több szervező vesszővel elválasztva (a publikus „Szervező").
        $organizerNames = TextCleaner::clean($data['organizer_names'] ?? null);
        if (mb_strlen($organizerNames) < 2) {
            $errors[] = 'Add meg a szervező(k) nevét.';
        }

        $desc = TextCleaner::clean($data['description'] ?? null, multiline: true);
        if (mb_strlen($desc) < SubmissionForm::DESCRIPTION_MIN) {
            $errors[] = 'A leírás legalább ' . SubmissionForm::DESCRIPTION_MIN . ' karakter legyen.';
        }
        if (mb_strlen($desc) > SubmissionForm::DESCRIPTION_MAX) {
            $errors[] = 'A leírás legfeljebb ' . SubmissionForm::DESCRIPTION_MAX . ' karakter lehet.';
        }

        $entryType = EntryType::tryFrom((string) ($data['entry_type'] ?? ''));
        if ($entryType === null) {
            $errors[] = 'Válassz belépés-típust.';
        }
        $ticketInfo = TextCleaner::clean($data['ticket_info'] ?? null);
        if ($entryType === EntryType::Ticketed && $ticketInfo === '') {
            $errors[] = 'Jegyes programnál add meg a jegyinformációt.';
        }

        // Időpont: napi EGY kezdés/vége + mely fesztiválnapokon fut.
        $startTime = trim((string) ($data['start_time'] ?? ''));
        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $startTime)) {
            $errors[] = 'Add meg a kezdés időpontját.';
            $startTime = '';
        }
        $endTime = trim((string) ($data['end_time'] ?? ''));
        if ($endTime !== '' && ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $endTime)) {
            $endTime = '';
        }
        // ÉJFÉL UTÁNI BEFEJEZÉS: a fesztiválnap hajnali dayCutHour óráig tart, ezért a kezdésnél
        // KORÁBBI vég a KÖVETKEZŐ hajnalt jelenti (23:00 → 00:30), és a program ugyanazon a napon marad.
        if ($endTime !== '' && $startTime !== '') {
            if ($endTime === $startTime) {
                $errors[] = 'A vége ne ugyanaz legyen, mint a kezdés.';
            } elseif ($endTime < $startTime && $endTime > SubmissionForm::dayCutTime()) {
                $errors[] = SubmissionForm::dayCutMessage();
            }
        }
        $validDays = Festival::dayIds();
        $days = array_values(array_unique(array_filter(
            array_map('strval', (array) ($data['recurrence_days'] ?? [])),
            fn (string $d) => in_array($d, $validDays, true),
        )));
        sort($days);
        if ($days === []) {
            $errors[] = 'Válassz legalább egy fesztiválnapot.';
        }

        // Kötelező fotó + felbontás + formátum + méret. A javításnál a meglévő fotó maradhat.
        $errors = array_merge($errors, $this->validatePhoto($photo));
        $credit = TextCleaner::clean($data['photographer_credit'] ?? null);
        if ($credit === '') {
            $errors[] = 'Add meg a fotós nevét.';
        }
        if (! filter_var($data['photo_rights_declared'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $errors[] = 'A fotó jogi nyilatkozatának elfogadása kötelező.';
        }

        // Kategóriaspecifikus extra mezők
        $extras = is_array($data['extra'] ?? null) ? $data['extra'] : [];
        $isOn = fn (string $key) => filter_var($extras[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        $extraOut = [];
        foreach (SubmissionForm::extraFieldsFor($this->category->slug) as $f) {
            $raw = $extras[$f['key']] ?? null;
            if ($f['type'] === 'checkbox') {
                $v = $isOn($f['key']);
                $filled = $v;
            } elseif ($f['type'] === 'number') {
                $v = ($raw === '' || $raw === null || ! is_numeric($raw)) ? null : $raw + 0;
                $filled = $v !== null;
            } else {
                $v = TextCleaner::clean((string) $raw, multiline: $f['type'] === 'longtext');
                $filled = $v !== '';
            }
            $need = ($f['required'] ?? false) || (isset($f['requiredIf']) && $isOn($f['requiredIf']));
            if ($need && ! $filled) {
                $errors[] = "Hiányzó kötelező mező: {$f['label']}.";
            }
            if ($filled) {
                $extraOut[$f['key']] = $v;
            }
        }

        $this->errors = $errors;
        $this->clean = [
            'title' => $title,
            'description' => $desc,
            'organizer_names' => $organizerNames,
            'venue_id' => $venueId,
            'entry_type' => $entryType?->value,
            'ticket_info' => $ticketInfo !== '' ? $ticketInfo : null,
            'start_time' => $startTime,
            'end_time' => $endTime !== '' ? $endTime : null,
            'schedule_note' => TextCleaner::clean($data['schedule_note'] ?? null) ?: null,
            'days' => $days,
            'photographer_credit' => $credit,
            'extra_fields' => $extraOut !== [] ? $extraOut : null,
        ];

        return $errors === [];
    }

    /** @return list<string> */
    private function validatePhoto(?UploadedFile $photo): array
    {
        if ($photo === null) {
            return $this->existing?->photo_path ? [] : ['A fotó feltöltése kötelező.'];
        }
        // A fájl elindult, de nem érkezett meg épen (pl. a tárhely feltöltési korlátja) — ez NEM a beküldő hibája.
        if (! $photo->isValid()) {
            $this->photoFailed = true;
            $this->photoFailReason = 'A szerver nem fogadta a fájlt: ' . $photo->getErrorMessage();

            return ['A fotó feltöltése nem sikerült (a szerver nem fogadta a fájlt). Próbáld újra; ha most sem megy, keresd a szervezőt.'];
        }

        $errors = [];
        if ($photo->getSize() > SubmissionForm::PHOTO_MAX_BYTES) {
            $errors[] = 'A fotó legfeljebb ' . SubmissionForm::photoMaxMb() . ' MB lehet.';
        }
        // A formátumot és a méretet a FÁJL TARTALMÁBÓL olvassuk, nem a böngésző állításából.
        $info = @getimagesize($photo->getRealPath());
        if ($info === false) {
            $errors[] = 'A fotó nem olvasható (érvénytelen kép).';

            return $errors;
        }
        if (! in_array($info['mime'], SubmissionForm::PHOTO_FORMATS, true)) {
            $errors[] = 'A fotó csak JPG, PNG vagy WebP lehet.';

            return $errors;
        }
        [$w, $h] = $info;
        if ($w < SubmissionForm::PHOTO_MIN_WIDTH || $h < SubmissionForm::PHOTO_MIN_HEIGHT) {
            $errors[] = 'A fotó legalább ' . SubmissionForm::PHOTO_MIN_WIDTH . '×' . SubmissionForm::PHOTO_MIN_HEIGHT . " pixel legyen (most {$w}×{$h}).";
        }
        $this->photo = $photo;
        $this->photoMime = $info['mime'];

        return $errors;
    }

    /**
     * Mentés (a validate() után). A fotó ELŐSZÖR kerül a tárba: ha ez nem sikerül, a program NEM jön
     * létre / NEM módosul — 2026-ban a program fotó nélkül létrejött, és a beküldő sikert látott.
     */
    public function save(): ?ProgramItem
    {
        $newPath = null;
        if ($this->photo !== null) {
            $newPath = $this->storePhoto();
            if ($newPath === null) {
                return null;
            }
        }

        try {
            $program = DB::transaction(function () use ($newPath) {
                $program = $this->existing
                    ? ProgramItem::whereKey($this->existing->id)->lockForUpdate()->firstOrFail()
                    : new ProgramItem;

                // A jóváhagyás közben is megtörténhetett — zárolt programhoz a beküldő nem nyúlhat.
                if ($program->exists && ! $program->isSubmitterEditable()) {
                    throw new ProgramLocked;
                }

                $c = $this->clean;
                $program->fill([
                    'title' => $c['title'],
                    'description' => $c['description'],
                    'organizer_names' => $c['organizer_names'],
                    'venue_id' => $c['venue_id'],
                    'entry_type' => $c['entry_type'],
                    'ticket_info' => $c['ticket_info'],
                    'schedule_note' => $c['schedule_note'],
                    'photographer_credit' => $c['photographer_credit'],
                    'photo_rights_declared' => true,
                    'extra_fields' => $c['extra_fields'],
                    // Ki küldte be (belépett programgazda) — csak nyilvántartás, NEM ebből jön a Szervező.
                    'contact_name' => $this->manager->name,
                    'contact_email' => $this->manager->email,
                ]);
                if (! $program->exists) {
                    $program->category_id = $this->category->id;
                    $program->program_manager_id = $this->manager->id;
                    $program->source = ProgramSource::PublicForm;
                    $program->status = ProgramStatus::Draft;
                }
                if ($newPath !== null) {
                    $program->photo_path = $newPath;
                }
                $uj = ! $program->exists;
                $program->save();

                ProgramReview::log($program, $uj ? ReviewAction::Submitted : ReviewAction::Resubmitted, manager: $this->manager);

                $this->syncOccurrences($program, $c['days'], $c['start_time'], $c['end_time'], $c['schedule_note']);

                return $program;
            });
        } catch (Throwable $e) {
            if ($newPath !== null) {
                Storage::disk('local')->delete($newPath);
            }
            throw $e;
        }

        // Innentől a program BENT VAN: ami ezután hibázik, az nem fordulhat át „sikertelen beküldésbe".
        // A lecserélt régi fotó csak a sikeres mentés UTÁN törlődik.
        $old = $this->existing?->photo_path;
        if ($newPath !== null && $old && $old !== $newPath) {
            try {
                Storage::disk('local')->delete($old);
            } catch (Throwable) {
                // egy ottmaradt régi fájl nem hiba a beküldőnek
            }
        }

        BekuldesLevelek::ujBekuldes($program->load('category'), javitas: $this->existing !== null);

        return $program;
    }

    private function storePhoto(): ?string
    {
        $ext = match ($this->photoMime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        try {
            $path = Storage::disk('local')->putFileAs(self::PHOTO_DIR, $this->photo, Str::uuid() . '.' . $ext);
        } catch (Throwable $e) {
            $path = false;
            $this->photoFailReason = 'A fájl tárolása nem sikerült: ' . $e->getMessage();
        }
        if ($path === false || ! Storage::disk('local')->exists($path)) {
            $this->photoFailed = true;
            $this->photoFailReason = $this->photoFailReason ?: 'A fájl tárolása nem sikerült.';
            Log::error('BEKÜLDÉS: a fotó tárolása nem sikerült. ' . $this->photoFailReason, ['beku' => $this->manager->email]);
            $this->errors = [$this->existing
                ? 'A fotó mentése nem sikerült, ezért a módosítás NEM mentődött el. Próbáld újra pár perc múlva; ha most sem megy, keresd a szervezőt.'
                : 'A fotó mentése nem sikerült, ezért a program NEM jött létre. Próbáld újra pár perc múlva; ha most sem megy, keresd a szervezőt.'];

            return null;
        }

        return $path;
    }

    /** Jelzés a koordinátornak, ha a fotón múlt a beküldés (a hívó dönti el, mikor). */
    public function reportPhotoFailure(string $title): void
    {
        Log::error('BEKÜLDÉS: fotóhiba. ' . $this->photoFailReason, ['beku' => $this->manager->email, 'cim' => $title]);
        BekuldesLevelek::fotoHiba($this->manager, $this->category->name_hu, $title, $this->photoFailReason);
    }

    /**
     * Napi időpont-sorok összehangolása: a megmaradó napok HELYBEN frissülnek (az azonosítójuk marad),
     * a kivett napok törlődnek, az újak beszúródnak. (2026-ban ezt egy Directus-flow végezte.)
     *
     * @param  list<string>  $days
     */
    public function syncOccurrences(ProgramItem $program, array $days, string $start, ?string $end, ?string $note): void
    {
        $meglevo = $program->occurrences()->get()->keyBy(fn ($o) => $o->date->format('Y-m-d'));

        $program->occurrences()->whereNotIn('date', $days)->delete();

        foreach ($days as $day) {
            $adat = ['start_time' => $start, 'end_time' => $end, 'note' => $note];
            if ($meglevo->has($day)) {
                $meglevo[$day]->update($adat);
            } else {
                $program->occurrences()->create(['date' => $day] + $adat);
            }
        }
    }
}
