<?php

namespace App\Support;

use App\Enums\EntryType;
use App\Enums\ProgramStatus;
use App\Enums\TranslatableField;
use App\Models\ProgramItem;
use Illuminate\Support\HtmlString;

/**
 * A program JELZÉSEI — a 2026-os `flagsOf()` (programkereso/script.js) betűre: jegyinfó (vagy „adományalapú”),
 * korhatár/korosztály, „Változott” / „Elmarad”. Az „Ingyenes” jelölés NINCS (2026: szinte minden program ingyenes).
 * `$teljes`: a felugró ablakban a jegyinfó a TELJES szöveg, a címei „Jegyvásárlás” felirattal kattinthatók (2026:
 * fgLink.into); a listában rövid (teaser 60, a linkek helyén a felirat).
 *
 * Miért PHP és nem Blade-sablon: kártyánként fut (egy napon ~100-szor) — külön sablonként ez egymaga a lap
 * kirajzolásának negyede volt (mérve 2026-10-30: 73 kártyánál ~195 ms).
 */
final class ProgramJelzesek
{
    public static function html(ProgramItem $p, bool $teljes = false): HtmlString
    {
        $ki = '';
        $jegy = $p->entry_type === EntryType::Ticketed ? trim(ProgramSzoveg::mezo($p, TranslatableField::TicketInfo)['szoveg']) : '';
        $felirat = __('publikus.pk_jegyvasarlas');
        if ($jegy !== '') {
            $ki .= '<span class="pk-flag is-price">' . ($teljes ? ProgramSzoveg::linkek($jegy, $felirat) : e(ProgramSzoveg::rovid($jegy, 60, $felirat))) . '</span>';
        } elseif ($p->entry_type === EntryType::Donation) {
            $ki .= '<span class="pk-flag is-price">' . e(__('publikus.pk_adomany')) . '</span>';
        }
        $kor = trim((string) ($p->extra_fields['age_limit'] ?? $p->extra_fields['age_group'] ?? ''));
        if ($kor !== '') {
            $ki .= '<span class="pk-flag">' . e(ProgramSzoveg::rovid($kor, 24)) . '</span>';
        }
        if ($p->status === ProgramStatus::Changed) {
            $ki .= '<span class="pk-flag is-lang">' . e(__('publikus.pk_valtozott')) . '</span>';
        }
        if ($p->status === ProgramStatus::Cancelled) {
            $ki .= '<span class="pk-flag is-lang">' . e(__('publikus.pk_elmarad')) . '</span>';
        }

        return new HtmlString($ki);
    }
}
