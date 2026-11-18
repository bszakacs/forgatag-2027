<?php

namespace App\Services\Forditas;

use App\Enums\TranslatableField;
use App\Enums\TranslationStatus;
use App\Models\ProgramItem;
use App\Models\ProgramItemTranslation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A KÉZI fordítás mentése (a program lapján: „Román fordítás” gomb) — EGY író-út, a szerver ellenőrzi, nem csak a felület.
 *
 * Szabályok: kézzel írt vagy átírt szöveg NEM lehet „gépi” (különben a gép felülírhatná — az emberi munka sérthetetlen);
 * az `outdated` csak akkor maradhat, ha a szöveghez nem nyúltak (döntés: átnézve/jóváhagyva = feloldva); emberi
 * státusznál a lenyomat a MOSTANI magyar szövegé, és a gépi ellenőrzés jelzése törlődik (ember nézte át, ugyanezen a
 * képernyőn látta). Üresre törölt szöveg = a sor törlődik (a következő futás újra gépi fordítást ad).
 */
final class KeziForditas
{
    /** @return array<string, string> a felületnek: státusz => felirat (az `outdated`-et a rendszer adja, nem választható új értéknek) */
    public static function valaszthatoStatuszok(?TranslationStatus $jelenlegi): array
    {
        $o = [
            TranslationStatus::Approved->value => TranslationStatus::Approved->label(),
            TranslationStatus::Reviewed->value => TranslationStatus::Reviewed->label(),
            TranslationStatus::MachineTranslated->value => TranslationStatus::MachineTranslated->label() . ' (nincs átnézve)',
        ];
        if ($jelenlegi === TranslationStatus::Outdated) {
            $o[TranslationStatus::Outdated->value] = 'Elavult — még nem néztem át';
        }

        return $o;
    }

    /**
     * @param  array<string, array{ro?: ?string, status?: ?string}>  $adat  mező => új román szöveg + státusz
     * @return int ennyi mező változott
     */
    public static function ment(ProgramItem $program, User $ki, array $adat): int
    {
        abort_unless($ki->role->canTranslate(), 403);

        return DB::transaction(function () use ($program, $adat) {
            $valtozott = 0;
            $hibak = [];
            foreach (TranslatableField::cases() as $mezo) {
                if (! array_key_exists($mezo->value, $adat)) {
                    continue;
                }
                $ro = trim((string) ($adat[$mezo->value]['ro'] ?? ''));
                $statusz = TranslationStatus::tryFrom((string) ($adat[$mezo->value]['status'] ?? ''));
                $sor = $program->translations()->where('locale', 'ro')->where('field', $mezo->value)->lockForUpdate()->first();
                $regi = $sor ? trim((string) $sor->translated_text) : '';

                if ($ro === '') {
                    if ($sor) {
                        $sor->delete();
                        $valtozott++;
                    }

                    continue;
                }
                $kulcs = "forditas.{$mezo->value}.status";
                if ($statusz === null) {
                    $hibak[$kulcs] = 'Válaszd ki az állapotát.';

                    continue;
                }
                $atirta = $ro !== $regi;
                if ($atirta && $statusz === TranslationStatus::MachineTranslated) {
                    $hibak[$kulcs] = 'Kézzel írt szöveg nem lehet „gépi” — állítsd „Átnézve” vagy „Jóváhagyva” állapotra.';

                    continue;
                }
                if ($statusz === TranslationStatus::Outdated && ($atirta || $sor?->status !== TranslationStatus::Outdated)) {
                    $hibak[$kulcs] = 'Az „Elavult” állapotot a rendszer adja. Ha átnézted, állítsd „Átnézve” vagy „Jóváhagyva” állapotra.';

                    continue;
                }
                if (! $atirta && $sor?->status === $statusz) {
                    continue;                                   // nem változott semmi
                }

                $ember = in_array($statusz, [TranslationStatus::Reviewed, TranslationStatus::Approved], true);
                $uj = ['translated_text' => $ro, 'status' => $statusz];
                if ($ember) {
                    $uj['source_text_hash'] = ProgramItemTranslation::hashSource($program->{$mezo->value});
                    $uj['check_note'] = null;
                }
                $sor ? $sor->update($uj) : $program->translations()->create(['locale' => 'ro', 'field' => $mezo] + $uj);
                $valtozott++;
            }
            if ($hibak !== []) {
                throw ValidationException::withMessages($hibak);
            }

            return $valtozott;
        });
    }
}
