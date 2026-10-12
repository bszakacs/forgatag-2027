<?php

namespace App\Http\Controllers\Bekuldes;

use App\Enums\EntryType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProgramItem;
use App\Models\ProgramManager;
use App\Models\Venue;
use App\Services\ProgramLocked;
use App\Services\ProgramSubmission;
use App\Support\Submission;
use App\Support\SubmissionForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * A programbeküldő űrlap: kategóriaválasztó, új beküldés, a beküldő javítása (jóváhagyásig),
 * „Saját beküldéseim". Minden végpont a szerveren is ellenőrzi:
 *   - nyitva van-e a beküldés (Beállítások — nem elég, hogy az űrlap eltűnik),
 *   - jogosult-e a beküldő az adott kategóriára (2026-ban ez hiányzott),
 *   - a saját programjáról van-e szó, és az még nincs-e jóváhagyva.
 */
class UrlapController extends Controller
{
    public const NEM_JOGOSULT = 'Ebbe a kategóriába nem küldhetsz be programot. Ha szerinted ez hiba, keresd a szervezőt.';

    public const MENTES_HIBA = 'A beküldés mentése nem sikerült. Próbáld újra később.';

    private function bekuldo(): ProgramManager
    {
        return Auth::guard('bekuldo')->user();
    }

    private function jogosult(Category $category): bool
    {
        return $this->bekuldo()->categories()->whereKey($category->id)->exists();
    }

    public function kezdo(): View|RedirectResponse
    {
        $nyitva = Submission::isOpen();
        $kategoriak = $this->bekuldo()->categories()->orderBy('sort')->orderBy('name_hu')->get();

        // Egyetlen kategória → nincs mit választani, egyből az űrlap jön.
        if ($nyitva && $kategoriak->count() === 1 && ! session('hiba')) {
            return redirect()->route('bekuldes.urlap', $kategoriak->first());
        }

        return view('bekuldes.kezdo', compact('nyitva', 'kategoriak'));
    }

    public function urlap(Category $category): View|RedirectResponse
    {
        if (! Submission::isOpen()) {
            return redirect()->route('bekuldes.kezdo');
        }
        if (! $this->jogosult($category)) {
            return redirect()->route('bekuldes.kezdo')->with('hiba', self::NEM_JOGOSULT);
        }

        return $this->urlapNezet($category, null);
    }

    public function store(Request $request, Category $category): JsonResponse
    {
        if ($zarva = $this->zarvaValasz()) {
            return $zarva;
        }
        if (! $this->jogosult($category)) {
            return response()->json(['ok' => false, 'errors' => [self::NEM_JOGOSULT]], 403);
        }

        return $this->ment($request, new ProgramSubmission($this->bekuldo(), $category));
    }

    public function sajat(): View
    {
        $programok = $this->bekuldo()->programItems()
            ->with(['category', 'occurrences'])
            ->latest()
            ->get();

        return view('bekuldes.sajat', ['programok' => $programok, 'nyitva' => Submission::isOpen()]);
    }

    public function szerkesztes(int $program): View|RedirectResponse
    {
        $p = $this->sajatProgram($program);
        if (! Submission::isOpen() || ! $p->isSubmitterEditable()) {
            return redirect()->route('bekuldes.sajat');
        }
        if (! $p->category || ! $this->jogosult($p->category)) {
            return redirect()->route('bekuldes.kezdo')->with('hiba', self::NEM_JOGOSULT);
        }

        return $this->urlapNezet($p->category, $p);
    }

    public function update(Request $request, int $program): JsonResponse
    {
        if ($zarva = $this->zarvaValasz()) {
            return $zarva;
        }
        $p = $this->sajatProgram($program);
        if (! $p->isSubmitterEditable()) {
            return response()->json(['ok' => false, 'errors' => [ProgramLocked::UZENET]], 409);
        }
        if (! $p->category || ! $this->jogosult($p->category)) {
            return response()->json(['ok' => false, 'errors' => [self::NEM_JOGOSULT]], 403);
        }

        return $this->ment($request, new ProgramSubmission($this->bekuldo(), $p->category, $p));
    }

