<?php

use App\Enums\NotificationTopic;
use App\Mail\SzovegesLevel;
use App\Support\Festival;
use App\Support\Notifier;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A beküldő űrlapon a tárhely kéréskorlátját túllépő fotó a PHP-n akad el, még a belépés
        // ellenőrzése előtt. Ez is FOTÓHIBA: a beküldő érthető üzenetet lát, a koordinátor jelzést kap
        // (IP-nként óránként legfeljebb 3-at, hogy egy robot ne árassza el a postafiókot).
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if (! $request->is('bekuldes/*')) {
                return null;
            }
            RateLimiter::attempt('fotohiba-413|' . $request->ip(), 3, function () use ($request) {
                Notifier::topic(NotificationTopic::ProgramSubmission, new SzovegesLevel(
                    'Programfotó feltöltése nem sikerült – ' . Festival::name(),
                    "Egy beküldőnél a fotó nagyobb volt, mint amit a tárhely egy kérésben fogad, ezért a beküldése NEM ment át.\n"
                    . "A beküldő a felületen hibaüzenetet látott; lehet, hogy segítség kell neki.\n\n"
                    . 'Kérés mérete: ' . round(((int) $request->server('CONTENT_LENGTH')) / 1024 / 1024, 1) . ' MB · korlát: ' . ini_get('post_max_size') . "\n"
                    . 'IP-cím: ' . $request->ip(),
                ));
            }, 3600);

            return response()->json(['ok' => false, 'errors' => [
                'A fotó feltöltése nem sikerült: a fájl túl nagy a szervernek. A program NEM jött létre. Próbáld kisebb fájllal; ha most sem megy, keresd a szervezőt.',
            ]], 413);
        });
    })->create();
