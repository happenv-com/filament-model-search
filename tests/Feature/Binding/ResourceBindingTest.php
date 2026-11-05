<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\ParentResourceRegistration;
use Filament\Resources\Resource;
use Happenv\FilamentModelSearch\Binding\ResourceBinding;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\FilamentModelSearch\Concerns\InteractsWithModelSearch;
use Happenv\FilamentModelSearch\Facades\FilamentModelSearch;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Customer;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\CustomerResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\CustomerSearch;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\OrderSearch;
use Happenv\ModelSearch\Postgres\Indexing\IndexState;
use Happenv\ModelSearch\Registry\ModelSearchRegistry;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

covers(ResourceBindings::class, ResourceBinding::class);

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

final class PlainOrderResource extends Resource
{
    protected static ?string $model = Order::class;
}

final class HtmlIconOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    #[Override]
    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return new HtmlString('<svg></svg>');
    }
}

final class NestedOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    #[Override]
    public static function getParentResourceRegistration(): ?ParentResourceRegistration
    {
        return CustomerResource::asParent()->relationship('orders');
    }

    #[Override]
    public static function getPages(): array
    {
        return OrderResource::getPages();
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

    // The shared instance was filled by the admin panel's plugin; the facade adds a panel-agnostic pair.
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

it('fills the shared bindings when a panel registers its plugin', function (): void {
    $bindings = resolve(ResourceBindings::class);

    expect($bindings->for(Order::class)?->resourceClass())->toBe(OrderResource::class)
        ->and($bindings->for(Customer::class)?->resourceClass())->toBe(CustomerResource::class)
        ->and($bindings->all())->toHaveCount(3);
});

it('refuses a listed resource without the trait, naming the fix', function (): void {
    $bindings = new ResourceBindings;

    expect(fn () => $bindings->registerResource(PlainOrderResource::class))
        ->toThrow(LogicException::class, 'InteractsWithModelSearch')
        ->and(fn () => $bindings->register(PlainOrderResource::class, OrderResource::class))
        ->toThrow(LogicException::class, 'not a search definition');
});

it('lets two panels bind different resources for one model', function (): void {
    $bindings = new ResourceBindings;
    $bindings->register(OrderSearch::class, OrderResource::class, 'admin');
    $bindings->register(OrderSearch::class, TraitOrderResource::class, 'other');

    expect(fn () => $bindings->register(OrderSearch::class, TraitOrderResource::class, 'admin'))->toThrow(LogicException::class);

    Filament::setCurrentPanel('admin');
    expect($bindings->for(Order::class)?->resourceClass())->toBe(OrderResource::class);

    $other = Panel::make()->id('other')->path('other');
    Filament::registerPanel($other);
    Filament::setCurrentPanel($other);
    expect($bindings->for(Order::class)?->resourceClass())->toBe(TraitOrderResource::class)
        ->and($bindings->all())->toHaveCount(1);
});

it('does not give access to a resource that is not registered on the current panel', function (): void {
    $binding = binding(OrderSearch::class, OrderResource::class);
    $order = Order::query()->create(['signature' => 'OR7007']);
    $this->signIn(['orders.viewAny', 'orders.view']);

    expect($binding->isAccessible())->toBeTrue();

    $other = Panel::make()->id('other')->path('other');
    Filament::registerPanel($other);
    Filament::setCurrentPanel($other);

    expect($binding->isAccessible())->toBeFalse()
        ->and($binding->url($order))->toBeNull();
});

it('has no url for a nested resource', function (): void {
    $order = Order::query()->create(['signature' => 'OR8008']);
    $this->signIn(['orders.viewAny', 'orders.view', 'orders.update']);

    expect(binding(OrderSearch::class, NestedOrderResource::class)->url($order))->toBeNull();
});

it('reads icon and sort, and drops an Htmlable icon', function (): void {
    expect(binding(CustomerSearch::class, CustomerResource::class)->icon())->toBe('heroicon-o-users')
        ->and(binding(CustomerSearch::class, CustomerResource::class)->sort())->toBe(20)
        ->and(binding(OrderSearch::class, HtmlIconOrderResource::class)->icon())->toBeNull()
        ->and(binding(OrderSearch::class, HtmlIconOrderResource::class)->sort())->toBe(0);
});

it('answers from the core when the cache store fails', function (): void {
    config(['model-search.cache_store' => 'no-such-store']);

    expect(binding(OrderSearch::class, OrderResource::class)->isReady())->toBeFalse();
});

it('keeps readiness apart per database on one connection', function (): void {
    $name = config('database.connections.pgsql.database').'_b';
    $maintenance = [...config('database.connections.pgsql'), 'database' => 'postgres'];
    config(['database.connections.maintenance' => $maintenance]);
    $pdo = DB::connection('maintenance')->getPdo();
    $pdo->exec(sprintf('DROP DATABASE IF EXISTS "%s"', $name));
    $pdo->exec(sprintf('CREATE DATABASE "%s"', $name));

    $original = config('database.connections.pgsql.database');
    $binding = binding(OrderSearch::class, OrderResource::class);

    try {
        // Database A: installed and indexed.
        Order::query()->create(['signature' => 'OR9009']);
        $this->indexAll();
        expect($binding->isReady())->toBeTrue();

        // A tenancy switch: the same connection, another database, set up but never indexed.
        config(['database.connections.pgsql.database' => $name]);
        DB::purge('pgsql');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        expect($binding->isReady())->toBeFalse();
    } finally {
        config(['database.connections.pgsql.database' => $original]);
        DB::purge('pgsql');
        $pdo->exec(sprintf('DROP DATABASE IF EXISTS "%s" WITH (FORCE)', $name));
        DB::purge('maintenance');
    }
});

it('reports a failing cache store once and still answers from the core', function (): void {
    config(['model-search.cache_store' => 'no-such-store']);
    $reported = [];
    $this->app->make(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported): void {
        $reported[] = $e;
    });

    expect(binding(OrderSearch::class, OrderResource::class)->isReady())->toBeFalse()
        ->and($reported)->toHaveCount(1);
});
