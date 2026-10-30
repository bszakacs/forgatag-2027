@use('App\Support\ProgramSzoveg')
@use('App\Enums\EntryType')
@use('App\Enums\ProgramStatus')
@use('App\Enums\TranslatableField')
{{--
  A program JELZÉSEI (2026: flagsOf) — a sor, a kártya és a felugró ablak ugyanezt mutatja: jegyinfó (vagy
  „adományalapú”), korhatár/korosztály, „Változott”/„Elmarad”. Az „Ingyenes” jelölés NINCS (2026: szinte minden program
  ingyenes, tehát nem információ). $teljes: a felugró ablakban a jegyinfó teljes, kattintható linkkel; a listában rövid.
--}}
@php
    $teljes ??= false;
    $jegy = $p->entry_type === EntryType::Ticketed ? trim(ProgramSzoveg::mezo($p, TranslatableField::TicketInfo)['szoveg']) : '';
    $kor = trim((string) ($p->extra_fields['age_limit'] ?? $p->extra_fields['age_group'] ?? ''));
@endphp
@if ($jegy !== '')
  <span class="pk-flag is-price">@if ($teljes){{ ProgramSzoveg::linkek($jegy, __('publikus.pk_jegyvasarlas')) }}@else{{ ProgramSzoveg::rovid($jegy, 60, __('publikus.pk_jegyvasarlas')) }}@endif</span>
@elseif ($p->entry_type === EntryType::Donation)
  <span class="pk-flag is-price">{{ __('publikus.pk_adomany') }}</span>
@endif
@if ($kor !== '')
  <span class="pk-flag">{{ \Illuminate\Support\Str::limit($kor, 24, '…') }}</span>
@endif
@if ($p->status === ProgramStatus::Changed)
  <span class="pk-flag is-lang">{{ __('publikus.pk_valtozott') }}</span>
@elseif ($p->status === ProgramStatus::Cancelled)
  <span class="pk-flag is-lang">{{ __('publikus.pk_elmarad') }}</span>
@endif
