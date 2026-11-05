<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Resources\Variants;

use Filament\Resources\Resource;
use Happenv\FilamentModelSearch\Concerns\InteractsWithModelSearch;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\OrderSearch;
use Override;

final class TraitOrderResource extends Resource
{
    use InteractsWithModelSearch;

    protected static ?string $model = Order::class;

    #[Override]
    public static function modelSearch(): string
    {
        return OrderSearch::class;
    }
}
