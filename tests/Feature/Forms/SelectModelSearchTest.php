<?php

declare(strict_types=1);

use Filament\Forms\Components\Select;
use Happenv\FilamentModelSearch\Forms\SelectModelSearch;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\OrderSearch;
use Illuminate\Database\Eloquent\Builder;

covers(SelectModelSearch::class);

beforeEach(function (): void {
    config([
        'filament-model-search.readiness_cache_seconds' => 0,
        'fixtures.customer_select' => null,
        'fixtures.customer_scope' => null,
    ]);
    $this->signIn(['orders.viewAny', 'orders.view', 'customers.viewAny', 'customers.view']);
    config(['fixtures.customer_select' => static fn (Select $select): Select => $select->modelSearch()]);
});

it('searches options through the index once ready, native before', function (): void {
    $customer = $this->createCustomer('Alice Smith', 'alice@example.test');
    $other = $this->createCustomer('Bob Jones', 'bob@example.test');

    expect($this->customerSelect()->getSearchResults('Alice'))->toBe([$customer->id => 'Alice Smith'])
        ->and($this->customerSelect()->getSearchResults('alice@example.test'))->toBe([]);

    $this->indexAll();

    expect($this->customerSelect()->getSearchResults('alice@example.test'))->toBe([$customer->id => 'Alice Smith'])
        ->and($this->customerSelect()->getSearchResults('alice@example.test'))->not->toHaveKey($other->id);
});

it('keeps the labels of selected values', function (): void {
    $selected = $this->createCustomer('Carol Selected', 'carol@example.test');
    $this->indexAll();

    $select = $this->customerSelect()->state($selected->id);

    // The phrase does not reach the selected customer, yet its label is still there.
    expect($select->getSearchResults('nobody-matches-this'))->toBe([])
        ->and($select->getOptionLabel())->toBe('Carol Selected');
});

it('labels results with the record label callback of the relationship', function (): void {
    $customer = $this->createCustomer('Dave Label', 'dave@example.test');
    $this->indexAll();
    config(['fixtures.customer_select' => static fn (Select $select): Select => $select
        ->getOptionLabelFromRecordUsing(static fn ($record): string => strtoupper($record->name).' <'.$record->email.'>')
        ->modelSearch()]);

    expect($this->customerSelect()->getSearchResults('dave@example.test'))->toBe([$customer->id => 'DAVE LABEL <dave@example.test>']);
});

it('never offers a record outside the relationship query', function (): void {
    $inside = $this->createCustomer('Zed Inside', 'zed.inside@a.test', 'P1');
    $this->createCustomer('Zed ModifyOut', 'zed.modify@a.test', 'P3');
    // Matches the orWhere of the relationship query, but is outside the resource scope.
    $this->createCustomer('Zed ScopeOut', 'zed.scoped@a.test', 'P2');
    $this->indexAll();

    config([
        'fixtures.customer_scope' => static fn (Builder $query): Builder => $query->where('email', 'not like', '%scoped%'),
        'fixtures.customer_select' => static fn (Select $select): Select => $select
            ->relationship('customer', 'name', static fn (Builder $query): Builder => $query->where('phone', 'P1')->orWhere('phone', 'P2'))
            ->modelSearch(),
    ]);

    expect($this->customerSelect()->getSearchResults('zed'))->toBe([$inside->id => 'Zed Inside']);
});

it('offers nothing beyond the relationship query when the resource has no scope of its own', function (): void {
    $inside = $this->createCustomer('Yan Inside', 'yan.inside@a.test', 'P1');
    $this->createCustomer('Yan Outside', 'yan.outside@a.test', 'P2');
    $this->indexAll();

    config(['fixtures.customer_select' => static fn (Select $select): Select => $select
        ->relationship('customer', 'name', static fn (Builder $query): Builder => $query->where('phone', 'P1'))
        ->modelSearch()]);

    expect($this->customerSelect()->getSearchResults('yan'))->toBe([$inside->id => 'Yan Inside']);
});

it('keeps a relationship query with a custom select searchable', function (): void {
    $customer = $this->createCustomer('Ula Counted', 'ula@example.test');
    $this->indexAll();

    config(['fixtures.customer_select' => static fn (Select $select): Select => $select
        ->relationship('customer', 'name', static fn (Builder $query): Builder => $query->withCount('orders as order_total')->addSelect('customers.phone'))
        ->modelSearch()]);

    expect($this->customerSelect()->getSearchResults('ula@example.test'))->toBe([$customer->id => 'Ula Counted']);
});

