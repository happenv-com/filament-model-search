<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Searches;

use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\ModelSearch\Definition\ModelSearch;
use Happenv\ModelSearch\Definition\SearchesModel;
use Happenv\ModelSearch\Definition\SearchField;

#[SearchesModel(Order::class)]
final class OrderSearch extends ModelSearch
{
    public function fields(): array
    {
        return [
            SearchField::key('signature')->exactOnSource()->label(static fn (): string => 'Signature'),
            SearchField::key('external_number'),
            SearchField::key('customer_email'),
            SearchField::name('customer_name'),
        ];
    }

    public function sortAt(): ?string
    {
        return 'purchased_at';
    }
}
