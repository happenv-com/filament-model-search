<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Searches;

use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Customer;
use Happenv\ModelSearch\Definition\ModelSearch;
use Happenv\ModelSearch\Definition\SearchesModel;
use Happenv\ModelSearch\Definition\SearchField;

#[SearchesModel(Customer::class)]
final class CustomerSearch extends ModelSearch
{
    public function fields(): array
    {
        return [
            SearchField::key('email'),
            SearchField::key('phone'),
            SearchField::name('name'),
        ];
    }
}
