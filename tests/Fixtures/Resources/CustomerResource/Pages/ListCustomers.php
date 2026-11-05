<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Resources\CustomerResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\CustomerResource;

final class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
}
