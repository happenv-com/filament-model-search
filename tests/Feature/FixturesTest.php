<?php

declare(strict_types=1);

use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Customer;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\User;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\CustomerResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\ProductResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\OrderSearch;
use Happenv\ModelSearch\Facades\ModelSearches;

covers(OrderSearch::class);

it('finds an order through its customer', function (): void {
    $customer = Customer::query()->create(['name' => 'Zofia Wisniewska', 'email' => 'zofia@example.test']);
    $order = Order::query()->create(['signature' => 'OR3003', 'customer_id' => $customer->id]);
    Order::query()->create(['signature' => 'OR4004']);

    $this->indexAll();

    $found = array_values(iterator_to_array(OrderSearch::query('zofia@example.test')->within(Order::query(), via: [Customer::class => Customer::query()])->get()));

    expect($order->customer->is($customer))->toBeTrue()
        ->and($found)->toHaveCount(1)
        ->and($found[0]->model->is($order))->toBeTrue();
});

it('declares one constraint stage next to the unconstrained one', function (): void {
    $stages = array_map(static fn ($stage): string => $stage->key, ModelSearches::for(Order::class)->stages);

    expect($stages)->toHaveCount(2);
});

it('indexes rows created after the first drain', function (): void {
    Order::query()->create(['signature' => 'OR5005']);
    $this->indexAll();
    $second = Order::query()->create(['signature' => 'OR6006']);
    $this->indexAll();

    $found = array_values(iterator_to_array(OrderSearch::query('OR6006')->within(Order::query(), via: [Customer::class => Customer::query()])->get()));

    expect($found)->toHaveCount(1)->and($found[0]->model->is($second))->toBeTrue();
});

it('gives resources the pages and title attribute later tasks rely on', function (): void {
    expect(array_keys(OrderResource::getPages()))->toBe(['index', 'view', 'edit'])
        ->and(array_keys(CustomerResource::getPages()))->toBe(['index', 'edit'])
        ->and(OrderResource::getRecordTitleAttribute())->toBe('signature')
        ->and(CustomerResource::getRecordTitleAttribute())->toBe('name')
        ->and(ProductResource::getRecordTitleAttribute())->toBe('sku');
});

it('lets a user see orders but not customers', function (): void {
    $user = User::factory()->withAbilities(['orders.viewAny', 'orders.view'])->create();
    $this->actingAs($user);

    expect(OrderResource::canViewAny())->toBeTrue()
        ->and(CustomerResource::canViewAny())->toBeFalse()
        ->and($user->can('view', new Order))->toBeTrue()
        ->and($user->can('update', new Order))->toBeFalse();
});

it('gives a user without abilities no access', function (): void {
    $this->actingAs(User::factory()->create());

    expect(OrderResource::canViewAny())->toBeFalse();
});
