<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch;

use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\FilamentModelSearch\Forms\SelectModelSearch;
use Happenv\FilamentModelSearch\Livewire\RecordPalette;
use Happenv\FilamentModelSearch\Tables\SelectFilterModelSearch;
use Happenv\FilamentModelSearch\Tables\TableModelSearch;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class FilamentModelSearchServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-model-search.php', 'filament-model-search');

        $this->app->singleton(ResourceBindings::class);
    }

    public function boot(): void
    {
        new TableModelSearch()->register();
        new SelectModelSearch()->register();
        new SelectFilterModelSearch()->register();

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-model-search');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-model-search');

        Livewire::component(FilamentModelSearchPlugin::PALETTE_COMPONENT, RecordPalette::class);

        $this->publishes([
            __DIR__.'/../config/filament-model-search.php' => config_path('filament-model-search.php'),
        ], 'filament-model-search-config');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/filament-model-search'),
        ], 'filament-model-search-translations');
    }
}
