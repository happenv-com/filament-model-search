<?php

declare(strict_types=1);

use Filament\Tables\Table;
use Happenv\FilamentModelSearch\Support\SearchOutcome;
use Happenv\FilamentModelSearch\Tables\TableModelSearch;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource\Pages\ListOrders;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\CustomerSearch;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\OrderSearch;
use Happenv\ModelSearch\Postgres\Indexing\IndexState;
use Happenv\ModelSearch\Registry\ModelSearchRegistry;
use Livewire\Livewire;

covers(TableModelSearch::class);

beforeEach(function (): void {
    config(['filament-model-search.readiness_cache_seconds' => 0, 'fixtures.order_table' => null, 'fixtures.order_only' => null]);
    $this->signIn(['orders.viewAny', 'orders.view']);
});

it('leaves the native search in place until the type is ready', function (): void {
    $byColumn = $this->createOrder('OR1001', 'EXT-998877');
    $byIndex = $this->createOrder('OR1002', 'EXT-112233');

    Livewire::test(ListOrders::class)
        ->searchTable('OR1001')
        ->assertCanSeeTableRecords([$byColumn])
        ->assertCanNotSeeTableRecords([$byIndex])
        ->searchTable('998877')
        ->assertCountTableRecords(0);
});

it('searches through the index once ready', function (): void {
    $target = $this->createOrder('OR2001', 'EXT-998877');
    $other = $this->createOrder('OR2002', 'EXT-112233');
    $this->indexAll();

    Livewire::test(ListOrders::class)
        ->assertSeeHtml('fi-ta-search-field')
        ->searchTable('998877')
        ->assertCanSeeTableRecords([$target])
        ->assertCanNotSeeTableRecords([$other]);
});

it('keeps pagination, counting and sorting of the table', function (): void {
    $orders = collect(range(1, 12))->map(fn (int $n): Order => $this->createOrder(sprintf('OR3%03d', $n), 'EXT-555'.sprintf('%03d', $n)));
    $this->createOrder('OR3999', 'EXT-000000');
    $this->indexAll();

    $component = Livewire::test(ListOrders::class)->searchTable('EXT-555')->sortTable('signature', 'desc');

    $records = $component->instance()->getTableRecords();

    expect($records->total())->toBe(12)
        ->and($records->count())->toBe(10)
        ->and($records->pluck('signature')->all())->toBe($orders->pluck('signature')->reverse()->take(10)->values()->all());

    $component->call('gotoPage', 2);

    expect($component->instance()->getTableRecords()->count())->toBe(2);
});

it('shows the too-broad state instead of an empty table', function (): void {
    config(['model-search.apply.max_ids' => 1]);
    $this->createOrder('OR4001', 'EXT-777001');
    $this->createOrder('OR4002', 'EXT-777002');
    $this->createOrder('OR4003', 'EXT-777003');
    $this->indexAll();

    Livewire::test(ListOrders::class)
        ->searchTable('EXT-777')
        ->assertCountTableRecords(0)
        ->assertSee(trans(SearchOutcome::TooBroad->heading()))
        ->assertSee(trans(SearchOutcome::TooBroad->description()))
        ->assertDontSee(__('filament-tables::table.empty.heading', ['model' => 'orders']));
});

it('shows the timed-out state', function (): void {
    // The source-table driver embeds the table's scope in the search statement, so a slow scope outlasts the timeout.
    config(['model-search.driver' => 'database', 'model-search.search.timeout_ms' => 100]);
    $this->createOrder('OR5001', 'EXT-888001');
    config(['fixtures.order_table' => static fn (Table $table): Table => $table->modifyQueryUsing(
        static fn ($query) => $query->whereRaw('pg_sleep(0.5) IS NOT NULL'),
    )]);

    Livewire::test(ListOrders::class)
        ->searchTable('EXT-888001')
        ->assertCountTableRecords(0)
        ->assertSee(trans(SearchOutcome::TimedOut->heading()))
        ->assertSee(trans(SearchOutcome::TimedOut->description()));
});

it('returns no rows for a failed search even when the table query holds an orWhere', function (): void {
    config(['model-search.apply.max_ids' => 1]);
    $this->createOrder('OR4101', 'EXT-717001');
    $this->createOrder('OR4102', 'EXT-717002');
    $this->indexAll();

    // Both orders satisfy the FIRST branch: a plain `AND 1 = 0` would still return them.
    config(['fixtures.order_table' => static fn (Table $table): Table => $table->modifyQueryUsing(
        static fn ($query) => $query->where('state', 'unshipped')->orWhere('state', 'shipped'),
    )]);

    Livewire::test(ListOrders::class)
        ->searchTable('EXT-717')
        ->assertCountTableRecords(0)
        ->assertSee(trans(SearchOutcome::TooBroad->heading()));
});

