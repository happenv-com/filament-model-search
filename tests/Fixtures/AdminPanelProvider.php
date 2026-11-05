<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Happenv\FilamentModelSearch\FilamentModelSearchPlugin;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\CustomerResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\ProductResource;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->resources([OrderResource::class, CustomerResource::class, ProductResource::class])
            ->plugin(
                FilamentModelSearchPlugin::make()
                    ->resources([OrderResource::class, CustomerResource::class, ProductResource::class]),
            );
    }
}
