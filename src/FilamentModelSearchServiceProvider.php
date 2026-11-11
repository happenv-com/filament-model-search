<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch;

use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\FilamentModelSearch\Forms\SelectModelSearch;
use Happenv\FilamentModelSearch\History\PaletteHistory;
use Happenv\FilamentModelSearch\Livewire\RecordPalette;
use Happenv\FilamentModelSearch\Support\FailureReports;
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
        $this->app->singleton(FailureReports::class);
        $this->app->singleton(PaletteHistory::class);
    }

    public function boot(): void
    {
        new TableModelSearch()->register();
        new SelectModelSearch()->register();
        new SelectFilterModelSearch()->register();

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-model-search');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-model-search');

        if (config('filament-model-search.palette.history.run_migrations', true) === true) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        Livewire::component(FilamentModelSearchPlugin::PALETTE_COMPONENT, RecordPalette::class);

        $this->publishes([
            __DIR__.'/../config/filament-model-search.php' => config_path('filament-model-search.php'),
        ], 'filament-model-search-config');

        $this->publishes([
            __DIR__.'/../database/migrations/2026_11_12_000001_create_model_search_palette_history_table.php' => database_path('migrations/2026_11_12_000001_create_model_search_palette_history_table.php'),
        ], 'filament-model-search-migrations');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/filament-model-search'),
        ], 'filament-model-search-translations');
    }
}
