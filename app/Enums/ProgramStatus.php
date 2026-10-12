<?php

namespace App\Enums;

/**
 * A program életciklusa. A publikus oldalon látszik: published, changed („Változott”),
 * cancelled („Elmarad”) — ez a lista EGY helyen van: publicValues().
 */
enum ProgramStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Changed = 'changed';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Vázlat',
            self::PendingReview => 'Jóváhagyásra vár',
            self::Published => 'Közzétéve',
            self::Changed => 'Változott',
            self::Cancelled => 'Elmarad',
            self::Archived => 'Archivált',
        };
    }

    /** @return list<string> a publikus oldalon megjelenő státuszok */
    public static function publicValues(): array
    {
        return [self::Published->value, self::Changed->value, self::Cancelled->value];
    }

    /** @return list<string> amelyekre a gépi fordítás fut (már a beküldéskor is) */
    public static function translatableValues(): array
    {
        return [self::Draft->value, self::PendingReview->value, self::Published->value, self::Changed->value];
    }
}
