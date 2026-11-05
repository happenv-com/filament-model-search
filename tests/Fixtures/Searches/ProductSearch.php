<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Searches;

use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Product;
use Happenv\ModelSearch\Definition\ModelSearch;
use Happenv\ModelSearch\Definition\SearchesModel;
use Happenv\ModelSearch\Definition\SearchField;

#[SearchesModel(Product::class)]
final class ProductSearch extends ModelSearch
{
    public function fields(): array
    {
        return [
            SearchField::key('sku'),
            SearchField::key('ean'),
            SearchField::name('name')->translatable(),
        ];
    }
}
