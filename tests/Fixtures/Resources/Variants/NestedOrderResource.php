<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Resources\Variants;

use Filament\Resources\ParentResourceRegistration;
use Filament\Resources\Resource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\CustomerResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource;
use Override;

final class NestedOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    #[Override]
    public static function getParentResourceRegistration(): ?ParentResourceRegistration
    {
        return CustomerResource::asParent()->relationship('orders');
    }

    #[Override]
    public static function getPages(): array
    {
        return OrderResource::getPages();
    }
}
