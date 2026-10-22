<?php

namespace App\Filament\Mediatar;

use App\Enums\MediaLicense;
use App\Enums\MediaOrigin;
use App\Models\Media;
use Awcodes\Curator\Resources\Media\Schemas\MediaForm;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * A médiatár szerkesztőlapja: a Curator mezői + a MI mezőink (jogállás, eredet, gyűjtemények, használat).
 * A jogállás szabálya a modellben él (Media::jogallasHiba) — itt csak a felület igazodik hozzá:
 * beküldött képnél kötelező és „Ismeretlen” nem választható; adminban feltöltöttnél nem áll az útba.
 * Az arculat fájljának cseréjét/átnevezését a modell utasítja el (a /tema-ban cserélhető).
 */
class MediatarForm extends MediaForm
{
    public static function configure(Schema $schema): Schema
    {
        $schema = parent::configure($schema);

        return $schema->components([...$schema->getComponents(), self::jogallas()]);
    }

    private static function jogallas(): Section
    {
        $bekuldott = fn (?Media $record) => $record?->origin === MediaOrigin::Bekuldes;

        return Section::make('Jogállás, eredet, gyűjtemények')
            ->columnSpanFull()->columns(['default' => 1, 'md' => 2])
            ->schema([
                Select::make('license')->label('Licenc')
                    ->options(MediaLicense::options())
                    ->default(MediaLicense::Sajat->value)
                    ->selectablePlaceholder(false)
                    ->required($bekuldott)
                    ->disableOptionWhen(fn (string $value, ?Media $record) => $bekuldott($record) && $value === MediaLicense::Ismeretlen->value)
                    ->helperText(fn (?Media $record) => $bekuldott($record)
                        ? 'Beküldött (külsős) kép: a licenc kötelező, „Ismeretlen” jogállással nem publikálható.'
                        : 'Adminban feltöltött kép: saját forrás az alapérték — csak akkor módosítsd, ha más a helyzet.'),
                TextInput::make('rights_holder')->label('Jogtulajdonos')->maxLength(255)
                    ->required($bekuldott),
                TextInput::make('photographer_credit')->label('Fotós (kredit)')->maxLength(255),
                Select::make('collections')->label('Gyűjtemények')
                    ->relationship('collections', 'name', fn ($query) => $query->orderBy('sort')->orderBy('name'))
                    ->multiple()->preload()
                    ->helperText('Egy kép több gyűjteményben is lehet. Ha egyikben sincs: „Rendezetlen”.'),
                Placeholder::make('eredet')->label('Eredet')
                    ->content(fn (?Media $record) => trim(($record?->origin?->label() ?? MediaOrigin::Admin->label())
                        . ($record?->legacy_note ? ' — ' . $record->legacy_note : ''))),
                Placeholder::make('hasznalat')->label('Hol használjuk')
                    ->hiddenOn('create')
                    ->content(function (?Media $record) {
                        $hol = $record?->usages() ?? [];

                        return $hol === [] ? 'Sehol — törölhető.'
                            : new HtmlString(e(count($hol) . ' helyen (amíg használjuk, nem törölhető):') . '<br>'
                                . implode('<br>', array_map('e', array_slice($hol, 0, 12)))
                                . (count($hol) > 12 ? '<br>… és még ' . (count($hol) - 12) : ''));
                    }),
            ]);
    }
}
