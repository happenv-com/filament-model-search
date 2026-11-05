<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Resources\Variants;

use Filament\Resources\Resource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;

final class PlainOrderResource extends Resource
{
    protected static ?string $model = Order::class;
}
