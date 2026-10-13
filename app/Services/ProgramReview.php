<?php

namespace App\Services;

use App\Enums\ProgramSource;
use App\Enums\ProgramStatus;
use App\Enums\ReviewAction;
use App\Models\ProgramItem;
use App\Models\ProgramManager;
use App\Models\ProgramReviewEvent;
use App\Models\User;
use App\Support\BekuldesLevelek;
use Illuminate\Support\Facades\DB;

/**
 * A jóváhagyási sor lépései, EGY helyen (az admin gombjai csak ezt hívják):
 *
 *   draft ──(Jóváhagyásra küld)──▶ pending_review ──(Jóváhagy)──▶ published
 *     ▲                                  │
 *     └──────(Visszaküld javításra)──────┘   (draftból is: a beküldő levelet kap az indokkal)
 *
 * Minden lépés a jóváhagyási naplóba kerül (append-only). A jóváhagyási levelet a ProgramItem
 * „updated” eseménye küldi (bárhonnan történik a közzététel) — az eredményét itt adjuk vissza.
 */
class ProgramReview
{
    public static function canSendToReview(ProgramItem $p): bool
    {
        return $p->status === ProgramStatus::Draft;
    }

    public static function canApprove(ProgramItem $p): bool
    {
        return $p->status === ProgramStatus::PendingReview;
    }

    /** Visszaküldeni csak beküldött programot lehet: a belső felvitelnek nincs kinek visszamennie. */
    public static function canReturn(ProgramItem $p): bool
    {
        return $p->status->isSubmitterEditable()
            && $p->source === ProgramSource::PublicForm
            && filled($p->contact_email);
    }

    /**
     * Ami miatt a program MÉG NEM hagyható jóvá. Üres lista = jóváhagyható.
     *
     * @return list<string>
     */
    public static function approvalBlockers(ProgramItem $p): array
    {
        $hibak = [];
        if (blank($p->photo_path)) {
            $hibak[] = 'Nincs fotó. Fotó nélkül nem hagyható jóvá — küldd vissza javításra.';
        } elseif (! $p->hasPhotoFile()) {
            $hibak[] = 'A fotó fájlja hiányzik a tárhelyről (a hivatkozás megvan, a fájl nincs). Küldd vissza javításra, hogy a beküldő újra feltöltse.';
        }
        if (! $p->photo_rights_declared) {
            $hibak[] = 'A beküldő nem nyilatkozott a fotó felhasználási jogáról.';
        }
        if ($p->category_id === null) {
            $hibak[] = 'Nincs kategóriája.';
        }
        if ($p->venue_id === null) {
            $hibak[] = 'Nincs helyszíne.';
        }
        if (! $p->occurrences()->exists()) {
            $hibak[] = 'Nincs időpontja.';
        }

        return $hibak;
    }

    public static function sendToReview(ProgramItem $program, User $user): void
    {
        self::step($program, $user, ReviewAction::SentToReview, ProgramStatus::PendingReview,
            fn (ProgramItem $p) => self::canSendToReview($p) ? null : 'Csak vázlat küldhető jóváhagyásra.');
    }

    /**
     * @return bool|null a jóváhagyási levél: true = kiment, false = NEM ment ki, null = nem kellett (belső program)
     */
    public static function approve(ProgramItem $program, User $user): ?bool
    {
        $kesz = self::step($program, $user, ReviewAction::Approved, ProgramStatus::Published, function (ProgramItem $p) {
            if (! self::canApprove($p)) {
                return 'Csak „Jóváhagyásra vár” állapotú program hagyható jóvá.';
            }
            $hibak = self::approvalBlockers($p);

            return $hibak === [] ? null : implode(' ', $hibak);
        });

        return $kesz->approvalMailSent;
    }

    /** @return bool kiment-e a levél a beküldőnek */
    public static function returnForFix(ProgramItem $program, User $user, string $indok): bool
    {
        $indok = trim($indok);
        if ($indok === '') {
            throw new ReviewStepRefused('A visszaküldéshez indok kell — a beküldő ebből tudja, mit javítson.');
        }
        $kesz = self::step($program, $user, ReviewAction::Returned, ProgramStatus::Draft,
            fn (ProgramItem $p) => self::canReturn($p) ? null : 'Ez a program nem küldhető vissza (már jóváhagyták, vagy nincs beküldője).',
            $indok);

        return BekuldesLevelek::visszakuldve($kesz, $indok);
    }

    /** Naplósor. A beküldés/javítás (ProgramSubmission) is ezt hívja. */
    public static function log(ProgramItem $p, ReviewAction $action, ?ProgramStatus $from = null, ?User $user = null,
        ?ProgramManager $manager = null, ?string $note = null): ProgramReviewEvent
    {
        return ProgramReviewEvent::create([
            'program_item_id' => $p->id,
            'action' => $action,
            'from_status' => $from ?? $p->status,
            'to_status' => $p->status,
            'user_id' => $user?->id,
            'program_manager_id' => $manager?->id,
            'actor_name' => $user?->name ?? $manager?->name,
            'note' => $note,
        ]);
    }

    /**
     * Egy lépés zárolt sorral: a beküldő javítása és a jóváhagyás nem futhat át egymáson.
     *
     * @param  callable(ProgramItem): ?string  $refuse  ha nem null: a lépés elutasításának oka
     */
    private static function step(ProgramItem $program, User $user, ReviewAction $action, ProgramStatus $to,
        callable $refuse, ?string $note = null): ProgramItem
    {
        return DB::transaction(function () use ($program, $user, $action, $to, $refuse, $note) {
            $p = ProgramItem::whereKey($program->id)->lockForUpdate()->firstOrFail();
            if (($ok = $refuse($p)) !== null) {
                throw new ReviewStepRefused($ok);
            }
            $from = $p->status;
            $p->status = $to;
            $p->save(); // a jóváhagyási levelet a modell updated-eseménye küldi
            self::log($p, $action, $from, $user, note: $note);

            return $p;
        });
    }
}
