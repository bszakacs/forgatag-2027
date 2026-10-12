<?php

namespace App\Providers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
