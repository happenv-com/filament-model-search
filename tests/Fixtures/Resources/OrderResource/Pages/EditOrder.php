<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource;

final class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;
}
