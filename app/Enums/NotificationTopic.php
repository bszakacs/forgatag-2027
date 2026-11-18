<?php

namespace App\Enums;

/**
 * Az értesítési témák. A témák listája a rendszer szerkezete (kód), a hozzájuk tartozó
 * koordinátori e-mail-címek viszont szervezői adat: a Beállítások „Értesítések” blokkjában.
 * Új funkció (pl. szabadulószoba) = új téma ide + a címei a Beállításokban.
 */
enum NotificationTopic: string
{
    case ProgramSubmission = 'program_submission';
    case Olympics = 'olympics';
    case Volunteer = 'volunteer';

    public function label(): string
    {
        return match ($this) {
            self::ProgramSubmission => 'Programbeküldés — új beküldött program',
            self::Olympics => 'Családi Olimpia — új jelentkezés',
            self::Volunteer => 'Önkéntesek — új jelentkezés',
        };
    }

    /**
     * Melyik Beállítások-blokkban ül a címmezője (null = az „Értesítések” blokkban). A cím TÁROLÁSA mindig ugyanott van
     * (`notifications.topics.<téma>` — egy forrás), csak a mező áll a saját funkciója blokkjában, hogy a kapcsoló és a
     * koordinátor címe egy helyen látszódjon (PROMPT-20).
     */
    public function blokk(): ?string
    {
        return match ($this) {
            self::Olympics => 'olympics',
            self::Volunteer => 'volunteer',
            default => null,
        };
    }
}