it('ranks exact matches first', function (): void {
    $this->createCustomer('Aaa Prefix', null, '5551234');
    $exact = $this->createCustomer('Zzz Exact', null, '555123');
    $this->indexAll();

    $keys = array_keys($this->customerSelect()->getSearchResults('555123'));

    expect($keys)->toHaveCount(2)
        ->and($keys[0])->toBe($exact->id);
});

it('searches the first stage only by default and every stage with stages false', function (): void {
    $active = $this->createCustomer('Stage Active', 'stage.active@example.test');
    $inactive = $this->createCustomer('Stage Inactive', 'stage.inactive@example.test', null, ['active' => false]);
    $this->indexAll();

    expect(array_keys($this->customerSelect()->getSearchResults('stage')))->toBe([$active->id]);

    config(['fixtures.customer_select' => static fn (Select $select): Select => $select->modelSearch(stages: false)]);

    expect(array_keys($this->customerSelect()->getSearchResults('stage')))->toEqualCanonicalizing([$active->id, $inactive->id]);
});

it('limits the options to the select limit', function (): void {
    foreach (range(1, 5) as $n) {
        $this->createCustomer('Limit Person '.$n, 'limit'.$n.'@example.test');
    }
    $this->indexAll();
    config(['fixtures.customer_select' => static fn (Select $select): Select => $select->optionsLimit(3)->modelSearch()]);

    expect($this->customerSelect()->getSearchResults('limit'))->toHaveCount(3);
});

it('respects only()', function (): void {
    $byName = $this->createCustomer('Kind Name', 'other@example.test');
    $this->createCustomer('Plain', 'kind.mail@example.test');
    $this->indexAll();
    config(['fixtures.customer_select' => static fn (Select $select): Select => $select->modelSearch(only: ['name'])]);

    expect(array_keys($this->customerSelect()->getSearchResults('kind')))->toBe([$byName->id]);
});

it('offers an empty list when the core cannot answer', function (): void {
    $this->createCustomer('Broad One', 'broad1@example.test');
    $this->createCustomer('Broad Two', 'broad2@example.test');
    $this->indexAll();
    config(['model-search.search.timeout_ms' => 1, 'model-search.driver' => 'database']);
    config(['fixtures.customer_scope' => static fn (Builder $query): Builder => $query->whereRaw('pg_sleep(0.3) IS NOT NULL')]);

    expect($this->customerSelect()->getSearchResults('broad'))->toBe([]);
});

it('offers an empty list instead of an error when the core refuses the search', function (): void {
    config(['model-search.rate_limit' => ['max_attempts' => 1, 'decay_seconds' => 60]]);
    $customer = $this->createCustomer('Rate Limited', 'rate@example.test');
    $this->indexAll();
    $select = $this->customerSelect();

    expect($select->getSearchResults('rate@example.test'))->toBe([$customer->id => 'Rate Limited'])
        ->and($select->getSearchResults('rate@example.test'))->toBe([]);
});

it('starts using the index without a restart once the type becomes ready', function (): void {
    $customer = $this->createCustomer('Late Bloomer', 'late@example.test');
    $select = $this->customerSelect();

    expect($select->getSearchResults('late@example.test'))->toBe([]);

    $this->indexAll();

    expect($select->getSearchResults('late@example.test'))->toBe([$customer->id => 'Late Bloomer']);
});

it('requires a relationship', function (): void {
    expect(static fn (): Select => Select::make('customer_id')->modelSearch())
        ->toThrow(LogicException::class, 'relationship()');
});

it('refuses a definition of another model and non-string kinds', function (): void {
    config(['fixtures.customer_select' => static fn (Select $select): Select => $select->modelSearch(OrderSearch::class)]);

    expect(fn () => $this->customerSelect()->getSearchResults('x'))->toThrow(InvalidArgumentException::class);
    expect(static fn (): Select => Select::make('c')->relationship('customer', 'name')->modelSearch(only: [1]))
        ->toThrow(InvalidArgumentException::class);
});

it('treats an empty only() as no restriction', function (): void {
    $customer = $this->createCustomer('Free Range', 'free@example.test');
    $this->indexAll();
    config(['fixtures.customer_select' => static fn (Select $select): Select => $select->modelSearch(only: [])]);

    expect($this->customerSelect()->getSearchResults('free@example.test'))->toBe([$customer->id => 'Free Range']);
});
