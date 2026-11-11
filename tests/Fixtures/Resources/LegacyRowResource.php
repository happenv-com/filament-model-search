<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Resources;

use Filament\Resources\Resource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\FilamentModelSearch\Tests\Fixtures\Rows\RowLabel;

/**
 * A resource that still names a row class the way versions before 0.5 did.
 */
final class LegacyRowResource extends Resource
{
    protected static ?string $model = Order::class;

    public static function getGlobalSearchResultRow(): ?string
    {
        return RowLabel::class;
    }
}
