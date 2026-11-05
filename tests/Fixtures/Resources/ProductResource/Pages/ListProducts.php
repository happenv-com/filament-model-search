<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Resources\ProductResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\ProductResource;

final class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;
}