it('forgets a failed outcome when the next search succeeds', function (): void {
    config(['model-search.apply.max_ids' => 1]);
    $this->createOrder('OR6001', 'EXT-666001');
    $this->createOrder('OR6002', 'EXT-666002');
    $target = $this->createOrder('OR6003', 'EXT-999999');
    $this->indexAll();

    Livewire::test(ListOrders::class)
        ->searchTable('EXT-666')
        ->assertSee(trans(SearchOutcome::TooBroad->heading()))
        ->searchTable('EXT-999999')
        ->assertCanSeeTableRecords([$target])
        ->assertDontSee(trans(SearchOutcome::TooBroad->heading()));
});

it('respects only()', function (): void {
    $this->createOrder('OR7001', 'EXT-445566', ['customer_email' => 'zed@example.test']);
    $this->indexAll();

    config(['fixtures.order_only' => ['customer_email']]);
    Livewire::test(ListOrders::class)->searchTable('445566')->assertCountTableRecords(0);
    Livewire::test(ListOrders::class)->searchTable('zed@example.test')->assertCountTableRecords(1);
});

it('lets an orWhere in the table query never leak a row the phrase did not match', function (): void {
    $inside = $this->createOrder('OR8001', 'EXT-313131', ['state' => 'unshipped']);
    $otherMatch = $this->createOrder('OR8002', 'EXT-313132', ['state' => 'shipped']);
    $notMatching = $this->createOrder('OR8003', 'EXT-999000', ['state' => 'shipped']);
    $this->indexAll();

    // The table's own query carries an orWhere: the shipped order that does not match the phrase must stay out, however the branches combine.
    config(['fixtures.order_table' => static fn (Table $table): Table => $table->modifyQueryUsing(
        static fn ($query) => $query->where('state', 'shipped')->orWhere('state', 'unshipped'),
    )]);

    Livewire::test(ListOrders::class)
        ->searchTable('EXT-3131')
        ->assertCanSeeTableRecords([$inside, $otherMatch])
        ->assertCanNotSeeTableRecords([$notMatching]);
});

it('keeps a table query with a custom select searchable', function (): void {
    $target = $this->createOrder('OR8101', 'EXT-515151');
    $this->createOrder('OR8102', 'EXT-616161');
    $this->indexAll();

    config(['fixtures.order_table' => static fn (Table $table): Table => $table->modifyQueryUsing(
        static fn ($query) => $query->withCount('customer'),
    )]);

    Livewire::test(ListOrders::class)
        ->searchTable('EXT-515151')
        ->assertCanSeeTableRecords([$target])
        ->assertCountTableRecords(1);
});

it('keeps the empty state the host set before the model search', function (): void {
    $this->createOrder('OR8201', 'EXT-717171');
    $this->indexAll();

    config(['fixtures.order_table' => static fn (Table $table): Table => $table
        ->emptyStateHeading('Nothing shipped yet')
        ->emptyStateDescription('Orders appear here.')]);

    Livewire::test(ListOrders::class)
        ->searchTable('EXT-000000')
        ->assertCountTableRecords(0)
        ->assertSee('Nothing shipped yet')
        ->assertSee('Orders appear here.');
});

it('refuses a definition of another model at configuration', function (): void {
    config(['fixtures.order_table' => static fn (Table $table): Table => $table->modelSearch(CustomerSearch::class)]);

    try {
        Livewire::test(ListOrders::class);
    } catch (Throwable $e) {
        // The view wraps what the table configuration throws.
        while ($e->getPrevious() !== null) {
            $e = $e->getPrevious();
        }
    }

    expect($e ?? null)->toBeInstanceOf(InvalidArgumentException::class)->and($e->getMessage())->toContain('searches');
});

it('refuses non-string kinds', function (): void {
    expect(fn () => Table::make(Livewire::new(ListOrders::class))->modelSearch(OrderSearch::class, ['key', 5]))
        ->toThrow(InvalidArgumentException::class);
});

it('counts only the records matching the phrase', function (): void {
    $this->createOrder('OR9001', 'EXT-424242');
    $this->createOrder('OR9002', 'EXT-535353');
    $this->indexAll();

    $component = Livewire::test(ListOrders::class)->searchTable('424242');

    expect($component->instance()->getTableRecords()->total())->toBe(1);
});

it('starts using the index without a restart once the type becomes ready', function (): void {
    $target = $this->createOrder('OR9101', 'EXT-737373');
    $registry = resolve(ModelSearchRegistry::class);

    Livewire::test(ListOrders::class)->searchTable('737373')->assertCountTableRecords(0);

    new IndexState()->recordInstall($registry->for(Order::class));
    $this->indexAll();

    Livewire::test(ListOrders::class)->searchTable('737373')->assertCanSeeTableRecords([$target]);
});
