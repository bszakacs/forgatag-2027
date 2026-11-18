<?php

namespace App\Support;

use App\Enums\NotificationTopic;
use App\Enums\OlympicsStatus;
use App\Filament\Resources\Olimpia\OlympicsTeamResource;
use App\Filament\Resources\Onkentesek\VolunteerApplicationResource;
use App\Mail\SzovegesLevel;
use App\Models\OlympicsTeam;
use App\Models\VolunteerApplication;

/**
 * A két publikus jelentkező űrlap levelei (PROMPT-20), egy helyen. A szövegek a 2026-os Directus-flow-k levelei
 * („Olimpia — visszaigazolás a jelentkezőnek”, „Új olimpia-jelentkezés értesítő”, „Új önkéntes-jelentkezés értesítő”),
 * sima szöveges levélként (a markdown-csillagok nélkül). A koordinátori cím a Beállításokból (Notifier — ha üres, a kimaradt
 * levél hibaszinten a naplóba kerül, és a Beállítások lapon piros jelzés áll).
 *
 * ⚠ Az önkéntes-levél személyes adatot visz — de az egészségügyi megjegyzést és a dokumentumokat NEM (2026-ban sem): azok
 * csak az adminban, jog-ellenőrzéssel nézhetők meg.
 */
class JelentkezesLevelek
{
    /** Visszaigazolás a csapatkapitánynak — azonnal (a programbeküldővel ellentétben). */
    public static function olimpiaVisszaigazolas(OlympicsTeam $t): bool
    {
        $nev = Festival::name();
        $varolista = $t->status === OlympicsStatus::Waitlist;
        $tagok = self::tagLista($t);

        $szoveg = implode("\n", [
            "Kedves {$t->team_name}!",
            '',
            "Köszönjük, hogy jelentkeztetek a Családi Olimpiára a {$nev}on.",
            '',
            $varolista
                ? 'A csapat jelenleg VÁRÓLISTÁN van: a helyek betöltek, de gyakran szabadul fel hely. Ha sorra kerültök, jelezzük.'
                : 'A jelentkezéseteket elfogadtuk — a csapat elindul a Családi Olimpián.',
            '',
            "Csapatnév: {$t->team_name}",
            "Csapatszín: {$t->color_name}",
            '',
            'A bejelentett csapattagok (' . count($t->tagok()) . '):',
            '',
            $tagok,
            '',
            'Mi a következő lépés?',
            $varolista
                ? 'Ha felszabadul hely, e-mailben vagy telefonon keresünk titeket.'
                : 'A pontos időbeosztásról és a találkozóhelyről e-mailben vagy telefonon keresünk titeket.',
            '',
            'Ha valamelyik adat nem stimmel (név, életkor, csapatszín), válaszolj erre a levélre.',
            '',
            'Jó készülődést!',
            "— {$nev} csapata",
        ]);

        $level = new SzovegesLevel("Megkaptuk a jelentkezéseteket – Családi Olimpia, {$nev}", $szoveg);
        foreach (Notifier::cimek(NotificationTopic::Olympics) as $cim) {
            $level->replyTo($cim, $nev); // a válasz a koordinátorhoz menjen, ne a noreply-ra
        }

        return Notifier::send($t->captain_email, $level);
    }

    public static function olimpiaKoordinatornak(OlympicsTeam $t): bool
    {
        $allapot = $t->status === OlympicsStatus::Waitlist ? 'VÁRÓLISTA' : 'elindul (jelentkezett)';
        $szoveg = implode("\n", [
            'Új jelentkezés érkezett a Családi Olimpiára:',
            '',
            "{$t->team_name} — csapatszín: {$t->color_name}",
            "Állapot: {$allapot}",
            '',
            "- Kapitány telefon: {$t->captain_phone}",
            "- Kapitány e-mail: {$t->captain_email}",
            '- Csapattagok (' . count($t->tagok()) . '):',
            '',
            self::tagLista($t),
            '',
            'A jelentkezés megnyitása az adminban: ' . OlympicsTeamResource::getUrl('view', ['record' => $t], panel: 'admin'),
        ]);

        $level = new SzovegesLevel("Új olimpia-jelentkezés – {$t->team_name} ({$t->color_name})", $szoveg);
        $level->replyTo($t->captain_email, $t->team_name);

        return Notifier::topic(NotificationTopic::Olympics, $level);
    }

    public static function onkentesKoordinatornak(VolunteerApplication $a): bool
    {
        $napok = implode(', ', array_map(fn ($d) => Onkentes::napFeliratDatumbol((string) $d), (array) $a->available_days));
        $etrend = array_merge((array) $a->dietary, filled($a->dietary_other) ? ['Egyéb: ' . $a->dietary_other] : []);
        $szoveg = implode("\n", [
            'Új önkéntes-jelentkezés érkezett:',
            '',
            "{$a->full_name} ({$a->age} éves)",
            "E-mail: {$a->email}",
            '',
            '- Napok: ' . ($napok ?: '—'),
            '- Területek: ' . (implode(', ', (array) $a->areas) ?: '—'),
            '- Jogosítvány: ' . ($a->has_license ? 'van' : 'nincs'),
            '- Étrend: ' . ($etrend !== [] ? implode(', ', $etrend) : 'nincs megjegyzés'),
            "- Vészkontakt: {$a->emergency_name} — {$a->emergency_phone}",
            '',
            'Motiváció:',
            $a->motivation,
            '',
            'A jelentkezés megnyitása az adminban (' . ($a->kiskoru() ? Onkentes::korhatar() . ' év alatt a feltöltött dokumentumok is itt láthatók' : 'az egészségügyi tudnivalók itt láthatók') . '): '
                . VolunteerApplicationResource::getUrl('view', ['record' => $a], panel: 'admin'),
        ]);

        $level = new SzovegesLevel("Új önkéntes-jelentkezés – {$a->full_name}", $szoveg);
        $level->replyTo($a->email, $a->full_name);

        return Notifier::topic(NotificationTopic::Volunteer, $level);
    }

    private static function tagLista(OlympicsTeam $t): string
    {
        $sorok = array_map(fn ($m) => '- ' . $m['name'] . (filled($m['age'] ?? null) ? " ({$m['age']} éves)" : ''), $t->tagok());

        return $sorok !== [] ? implode("\n", $sorok) : '—';
    }
}
