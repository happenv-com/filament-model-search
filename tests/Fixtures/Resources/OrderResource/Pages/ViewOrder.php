<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource;

final class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;
}
