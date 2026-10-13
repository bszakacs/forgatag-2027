<?php

namespace App\Enums;

/** A jóváhagyási napló eseményei (program_review_events.action). */
enum ReviewAction: string
{
    case Submitted = 'submitted';
    case Resubmitted = 'resubmitted';
    case SentToReview = 'sent_to_review';
    case Approved = 'approved';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Beküldve az űrlapon',
            self::Resubmitted => 'A beküldő javította',
            self::SentToReview => 'Jóváhagyásra küldve',
            self::Approved => 'Jóváhagyva, közzétéve',
            self::Returned => 'Visszaküldve javításra',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Returned => 'danger',
            self::SentToReview => 'warning',
            default => 'gray',
        };
    }
}
