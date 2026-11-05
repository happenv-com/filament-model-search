<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Happenv\FilamentModelSearch\FilamentModelSearchPlugin;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\CustomerResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\ProductResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\OrderSearch;
use Happenv\ModelSearch\Facades\ModelSearches;

covers(FilamentModelSearchPlugin::class);

it('registers the plugin on a panel', function (): void {
    $plugin = Filament::getPanel('admin')->getPlugin('filament-model-search');

    expect($plugin)->toBeInstanceOf(FilamentModelSearchPlugin::class)
        ->and($plugin->getId())->toBe('filament-model-search')
        ->and(FilamentModelSearchPlugin::make())->toBeInstanceOf(FilamentModelSearchPlugin::class);
});

it('only stores the resource classes and the palette flag', function (): void {
    $plugin = FilamentModelSearchPlugin::make()
        ->resources([OrderResource::class, CustomerResource::class, ProductResource::class])
        ->palette(false);

    expect($plugin->getResources())->toBe([OrderResource::class, CustomerResource::class, ProductResource::class])
        ->and($plugin->isPaletteEnabled())->toBeFalse()
        ->and(FilamentModelSearchPlugin::make()->isPaletteEnabled())->toBeTrue()
        ->and(FilamentModelSearchPlugin::make()->palette()->isPaletteEnabled())->toBeTrue();
});

it('publishes the config with the documented defaults', function (): void {
    expect(config('filament-model-search.palette.enabled'))->toBeTrue()
        ->and(config('filament-model-search.palette.keybindings'))->toBe(['mod+shift+k'])
        ->and(config('filament-model-search.palette.debounce_ms'))->toBe(250)
        ->and(config('filament-model-search.palette.per_group'))->toBe(5)
        ->and(config('filament-model-search.readiness_cache_seconds'))->toBe(60);
});

it('loads translations in en, pl and de', function (string $locale): void {
    $namespaces = app('translator')->getLoader()->namespaces();

    expect($namespaces)->toHaveKey('filament-model-search')
        ->and(is_dir($namespaces['filament-model-search'].'/'.$locale))->toBeTrue();
})->with(['en', 'pl', 'de']);

it('runs the core lifecycle in the harness', function (): void {
    $order = Order::query()->create(['signature' => 'OR1001', 'customer_name' => 'Jan Kowalski']);
    $other = Order::query()->create(['signature' => 'OR2002', 'customer_name' => 'Anna Nowak']);

    $this->indexAll();

    expect(ModelSearches::isReady(Order::class))->toBeTrue();

    $found = array_values(iterator_to_array(OrderSearch::query('OR1001')->within(Order::query())->get()));

    expect($found)->toHaveCount(1)
        ->and($found[0]->model->is($order))->toBeTrue()
        ->and($found[0]->model->is($other))->toBeFalse();
});
