<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource;

final class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;
}