    /** A beküldött fotó — csak a saját programhoz (a fájl nem nyilvános). */
    public function foto(int $program): StreamedResponse
    {
        $p = $this->sajatProgram($program);
        abort_unless($p->photo_path && Storage::disk('local')->exists($p->photo_path), 404);

        return Storage::disk('local')->response($p->photo_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** Más programgazda programja: 404 (azt se áruljuk el, hogy létezik). */
    private function sajatProgram(int $id): ProgramItem
    {
        return $this->bekuldo()->programItems()->with('category')->findOrFail($id);
    }

    private function zarvaValasz(): ?JsonResponse
    {
        // Nem elég az űrlapot elrejteni: a végpontnak is nemet kell mondania.
        return Submission::isOpen()
            ? null
            : response()->json(['ok' => false, 'closed' => true, 'errors' => [Submission::closedMessage()]], 403);
    }

    private function ment(Request $request, ProgramSubmission $bekuldes): JsonResponse
    {
        // A sikertelenül érkezett fájl is UploadedFile (hibakóddal) — a validátor fotóhibaként kezeli.
        if (! $bekuldes->validate($request->except(['_token', 'photo']), $request->file('photo'))) {
            if ($bekuldes->photoFailed) {
                $bekuldes->reportPhotoFailure((string) $request->input('title'));
            }

            return response()->json(['ok' => false, 'errors' => $bekuldes->errors], 422);
        }

        try {
            $program = $bekuldes->save();
        } catch (ProgramLocked) {
            return response()->json(['ok' => false, 'errors' => [ProgramLocked::UZENET]], 409);
        } catch (Throwable $e) {
            Log::error('BEKÜLDÉS: a mentés nem sikerült. ' . $e->getMessage(), ['kivetel' => $e]);

            return response()->json(['ok' => false, 'errors' => [self::MENTES_HIBA]], 500);
        }

        if ($program === null) {
            // A fotó tárolása nem sikerült — a program nem jött létre / nem módosult. Látszik, és a koordinátor is tud róla.
            $bekuldes->reportPhotoFailure((string) $request->input('title'));

            return response()->json(['ok' => false, 'errors' => $bekuldes->errors], 503);
        }

        return response()->json(['ok' => true, 'id' => $program->id]);
    }

    private function urlapNezet(Category $category, ?ProgramItem $program): View
    {
        $napok = $program?->occurrences()->get() ?? collect();
        $elso = $napok->first();
        $ertekek = [
            'title' => $program?->title ?? '',
            'organizer_names' => $program?->organizer_names ?? '',
            'start_time' => $elso ? substr($elso->start_time, 0, 5) : null,
            'end_time' => $elso?->end_time ? substr($elso->end_time, 0, 5) : null,
            'schedule_note' => $program?->schedule_note ?? '',
            'days' => $napok->map(fn ($o) => $o->date->format('Y-m-d'))->all(),
            'venue' => $program?->venue_id ?? '',
            'description' => $program?->description ?? '',
            'entry_type' => $program?->entry_type?->value ?? EntryType::Free->value,
            'ticket_info' => $program?->ticket_info ?? '',
            'extra' => $program?->extra_fields ?? [],
            'photographer_credit' => $program?->photographer_credit ?? '',
            'photo_rights_declared' => (bool) $program?->photo_rights_declared,
        ];

        return view('bekuldes.urlap', [
            'kategoria' => $category,
            'program' => $program,
            'ertekek' => $ertekek,
            'config' => SubmissionForm::clientConfig($category),
            'helyszinek' => Venue::with('parent')->orderBy('name')->get(),
            'action' => $program ? route('bekuldes.modositas', $program) : route('bekuldes.bekuldes', $category),
            'fotoUrl' => $program?->photo_path ? route('bekuldes.foto', $program) : null,
        ]);
    }
}
