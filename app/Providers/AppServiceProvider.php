<?php

namespace App\Providers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A programbeküldés védelme (2026-ban a végponton semmilyen korlát nem volt).
        // Beküldés + javítás: IP-nként óránként 30 — egy szervezet egy gépről is bőven elfér benne.
        RateLimiter::for('bekuldes', fn (Request $r) => Limit::perHour(30)->by('bekuldes|' . $r->ip())
            ->response(fn () => response()->json(['ok' => false, 'errors' => ['Túl sok beküldés rövid idő alatt. Próbáld újra egy óra múlva; ha sürgős, keresd a szervezőt.']], 429)));
        // Belépési kísérletek IP-nként (a fiókonkénti zárolás a BelepesControllerben van).
        RateLimiter::for('bekuldes-belepes', fn (Request $r) => Limit::perMinutes(15, 30)->by('belepes|' . $r->ip())
            ->response(fn () => back()->withErrors(['email' => 'Túl sok sikertelen próbálkozás. Próbáld újra pár perc múlva.'])));

        // A Filament gombjai angolosan minden szót nagybetűsítenek („Új Programnap”) — magyarul hibás.
        // Itt, egy helyen igazítjuk: mondat elején nagy, egyébként kis kezdőbetű.
        CreateAction::configureUsing(fn (CreateAction $a) => $a->modalHeading(
            fn () => __('filament-actions::create.single.modal.heading', ['label' => $a->getModelLabel()])
        ));
        EditAction::configureUsing(fn (EditAction $a) => $a->modalHeading(
            fn () => __('filament-actions::edit.single.modal.heading', ['label' => Str::ucfirst($a->getModelLabel())])
        ));
        DeleteAction::configureUsing(fn (DeleteAction $a) => $a->modalHeading(
            fn () => __('filament-actions::delete.single.modal.heading', ['label' => Str::ucfirst($a->getModelLabel())])
        ));
    }
}
