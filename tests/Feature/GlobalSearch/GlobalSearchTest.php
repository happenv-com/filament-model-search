<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Happenv\FilamentModelSearch\Concerns\InteractsWithModelSearch;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\CustomerResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\ProductResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\OrderSearch;
use Happenv\ModelSearch\Postgres\Indexing\IndexState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

covers(InteractsWithModelSearch::class);

beforeEach(function (): void {
    config([
        'filament-model-search.readiness_cache_seconds' => 0,
        'fixtures.customer_scope' => null,
        'fixtures.order_details_customer' => null,
    ]);
});

it('searches natively until the index is ready, through the index after', function (): void {
    $this->signIn(['orders.viewAny', 'orders.view']);
    $this->createOrder('OR1001', 'EXT-424242');

    // The title attribute is the only native one: the external number is found by the index alone.
    expect($this->globalTitles(OrderResource::class, 'OR1001'))->toBe(['OR1001'])
        ->and($this->globalTitles(OrderResource::class, 'EXT-424242'))->toBe([]);

    $this->indexAll();

    expect($this->globalTitles(OrderResource::class, 'EXT-424242'))->toBe(['OR1001']);
});

it('keeps the ranking of the index', function (): void {
    $this->signIn(['orders.viewAny', 'orders.view']);
    $this->createOrder('OR2001', 'EXT-515151-A');
    $this->createOrder('OR2002', 'EXT-515151');
    $this->indexAll();

    // The exact external number outranks the longer one that merely starts with it.
    expect($this->globalTitles(OrderResource::class, 'EXT-515151'))->toBe(['OR2002', 'OR2001']);
});

it('shows what matched and hides a value the viewer may not see', function (): void {
    $customer = $this->createCustomer('Alice Smith', 'alice@example.test');
    $this->indexAll();

    $this->signIn(['customers.viewAny', 'customers.update']);
    $hidden = CustomerResource::getGlobalSearchResults('alice@example.test');

    expect($hidden)->toHaveCount(1)
        ->and($hidden->first()->details)->toBe([trans('filament-model-search::global-search.matched_by') => 'Email'])
        ->and(json_encode($hidden->first()))->not->toContain('alice@example.test');

    // The same process, another viewer: the value follows the viewer, not the first search.
    $this->signIn(['customers.viewAny', 'customers.update', 'customers.revealEmail']);
    $shown = CustomerResource::getGlobalSearchResults('alice@example.test');

    expect($shown->first()->details)->toContain('alice@example.test')
        ->and($shown->first()->url)->toContain((string) $customer->id);
});

it('respects the resource global search query, orWhere included', function (): void {
    $this->signIn(['customers.viewAny', 'customers.update']);
    $this->createCustomer('Quinn Inside', 'quinn.inside@a.test');
    $this->createCustomer('Quinn Outside', 'quinn.outside@a.test');
    $this->createCustomer('Rita Unrelated', 'rita@a.test', 'P9', ['active' => false]);
    // Satisfies the first branch of the scope, does not match the phrase: it must not come back either.
    $this->createCustomer('Zoe Other', 'zoe@a.test');
    $this->indexAll();

    // The phone branch keeps Rita in scope, but she does not match the phrase: nothing leaks through the orWhere.
    config(['fixtures.customer_scope' => static fn (Builder $query): Builder => $query->where('email', 'not like', '%outside%')->orWhere('phone', 'P9')]);

    expect($this->globalTitles(CustomerResource::class, 'quinn'))->toBe(['Quinn Inside']);
});

it('a user without viewAny gets no results from the resource', function (): void {
    $this->createOrder('OR3001', 'EXT-636363');
    $this->indexAll();

    $this->signIn(['orders.view']);
    // Called directly, as a host or another provider may: the resource answers for the viewer on its own.
    expect(OrderResource::getGlobalSearchResults('EXT-636363'))->toBeEmpty();

    // The same process, another user: the first answer was not remembered.
    $this->signIn(['orders.viewAny', 'orders.view']);
    expect($this->globalTitles(OrderResource::class, 'EXT-636363'))->toBe(['OR3001']);

    $this->signIn(['orders.view']);
    expect(Filament::getGlobalSearchProvider()->getResults('EXT-636363')->getCategories())->toBeEmpty();
});

