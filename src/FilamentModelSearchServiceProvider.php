<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch;

use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Illuminate\Support\ServiceProvider;

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
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-model-search');

        $this->publishes([
            __DIR__.'/../config/filament-model-search.php' => config_path('filament-model-search.php'),
        ], 'filament-model-search-config');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/filament-model-search'),
        ], 'filament-model-search-translations');
    }
}
