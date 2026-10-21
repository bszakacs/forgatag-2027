<?php

namespace Tests;

use App\Support\Settings;
use App\Support\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Téma nélkül egyetlen publikus lap sem szolgálható ki (token-szabály) — az adatbázisos
        // tesztek a valódi kezdő témával indulnak, hamis (ideiglenes) arculati tárral.
        Theme::flush();
        // A beállítás-gyorsítótár statikus — tesztek között törölni kell, különben egy korábbi teszt
        // beállításai „átszivárognak”, és elrejtik, ha egy teszt nem állítja be, amire szüksége van.
        Settings::flush();
        if (in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            Storage::fake(Theme::DISK);
            Theme::importSeed('teszt');
        }
    }
}