it('answers nothing, without an error, when the core refuses the search', function (): void {
    $this->signIn(['customers.viewAny', 'customers.update']);
    config(['model-search.rate_limit' => ['max_attempts' => 1, 'decay_seconds' => 60]]);
    $this->createCustomer('Uma Limited', 'uma.limited@a.test');
    $this->indexAll();

    expect($this->globalTitles(CustomerResource::class, 'uma.limited@a.test'))->toBe(['Uma Limited'])
        ->and(CustomerResource::getGlobalSearchResults('uma.limited@a.test'))->toBeEmpty();
});

it('skips a record without a page to open', function (): void {
    // May list products, but neither view nor edit one: there is no page to open.
    $this->signIn(['products.viewAny']);
    DB::table('products')->insert(['sku' => 'SKU-737373', 'created_at' => now(), 'updated_at' => now()]);
    $this->indexAll();

    expect($this->globalTitles(ProductResource::class, 'SKU-737373'))->toBe([]);
});

it('answers nothing for a timed out search', function (): void {
    $this->signIn(['customers.viewAny', 'customers.update']);
    config(['model-search.driver' => 'database', 'model-search.search.timeout_ms' => 100]);
    $this->createCustomer('Sam Slow', 'sam.slow@a.test');
    $this->indexAll();
    config(['fixtures.customer_scope' => static fn (Builder $query): Builder => $query->whereRaw('pg_sleep(0.5) IS NOT NULL')]);

    expect(CustomerResource::getGlobalSearchResults('sam.slow@a.test'))->toBeEmpty();
});

it('loads what the title and details need once for all results', function (): void {
    $this->signIn(['orders.viewAny', 'orders.view']);
    $customer = $this->createCustomer('Tess Loaded', 'tess@a.test');
    foreach (['OR4001', 'OR4002', 'OR4003'] as $signature) {
        $this->createOrder($signature, 'EXT-848484', ['customer_id' => $customer->id]);
    }
    $this->indexAll();
    config(['fixtures.order_details_customer' => true]);

    $loads = 0;
    DB::listen(static function ($query) use (&$loads): void {
        $loads += (int) str_contains($query->sql, 'from "customers"');
    });

    $results = OrderResource::getGlobalSearchResults('EXT-848484');

    expect($results)->toHaveCount(3)
        ->and($results->first()->details)->toBe(['Customer' => 'Tess Loaded', 'External Number' => 'EXT-848484'])
        ->and($loads)->toBeLessThanOrEqual(1);
});

it('does not repeat the title as what matched', function (): void {
    $this->signIn(['orders.viewAny', 'orders.view']);
    $this->createOrder('OR5001', 'EXT-212121');
    $this->indexAll();

    $titleMatch = OrderResource::getGlobalSearchResults('OR5001');

    expect($titleMatch)->toHaveCount(1)
        ->and($titleMatch->first()->details)->toBe([])
        ->and(OrderResource::getGlobalSearchResults('EXT-212121')->first()->details)->toBe(['External Number' => 'EXT-212121']);
});

it('shows found records whatever the flags of the answer say', function (): void {
    $this->signIn(['orders.viewAny', 'orders.view']);
    $this->createOrder('OR6001', 'EXT-323232');
    $this->indexAll();
    config(['filament-model-search.readiness_cache_seconds' => 60]);

    // Readiness is remembered as ready; then the type is marked for a rebuild, which the core answers with flagged results.
    expect($this->globalTitles(OrderResource::class, 'EXT-323232'))->toBe(['OR6001']);
    (new IndexState)->markForRebuild(['order']);

    expect(OrderSearch::query('EXT-323232')->within(OrderResource::getGlobalSearchEloquentQuery())->forUser(null)->get()->indexBuilding)->toBeTrue()
        ->and($this->globalTitles(OrderResource::class, 'EXT-323232'))->toBe(['OR6001']);
});
