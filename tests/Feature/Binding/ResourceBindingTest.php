<?php

declare(strict_types=1);

use Filament\Resources\Resource;
use Happenv\FilamentModelSearch\Binding\ResourceBinding;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\FilamentModelSearch\Concerns\InteractsWithModelSearch;
use Happenv\FilamentModelSearch\Facades\FilamentModelSearch;
use Happenv\FilamentModelSearch\FilamentModelSearchPlugin;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Customer;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\CustomerResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\CustomerSearch;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\OrderSearch;
use Happenv\ModelSearch\Postgres\Indexing\IndexState;
use Happenv\ModelSearch\Registry\ModelSearchRegistry;

covers(ResourceBindings::class);

final class TraitOrderResource extends Resource
{
    use InteractsWithModelSearch;

    protected static ?string $model = Order::class;

    #[Override]
    public static function modelSearch(): string
    {
        return OrderSearch::class;
    }
}

function binding(string $search, string $resource): ResourceBinding
{
    $bindings = new ResourceBindings;
    $bindings->register($search, $resource);

    return $bindings->all()[0];
}

it('binds a search to a resource explicitly and through the trait', function (): void {
    $bindings = new ResourceBindings;
    $bindings->register(CustomerSearch::class, CustomerResource::class);
    $bindings->registerResource(TraitOrderResource::class);

    expect($bindings->for(Customer::class)?->resourceClass())->toBe(CustomerResource::class)
        ->and($bindings->for(Customer::class)?->searchClass())->toBe(CustomerSearch::class)
        ->and($bindings->for(Order::class)?->resourceClass())->toBe(TraitOrderResource::class)
        ->and($bindings->all())->toHaveCount(2);

    // The facade and the panel plugin fill the shared instance; the panel lists no trait resources
    // here, so the shared instance holds only what was bound explicitly.
    FilamentModelSearch::resource(OrderSearch::class, OrderResource::class);
    FilamentModelSearch::resource(OrderSearch::class, OrderResource::class);

    expect(resolve(ResourceBindings::class)->for(Order::class)?->resourceClass())->toBe(OrderResource::class);
});

it('refuses a definition of another model and a second binding of the same model', function (): void {
    $bindings = new ResourceBindings;

    expect(fn () => $bindings->register(CustomerSearch::class, OrderResource::class))->toThrow(LogicException::class);

    $bindings->register(OrderSearch::class, OrderResource::class);

    expect(fn () => $bindings->register(OrderSearch::class, TraitOrderResource::class))->toThrow(LogicException::class);
});

it('evaluates access on every call, not at registration', function (): void {
    $binding = binding(OrderSearch::class, OrderResource::class);

    $this->signIn(['orders.viewAny']);
    expect($binding->isAccessible())->toBeTrue();

    $this->signIn([]);
    expect($binding->isAccessible())->toBeFalse();
});

it('prefers view over edit and never returns a delete url', function (): void {
    $order = Order::query()->create(['signature' => 'OR1001']);
    $customer = Customer::query()->create(['name' => 'Ann', 'email' => 'ann@example.test']);
    $orders = binding(OrderSearch::class, OrderResource::class);
    $customers = binding(CustomerSearch::class, CustomerResource::class);

    $this->signIn(['orders.viewAny', 'orders.view', 'orders.update', 'customers.viewAny', 'customers.update']);

    expect($orders->url($order))->toEndWith('/admin/orders/'.$order->getKey())
        ->and($customers->url($customer))->toEndWith('/admin/customers/'.$customer->getKey().'/edit');

    $this->signIn(['orders.viewAny', 'orders.update']);
    expect($orders->url($order))->toEndWith('/admin/orders/'.$order->getKey().'/edit')
        ->and($orders->url($order))->not->toContain('delete');

    $this->signIn(['orders.viewAny']);
    expect($orders->url($order))->toBeNull();
});

it("scope is the resource's global search query", function (): void {
    $binding = binding(OrderSearch::class, OrderResource::class);

    $this->signIn(['orders.viewAny']);

    expect($binding->scope()->toSql())->toBe(OrderResource::getGlobalSearchEloquentQuery()->toSql())
        ->and($binding->scope()->getModel())->toBeInstanceOf(Order::class);
});

it('reads title, icon and sort from the resource at call time', function (): void {
    $binding = binding(OrderSearch::class, OrderResource::class);
    $order = Order::query()->create(['signature' => 'OR2002']);

    expect((string) $binding->title($order))->toBe('OR2002')
        ->and($binding->sort())->toBeInt();
});

it('readiness reflects the core and refreshes after the cache window', function (): void {
    config(['filament-model-search.readiness_cache_seconds' => 60]);
    $binding = binding(OrderSearch::class, OrderResource::class);

    expect($binding->isReady())->toBeFalse();

    $registry = resolve(ModelSearchRegistry::class);
    new IndexState()->recordInstall($registry->for(Order::class));
    $this->indexAll();

    // The core's own cache is a few seconds old at most; ours still holds the earlier answer.
    $this->travel(30)->seconds();
    expect($binding->isReady())->toBeFalse();

    $this->travel(40)->seconds();
    expect($binding->isReady())->toBeTrue();
});

it('binds the trait resources a panel lists when the plugin registers, and ignores the others', function (): void {
    $plugin = FilamentModelSearchPlugin::make()
        ->resources([TraitOrderResource::class, CustomerResource::class]);

    $plugin->register(Filament\Facades\Filament::getCurrentPanel());
    $plugin->register(Filament\Facades\Filament::getCurrentPanel());

    $bindings = resolve(ResourceBindings::class);

    expect($bindings->for(Order::class)?->resourceClass())->toBe(TraitOrderResource::class)
        ->and($bindings->for(Customer::class))->toBeNull();
});
