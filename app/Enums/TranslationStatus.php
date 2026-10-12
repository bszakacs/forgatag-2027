<?php

namespace App\Enums;

/**
 * A fordítás állapota. Az emberi fordítás sérthetetlen: a gépi futás csak a
 * machine_translated sort írhatja felül (isMachineWritable).
 */
enum TranslationStatus: string
{
    case MachineTranslated = 'machine_translated';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Outdated = 'outdated';

    public function label(): string
    {
        return match ($this) {
            self::MachineTranslated => 'Gépi fordítás',
            self::Reviewed => 'Átnézve',
            self::Approved => 'Jóváhagyva',
            self::Outdated => 'ELAVULT — a magyar szöveg változott',
        };
    }

    /** Megjelenítési elsőbbség; 0 = nem jeleníthető meg (ott a magyar szöveg látszik). */
    public function publicRank(): int
    {
        return match ($this) {
            self::Approved => 3,
            self::Reviewed => 2,
            self::MachineTranslated => 1,
            self::Outdated => 0,
        };
    }

    public function isMachineWritable(): bool
    {
        return $this === self::MachineTranslated;
    }
}
