<?php

namespace App\Providers\Filament;

use App\Http\Controllers\Admin\ProgramFotoController;
use App\Http\Controllers\Admin\TemaController;
use App\Support\Theme;
use Illuminate\Support\HtmlString;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /** Logó + a panel neve egymás mellett (a Filament a magasságot a brandLogoHeight-ból adja). */
    private static function brand(): HtmlString
    {
        $nev = e(config('app.name'));
        $logo = self::themeAsset('logo');
        $kep = $logo ? '<img src="' . e($logo) . '" alt="" style="height:100%;width:auto;flex:none">' : '';

        return new HtmlString('<span style="display:inline-flex;align-items:center;gap:.55rem;height:100%;white-space:nowrap">'
            . $kep . '<span>' . $nev . '</span></span>');
    }

    /**
     * Egy arculati fájl címe a témából. Ha a téma nem olvasható (pl. üres adatbázis telepítés közben),
     * az admin NE álljon le emiatt — különben épp a javításhoz nem lehetne belépni. Ilyenkor a logó/favicon
     * egyszerűen elmarad, és a hiba a naplóba kerül (a publikus lapok ettől még hibával állnak meg).
     */
    private static function themeAsset(string $key): ?string
    {
        try {
            return Theme::assetUrl($key);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // KIZÁRÓLAG az admin-felhasználók őre (users tábla). A programbeküldők a 'bekuldo' őrrel,
            // külön jelszótárból lépnek be — az ő fiókjukkal ide belépni nem lehet (CLAUDE.md 2. fázis, 2.7).
            ->authGuard('web')
            // Szigorú jogosultság: policy nélküli modell lapja NEM nyílik meg mindenkinek, hanem hibával áll meg
            // (app/Policies — az alap mindent tilt, amit a szerepkör-mátrix, App\Enums\UserRole, meg nem enged).
            ->strictAuthorization()
            ->colors([
                'primary' => Color::Amber,
            ])
            // A márka a TÉMÁBÓL jön (a feltöltött logó és favicon — nincs második példány): kis logó a
            // név előtt, a fejléc magassága nem nő (2rem). Lásd self::brand().
            ->brandLogo(fn () => self::brand())
            ->brandLogoHeight('2rem')
            ->favicon(fn () => self::themeAsset('favicon'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            // A nyitólap a saját Dashboard-unk (app/Filament/Pages — a felfedezés regisztrálja): az
            // arculat-szerkesztőt egyenesen a /tema-ra küldi.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // Belépett admin-útvonalak (a panel belépés-ellenőrzése mögött). A nem nyilvános programfotó:
            ->authenticatedRoutes(function () {
                Route::get('programok/{program}/foto', ProgramFotoController::class)
                    ->whereNumber('program')->name('program-foto');
                // Az Arculat-szerkesztő (/admin/tema) végpontjai — mind a TemaController jog-őre mögött.
                // Az élő előnézet NEM hív szervert; ezek csak a mentés/publikálás/feltöltés útjai.
                Route::prefix('tema-api')->name('tema.')->controller(TemaController::class)->group(function () {
                    Route::get('elonezet', 'elonezet')->name('elonezet');
                    Route::post('vazlat', 'vazlat')->name('vazlat');
                    Route::delete('vazlat', 'vazlatEldob')->name('vazlat.eldob');
                    Route::post('publikalas', 'publikalas')->name('publikalas');
                    Route::post('feltoltes', 'feltoltes')->name('feltoltes');
                    Route::get('gyari', 'gyari')->name('gyari');
                    Route::get('verzio/{version}', 'verzio')->whereNumber('version')->name('verzio');
                });
            });
    }
}
