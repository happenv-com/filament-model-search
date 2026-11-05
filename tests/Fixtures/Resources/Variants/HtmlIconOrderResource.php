<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Resources\Variants;

use BackedEnum;
use Filament\Resources\Resource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Override;

final class HtmlIconOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    #[Override]
    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return new HtmlString('<svg></svg>');
    }
}
