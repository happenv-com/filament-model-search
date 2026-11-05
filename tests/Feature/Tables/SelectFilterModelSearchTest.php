<?php

declare(strict_types=1);

use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Happenv\FilamentModelSearch\Tables\SelectFilterModelSearch;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource\Pages\ListOrders;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;

covers(SelectFilterModelSearch::class);

beforeEach(function (): void {
    config([
        'filament-model-search.readiness_cache_seconds' => 0,
        'fixtures.order_only' => null,
        'fixtures.customer_scope' => null,
    ]);
    $this->signIn(['orders.viewAny', 'orders.view', 'customers.viewAny', 'customers.view']);
});

it('searches the filter options through the index once ready, native before', function (): void {
    $customer = $this->createCustomer('Alice Smith', 'alice@example.test');

    expect($this->customerFilterSelect()->getSearchResults('Alice'))->toBe([$customer->id => 'Alice Smith'])
        ->and($this->customerFilterSelect()->getSearchResults('alice@example.test'))->toBe([]);

    $this->indexAll();

    expect($this->customerFilterSelect()->getSearchResults('alice@example.test'))->toBe([$customer->id => 'Alice Smith']);
});

it('never offers a record outside the relationship query', function (): void {
    $inside = $this->createCustomer('Zed Inside', 'zed.inside@a.test', 'P1');
    $this->createCustomer('Zed ModifyOut', 'zed.modify@a.test', 'P3');
    $this->createCustomer('Zed ScopeOut', 'zed.scoped@a.test', 'P2');
    $this->indexAll();
    config(['fixtures.customer_scope' => static fn (Builder $query): Builder => $query->where('email', 'not like', '%scoped%')]);

    $select = $this->customerFilterSelect(static fn (SelectFilter $filter): SelectFilter => $filter
        ->relationship('customer', 'name', static fn (Builder $query): Builder => $query->where('phone', 'P1')->orWhere('phone', 'P2'))
        ->searchable()
        ->modelSearch());

    expect($select->getSearchResults('zed'))->toBe([$inside->id => 'Zed Inside']);
});

it('keeps the option for records without a relationship', function (): void {
    $this->createCustomer('Nobody Here', 'nobody@example.test');
    $this->indexAll();

    $select = $this->customerFilterSelect(static fn (SelectFilter $filter): SelectFilter => $filter
        ->relationship('customer', 'name', hasEmptyOption: true)
        ->emptyRelationshipOptionLabel('No customer')
        ->searchable()
        ->modelSearch());

    expect($select->getSearchResults('no customer'))->toHaveKey('__empty')
        ->and($select->getSearchResults('no customer'))->toContain('No customer')
        ->and($select->getSearchResults('nobody'))->not->toContain('No customer');
});

it('searches every stage with stages false', function (): void {
    $active = $this->createCustomer('Stage Active', 'stage.active@example.test');
    $inactive = $this->createCustomer('Stage Inactive', 'stage.inactive@example.test', null, ['active' => false]);
    $this->indexAll();

    expect(array_keys($this->customerFilterSelect()->getSearchResults('stage')))->toBe([$active->id]);

    $select = $this->customerFilterSelect(static fn (SelectFilter $filter): SelectFilter => $filter
        ->relationship('customer', 'name')->searchable()->modelSearch(stages: false));

    expect(array_keys($select->getSearchResults('stage')))->toEqualCanonicalizing([$active->id, $inactive->id]);
});

it('filters the table by a chosen value', function (): void {
    $customer = $this->createCustomer('Fil Chosen', 'fil@example.test');
    $mine = $this->createOrder('OR-FIL-1', null, ['customer_id' => $customer->id]);
    $other = $this->createOrder('OR-FIL-2');
    $this->indexAll();
    config(['fixtures.order_table' => static fn (Table $table): Table => $table->filters([
        SelectFilter::make('customer_id')->relationship('customer', 'name')->searchable()->modelSearch(),
    ])]);

    Livewire::test(ListOrders::class)
        ->filterTable('customer_id', $customer->id)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other]);
});

it('mirrors the limit of the relationship query onto the filter', function (): void {
    foreach (range(1, 3) as $n) {
        $this->createCustomer('Cap Person '.$n, 'cap'.$n.'@example.test');
    }
    $this->indexAll();
    $captured = null;

    $select = $this->customerFilterSelect(static function (SelectFilter $filter) use (&$captured): SelectFilter {
        $captured = $filter;

        return $filter->relationship('customer', 'name', static fn (Builder $query): Builder => $query->limit(2))->searchable()->modelSearch();
    });

    expect($select->getSearchResults('cap'))->toHaveCount(2)
        ->and($captured->getOptionsLimit())->toBe(2);
});

it('requires a relationship', function (): void {
    expect(static fn (): SelectFilter => SelectFilter::make('customer_id')->modelSearch())
        ->toThrow(LogicException::class, 'relationship()');
});

it('refuses non-string kinds', function (): void {
    expect(static fn (): SelectFilter => SelectFilter::make('c')->relationship('customer', 'name')->modelSearch(only: [1]))
        ->toThrow(InvalidArgumentException::class);
});
