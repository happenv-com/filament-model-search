<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures;

use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Customer;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Product;
use Happenv\FilamentModelSearch\Tests\Fixtures\Policies\CustomerPolicy;
use Happenv\FilamentModelSearch\Tests\Fixtures\Policies\OrderPolicy;
use Happenv\FilamentModelSearch\Tests\Fixtures\Policies\ProductPolicy;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\CustomerSearch;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\OrderSearch;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\ProductSearch;
use Happenv\ModelSearch\Facades\ModelSearches;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the fixture search definitions before the test migrations run.
 */
final class FixtureSearchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Relation::morphMap([
            'order' => Order::class,
            'customer' => Customer::class,
            'product' => Product::class,
        ]);

        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);

        ModelSearches::register(OrderSearch::class);
        ModelSearches::register(CustomerSearch::class);
        ModelSearches::register(ProductSearch::class);
    }
}
