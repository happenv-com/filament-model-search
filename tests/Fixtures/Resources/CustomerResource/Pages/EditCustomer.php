<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Resources\CustomerResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\CustomerResource;

final class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;
}
