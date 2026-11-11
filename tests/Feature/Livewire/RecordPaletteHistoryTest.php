<?php

declare(strict_types=1);

use Filament\Tables\Columns\TextColumn;
use Happenv\FilamentModelSearch\History\PaletteHistory;
use Happenv\FilamentModelSearch\Livewire\RecordPalette;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

covers(RecordPalette::class);

beforeEach(function (): void {
    config([
        'filament-model-search.readiness_cache_seconds' => 0,
        'filament-model-search.palette.history.enabled' => true,
        'filament-model-search.palette.history.limit' => 5,
        'filament-model-search.palette.history.keep' => 20,
        'fixtures.order_row' => null,
        'fixtures.customer_scope' => null,
    ]);
});

/**
 * Opens a record through the palette the way a click does.
 */
$openThrough = static function (object $test, string $phrase, string $type, string $id): void {
    Livewire::test(RecordPalette::class)->set('phrase', $phrase)->call('open', $type, $id);
};

it('notes a record when it is opened, and shows it while the field is empty', function () use ($openThrough): void {
    $this->signIn($this->allAbilities());
    $order = $this->createOrder('OR-H1', '710001');
    $this->indexAll();

    $openThrough($this, '710001', 'order', (string) $order->getKey());

    $component = Livewire::test(RecordPalette::class)
        ->assertSee(trans('filament-model-search::record-palette.recently_opened'))
        ->assertSee('OR-H1')
        ->assertSee(trans('filament-model-search::record-palette.clear_history'))
        ->assertSeeHtml('id="filament-model-search-palette-option-order-'.$order->getKey().'"');

    // A history row is not a result: nothing is highlighted on its own when the palette opens.
    expect($component->html())->not->toMatch('/<a\b[^<]*\sdata-palette-result\b/');
});

it('shows nothing, not even a heading, when nothing was opened', function (): void {
    $this->signIn($this->allAbilities());

    Livewire::test(RecordPalette::class)
        ->assertDontSee(trans('filament-model-search::record-palette.recently_opened'))
        ->assertDontSee(trans('filament-model-search::record-palette.clear_history'));
});

it('hides the history once a phrase is typed', function () use ($openThrough): void {
    $this->signIn($this->allAbilities());
    $order = $this->createOrder('OR-H2', '720001');
    $this->indexAll();
    $openThrough($this, '720001', 'order', (string) $order->getKey());

    Livewire::test(RecordPalette::class)->set('phrase', 'zz')->assertDontSee(trans('filament-model-search::record-palette.recently_opened'));
});

it('lists the newest first, mixed types, up to the limit', function () use ($openThrough): void {
    config(['filament-model-search.palette.history.limit' => 3]);
    $this->signIn($this->allAbilities());
    $this->indexAll();
    $this->travelTo(now()->subMinutes(10));

    foreach (['A', 'B', 'C', 'D'] as $i => $letter) {
        $order = $this->createOrder('OR-L'.$letter, '73000'.$i);
        $this->indexAll();
        $openThrough($this, '73000'.$i, 'order', (string) $order->getKey());
        $this->travel(1)->minutes();
    }

    $customer = $this->createCustomer('Mixed Customer', null, '730099');
    $this->indexAll();
    $openThrough($this, '730099', 'customer', (string) $customer->getKey());

    $html = Livewire::test(RecordPalette::class)->html();

    expect($html)->toContain('Mixed Customer')->toContain('OR-LD')->toContain('OR-LC')
        ->not->toContain('OR-LB')
        ->and(strpos($html, 'Mixed Customer'))->toBeLessThan(strpos($html, 'OR-LD'))
        ->and(strpos($html, 'OR-LD'))->toBeLessThan(strpos($html, 'OR-LC'));
});

it('moves a record that is opened again to the top', function () use ($openThrough): void {
    $this->signIn($this->allAbilities());
    $this->travelTo(now()->subMinutes(5));
    $first = $this->createOrder('OR-M1', '740001');
    $second = $this->createOrder('OR-M2', '740002');
    $this->indexAll();

    $openThrough($this, '740001', 'order', (string) $first->getKey());
    $this->travel(1)->minutes();
    $openThrough($this, '740002', 'order', (string) $second->getKey());
    $this->travel(1)->minutes();
    $openThrough($this, '740001', 'order', (string) $first->getKey());

    $html = Livewire::test(RecordPalette::class)->html();

    expect(strpos($html, 'OR-M1'))->toBeLessThan(strpos($html, 'OR-M2'))
        ->and(DB::table('model_search_palette_history')->count())->toBe(2);
});

