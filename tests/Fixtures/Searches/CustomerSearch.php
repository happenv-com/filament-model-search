<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Searches;

use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Customer;
use Happenv\ModelSearch\Definition\ModelSearch;
use Happenv\ModelSearch\Definition\SearchesModel;
use Happenv\ModelSearch\Definition\SearchField;
use Happenv\ModelSearch\Definition\SearchStage;
use Illuminate\Database\Eloquent\Builder;

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

    public function stages(): array
    {
        return [
            SearchStage::make('active', static fn (Builder $query): Builder => $query->where('active', true))
                ->watching(['active']),
            SearchStage::all(),
        ];
    }
}
