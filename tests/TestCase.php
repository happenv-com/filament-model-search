<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Closure;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\Components\Select;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Resources\Resource;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Happenv\FilamentModelSearch\Binding\ResourceBinding;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\FilamentModelSearch\FilamentModelSearchServiceProvider;
use Happenv\FilamentModelSearch\Tests\Fixtures\AdminPanelProvider;
use Happenv\FilamentModelSearch\Tests\Fixtures\FixtureSearchServiceProvider;
use Happenv\FilamentModelSearch\Tests\Fixtures\Livewire\OrderForm;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Customer;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\User;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource\Pages\ListOrders;
use Happenv\ModelSearch\ModelSearchServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use LogicException;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // The whole test runs in one transaction: that is the baseline a drain may run at.
        config(['model-search.drain.allowed_transaction_level' => 1]);

        Filament::setCurrentPanel('admin');
    }

    /**
     * Brings the index up to date with the rows the test created, installing the types that were not.
     *
     * Only through the commands of the core: `model-search:sync` installs, `model-search:drain` indexes.
     */
    protected function indexAll(): void
    {
        $this->artisan('model-search:sync')->assertSuccessful();
        $this->artisan('model-search:drain')->assertSuccessful();
    }

    /**
     * The abilities of a viewer who may use every resource of the fixture panel.
     *
     * @return list<string>
     */
    protected function allAbilities(): array
    {
        return ['orders.viewAny', 'orders.view', 'customers.viewAny', 'customers.update'];
    }

    /**
     * The binding a fresh registry makes of a definition and a resource.
     *
     * @param  class-string  $search
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    protected function bindingOf(string $search, string $resource): ResourceBinding
    {
        $bindings = new ResourceBindings;
        $bindings->register($search, $resource);

        return $bindings->all()[0];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function createOrder(string $signature, ?string $external = null, array $extra = []): Order
    {
        return Order::query()->create(['signature' => $signature, 'external_number' => $external, ...$extra]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function createCustomer(string $name, ?string $email = null, ?string $phone = null, array $extra = []): Customer
    {
        return Customer::query()->create(['name' => $name, 'email' => $email, 'phone' => $phone, ...$extra]);
    }

    /**
     * The titles of what a resource's global search answers, in order.
     *
     * @param  class-string<\Filament\Resources\Resource>  $resource
     * @return list<string>
     */
    protected function globalTitles(string $resource, #[\SensitiveParameter] string $phrase): array
    {
        return $resource::getGlobalSearchResults($phrase)->map(static fn ($result): string => (string) $result->title)->values()->all();
    }

    /**
     * The customer select of the fixture order form, shaped through `fixtures.customer_select`.
     */
    protected function customerSelect(): Select
    {
        $component = Livewire::test(OrderForm::class)->instance();
        $select = $component->getSchema('form')?->getComponent('customer_id');

        assert($select instanceof Select);

        return $select;
    }

    /**
     * Lists the orders with a customer filter and returns the select that filter renders.
     *
     * @param  Closure(SelectFilter): SelectFilter|null  $shape  Receives the bare filter; defaults to a searchable relationship filter with `modelSearch()`.
     */
    protected function customerFilterSelect(?Closure $shape = null): Select
    {
        config(['fixtures.order_table' => static fn (Table $table): Table => $table->filters([
            $shape instanceof Closure
                ? $shape(SelectFilter::make('customer_id'))
                : SelectFilter::make('customer_id')->relationship('customer', 'name')->searchable()->modelSearch(),
        ])]);

        $form = Livewire::test(ListOrders::class)->instance()->getTableFiltersForm();

        foreach ($form->getFlatComponents(withHidden: true) as $component) {
            if ($component instanceof Select) {
                return $component;
            }
        }

        throw new LogicException('The filter rendered no select.');
    }

    /**
     * The statements run on the customers table that restrict it to a list of keys, while the callback runs.
     *
     * @return list<array{query: string, bindings: array<int, mixed>}>
     */
    protected function loadingQueries(Closure $callback): array
    {
        $loads = [];

        DB::listen(static function ($query) use (&$loads): void {
            // A plain list of placeholders, not a subquery: the statement that loads the ranked keys.
            if (preg_match('/"customers"\."(id|code)" in \(\?(, \?)*\)/', $query->sql) === 1) {
                $loads[] = ['query' => $query->sql, 'bindings' => $query->bindings];
            }
        });

        $callback();

        return $loads;
    }

    /**
     * @param  list<string>  $abilities  For example `orders.viewAny`, `orders.view`.
     */
    protected function signIn(array $abilities = []): User
    {
        $user = User::factory()->withAbilities($abilities)->create();

        $this->actingAs($user);

        return $user;
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            ModelSearchServiceProvider::class,
            FilamentModelSearchServiceProvider::class,
            FixtureSearchServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'postgres'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', 'postgres'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/Migrations');
    }
}