it('keeps the history of each user to that user', function () use ($openThrough): void {
    $this->signIn($this->allAbilities());
    $order = $this->createOrder('OR-U1', '750001');
    $this->indexAll();
    $openThrough($this, '750001', 'order', (string) $order->getKey());

    $this->signIn($this->allAbilities());

    Livewire::test(RecordPalette::class)->assertDontSee('OR-U1')->assertDontSee(trans('filament-model-search::record-palette.recently_opened'));
});

it('skips a record that was deleted and one the user may no longer open', function () use ($openThrough): void {
    $this->signIn($this->allAbilities());
    $gone = $this->createOrder('OR-GONE', '760001');
    $customer = $this->createCustomer('Still Allowed', null, '760002');
    $this->indexAll();
    $openThrough($this, '760001', 'order', (string) $gone->getKey());
    $openThrough($this, '760002', 'customer', (string) $customer->getKey());

    $gone->delete();
    Livewire::test(RecordPalette::class)->assertDontSee('OR-GONE')->assertSee('Still Allowed');

    $other = $this->createOrder('OR-LOCKED', '760003');
    $this->indexAll();
    $openThrough($this, '760003', 'order', (string) $other->getKey());

    $this->signIn(['customers.viewAny', 'customers.update']);
    DB::table('model_search_palette_history')->update(['user_id' => (string) auth()->id()]);

    Livewire::test(RecordPalette::class)->assertDontSee('OR-LOCKED')->assertSee('Still Allowed');
});

it('opens a history row', function () use ($openThrough): void {
    $this->signIn($this->allAbilities());
    $order = $this->createOrder('OR-O1', '770001');
    $this->indexAll();
    $openThrough($this, '770001', 'order', (string) $order->getKey());

    Livewire::test(RecordPalette::class)->call('open', 'order', (string) $order->getKey())
        ->assertRedirect(OrderResource::getUrl('view', ['record' => $order]));
});

it('skips a record that left the scope of its resource while the resource stays open to the user', function () use ($openThrough): void {
    $this->signIn($this->allAbilities());
    $kept = $this->createCustomer('Kept In Scope', null, '810001');
    $left = $this->createCustomer('Left The Scope', null, '810002');
    $this->indexAll();
    $openThrough($this, '810001', 'customer', (string) $kept->getKey());
    $openThrough($this, '810002', 'customer', (string) $left->getKey());

    config(['fixtures.customer_scope' => static fn (Builder $query): Builder => $query->where('name', '!=', 'Left The Scope')]);

    Livewire::test(RecordPalette::class)->assertSee('Kept In Scope')->assertDontSee('Left The Scope');
});

it('forgets everything with Clear history', function () use ($openThrough): void {
    $this->signIn($this->allAbilities());
    $order = $this->createOrder('OR-C1', '780001');
    $this->indexAll();
    $openThrough($this, '780001', 'order', (string) $order->getKey());

    Livewire::test(RecordPalette::class)->assertSee('OR-C1')
        ->call('clearHistory')
        ->assertDontSee('OR-C1')
        ->assertDontSee(trans('filament-model-search::record-palette.recently_opened'))
        ->assertDontSee('OR-C1');

    expect(resolve(PaletteHistory::class)->recent(auth()->user()))->toBe([]);
});

it('records and shows nothing when history is disabled', function () use ($openThrough): void {
    config(['filament-model-search.palette.history.enabled' => false]);
    $this->signIn($this->allAbilities());
    $order = $this->createOrder('OR-D1', '790001');
    $this->indexAll();
    $openThrough($this, '790001', 'order', (string) $order->getKey());

    expect(DB::table('model_search_palette_history')->count())->toBe(0);
    Livewire::test(RecordPalette::class)->assertDontSee(trans('filament-model-search::record-palette.recently_opened'));
});

it('shows a history row with the columns its resource describes, and no match line', function (): void {
    config(['fixtures.order_row' => ['belowTitle' => [TextColumn::make('below')->getStateUsing(static fn (): string => 'BELOW-HISTORY')]]]);
    $this->signIn($this->allAbilities());
    $order = $this->createOrder('OR-R1', '800001');
    $this->indexAll();
    resolve(PaletteHistory::class)->record(auth()->user(), 'order', (string) $order->getKey());

    Livewire::test(RecordPalette::class)->assertSee('BELOW-HISTORY')->assertDontSeeHtml('class="fi-record-palette-row-hint"');
});
