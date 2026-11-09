<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Happenv\FilamentModelSearch\FilamentModelSearchPlugin;
use Happenv\FilamentModelSearch\Http\Controllers\RecordPaletteController;

covers(RecordPaletteController::class);

beforeEach(function (): void {
    config(['filament-model-search.readiness_cache_seconds' => 0]);
});

$url = static fn (): string => Filament::getPanel('admin')->route(FilamentModelSearchPlugin::PALETTE_ROUTE, absolute: false);

it('serves the component, its modal and its styles to a user it is offered to, never for a cache', function () use ($url): void {
    $this->signIn(['orders.viewAny', 'orders.view']);

    $response = $this->get($url());

    $response->assertOk()
        ->assertSee('wire:snapshot', false)
        ->assertSee('fi-record-palette-input', false)
        ->assertSee('<style', false);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('refuses a user who may open no searched resource', function () use ($url): void {
    $this->signIn([]);

    $this->get($url())->assertForbidden();
});

it('refuses when the palette is switched off', function () use ($url): void {
    config(['filament-model-search.palette.enabled' => false]);
    $this->signIn(['orders.viewAny', 'orders.view']);

    $this->get($url())->assertForbidden();
});

it('does not serve a guest', function () use ($url): void {
    $this->get($url())->assertForbidden();
});
