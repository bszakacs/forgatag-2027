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

    public function label(): string
    {
        return match ($this) {
            self::ProgramSubmission => 'Programbeküldés — új beküldött program',
        };
    }
}
