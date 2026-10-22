<?php

declare(strict_types=1);

/*
 * A médiatár (Curator) beállításai — CLAUDE.md 2/d.
 *
 * · A tár a NYILVÁNOS mappában van (public/mediatar, „mediatar” lemez): így nem kell szimbolikus link,
 *   amit a cPanel Fájlkezelő nem tud létrehozni. A fizikai tárolón NINCS mappa-rendezés — a „mappa”
 *   érzetét a gyűjtemények adják (App\Models\MediaCollection), szűrőként.
 * · A még jóvá nem hagyott beküldött fotó NEM ide kerül, hanem a zárt tárba (storage/app/private);
 *   jóváhagyáskor kerül át (App\Services\ProgramPhoto).
 * · A saját mezőink (jogtulajdonos, licenc, fotós, eredet, gyűjtemény) a mi modellünkön: App\Models\Media.
 */
return [
    'curation_formats' => Awcodes\Curator\Enums\CurationFormats::toArray(),
    'curation_max_dimension' => 8192,
    'default_disk' => 'mediatar',
    'default_directory' => null,
    'default_visibility' => 'public',
    'features' => [
        'curations' => true,
        'file_swap' => true,
        'directory_restriction' => false,
        'preserve_file_names' => false,
        'tenancy' => [
            'enabled' => false,
            'relationship_name' => null,
        ],
    ],
    // A Glide (méretváltozatok) aláíró kulcsa. MINDEN környezetben kell (a Curator dokumentációja kiemeli).
    // Ha a .env-ben nincs külön CURATOR_GLIDE_TOKEN, az alkalmazás kulcsából (APP_KEY) származtatjuk — így
    // egy friss környezet sem indulhat token nélkül, és titok nem kerül a tárolóba. (Az APP_KEY cseréje csak
    // annyit jelent, hogy a méretváltozatok címe megváltozik — a képek maguk nem.)
    'glide_token' => env('CURATOR_GLIDE_TOKEN')
        ?: (env('APP_KEY') ? hash_hmac('sha256', 'curator-glide', (string) env('APP_KEY')) : null),
    'model' => App\Models\Media::class,
    'path_generator' => null,
    'resource' => [
        'label' => 'kép / fájl',
        'plural_label' => 'Médiatár',
        'default_layout' => 'grid',
        'navigation' => [
            'group' => null,
            'icon' => 'heroicon-o-photo',
            'sort' => 3,
            'should_register' => true,
            'should_show_badge' => false,
        ],
        'resource' => App\Filament\Mediatar\MediatarResource::class,
        'pages' => [
            'create' => App\Filament\Mediatar\Pages\CreateMedia::class,
            'edit' => App\Filament\Mediatar\Pages\EditMedia::class,
            'index' => App\Filament\Mediatar\Pages\ListMedia::class,
        ],
        'schemas' => [
            'form' => App\Filament\Mediatar\MediatarForm::class,
        ],
        'tables' => [
            'table' => App\Filament\Mediatar\MediatarTable::class,
        ],
    ],
    'temporary_url_expiration' => 5,
    'url_provider' => Awcodes\Curator\Providers\GlideUrlProvider::class,
];
