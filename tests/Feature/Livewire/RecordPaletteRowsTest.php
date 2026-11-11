<?php

declare(strict_types=1);

use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Happenv\FilamentModelSearch\Livewire\RecordPalette;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Rows\ConfiguredOrderRow;
use Happenv\FilamentModelSearch\Tests\Fixtures\Rows\NotARow;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

covers(RecordPalette::class);

beforeEach(function (): void {
    config([
        'filament-model-search.readiness_cache_seconds' => 0,
        'fixtures.order_row' => null,
        'fixtures.order_row_class' => null,
        'fixtures.order_details_customer' => null,
        'fixtures.order_image' => null,
    ]);
});

/**
 * @param  array<string, mixed>  $slots  Slot name => its columns, or a closure returning them.
 */
$describeRows = static function (array $slots): void {
    config(['fixtures.order_row' => $slots]);
};

describe('a resource that describes its rows', function () use ($describeRows): void {
    it('renders every slot around the title, in its place', function () use ($describeRows): void {
        $describeRows([
            'leading' => [ImageColumn::make('lead')->getStateUsing(static fn (): string => 'https://images.test/lead.png')],
            'aboveTitle' => [TextColumn::make('above')->getStateUsing(static fn (): string => 'ABOVE-SLOT')],
            'besideTitle' => [TextColumn::make('state')->badge()],
            'belowTitle' => [Split::make([TextColumn::make('customer_name'), TextColumn::make('below')->getStateUsing(static fn (): string => 'BELOW-SLOT')])],
            'trailing' => [TextColumn::make('purchased_at')->date('Y-m-d')],
        ]);
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-10', '100001', ['customer_name' => 'Row Customer', 'purchased_at' => '2026-11-01 10:00:00']);
        $this->indexAll();

        $html = Livewire::test(RecordPalette::class)->set('phrase', 'OR-10')->html();

        // The order of the row: leading, above, title with beside, below, trailing.
        $positions = array_map(static fn (string $needle): int|false => strpos($html, $needle), [
            'https://images.test/lead.png', 'ABOVE-SLOT', 'OR-10', 'unshipped', 'Row Customer', 'BELOW-SLOT', '2026-11-01',
        ]);

        expect($positions)->each->not->toBeFalse()
            ->and($positions)->toBe(collect($positions)->sort()->values()->all())
            ->and(substr_count($html, 'role="option"'))->toBe(1);
    });

    it('shows the picture, the details and no slot when the row says nothing', function () use ($describeRows): void {
        config(['fixtures.order_details_customer' => true, 'fixtures.order_image' => 'https://images.test/order.png']);
        $describeRows([]);
        $this->signIn($this->allAbilities());
        $customer = $this->createCustomer('Fallback Customer');
        $this->createOrder('OR-11', '110001', ['customer_id' => $customer->id]);
        $this->indexAll();

        Livewire::test(RecordPalette::class)->set('phrase', 'OR-11')
            ->assertSeeHtml('src="https://images.test/order.png"')
            ->assertSeeHtml('<b>Customer:</b>')
            ->assertSee('Fallback Customer')
            ->assertDontSeeHtml('class="fi-record-palette-row-leading"')
            ->assertDontSeeHtml('class="fi-record-palette-row-trailing"')
            ->assertDontSeeHtml('row-slotted"');
    });

    it('replaces the details line with the slot and the image with the leading column', function () use ($describeRows): void {
        config(['fixtures.order_details_customer' => true, 'fixtures.order_image' => 'https://images.test/order.png']);
        $describeRows([
            'leading' => [ImageColumn::make('lead')->getStateUsing(static fn (): string => 'https://images.test/lead.png')],
            'belowTitle' => [TextColumn::make('below')->getStateUsing(static fn (): string => 'BELOW-ONLY')],
        ]);
        $this->signIn($this->allAbilities());
        $customer = $this->createCustomer('Replaced Customer');
        $this->createOrder('OR-12', '120001', ['customer_id' => $customer->id]);
        $this->indexAll();

        Livewire::test(RecordPalette::class)->set('phrase', 'OR-12')
            ->assertSeeHtml('https://images.test/lead.png')
            ->assertDontSeeHtml('https://images.test/order.png')
            ->assertSee('BELOW-ONLY')
            ->assertDontSeeHtml('<b>Customer:</b>');
    });

    it('does not put the rendered rows into what the browser receives', function () use ($describeRows): void {
        $describeRows(['belowTitle' => [TextColumn::make('below')->getStateUsing(static fn (): string => 'BELOW-SLOT')]]);
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-13', '130001');
        $this->indexAll();

        $component = Livewire::test(RecordPalette::class)->set('phrase', 'OR-13')->assertSee('BELOW-SLOT');

        expect(json_encode($component->get('found')))->not->toContain('BELOW-SLOT')
            ->and(json_encode($component->snapshot))->not->toContain('BELOW-SLOT');
    });

    it('loads what the columns read once for the group, on a search and on a request that did not search', function () use ($describeRows): void {
        $describeRows(['with' => ['customer'], 'belowTitle' => [TextColumn::make('customer.name')]]);
        $this->signIn($this->allAbilities());

        foreach (range(1, 5) as $i) {
            $this->createOrder('OR-2'.$i, '2000'.$i, ['customer_id' => $this->createCustomer('Loaded Customer '.$i)->id]);
        }

        $this->indexAll();

        // The query log is scoped to the callback: a listener could not be removed and would keep counting.
        $customerLoads = static function (Closure $callback): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            try {
                $callback();
            } finally {
                DB::disableQueryLog();
            }

            $loads = array_filter(DB::getQueryLog(), static fn (array $entry): bool => str_contains($entry['query'], 'from "customers"') && str_contains($entry['query'], '"customers"."id" in ('));
            DB::flushQueryLog();

            return count($loads);
        };

        $component = null;

        expect($customerLoads(function () use (&$component): void {
            $component = Livewire::test(RecordPalette::class)->set('phrase', 'OR-2');
        }))->toBe(1);

        $component->assertSee('Loaded Customer 1')->assertSee('Loaded Customer 5');

        // An action that re-renders without searching: the records are loaded again by their keys, in one query.
        expect($customerLoads(static function () use ($component): void {
            $component->call('submit');
        }))->toBe(1);
        $component->assertSee('Loaded Customer 3');
    });

    it('drops the match line when the row already shows its value', function () use ($describeRows): void {
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-14', '140001', ['customer_name' => 'Shown Customer']);
        $this->indexAll();

        $hint = static fn (): ?array => Livewire::test(RecordPalette::class)->set('phrase', 'Shown Customer')->get('found')['groups'][0]['items'][0]['hint'];

        expect($hint())->not->toBeNull();

        $describeRows(['belowTitle' => [TextColumn::make('customer_name')->prefix('Customer: ')]]);

        expect($hint())->toBeNull();
    });

    it('keeps the match line when the row shows something else', function () use ($describeRows): void {
        $describeRows(['belowTitle' => [TextColumn::make('state')]]);
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-15', '150001', ['customer_name' => 'Hidden Customer']);
        $this->indexAll();

        $item = Livewire::test(RecordPalette::class)->set('phrase', 'Hidden Customer')->get('found')['groups'][0]['items'][0];

        expect($item['hint'])->not->toBeNull();
    });

    it('accepts slots given as closures', function () use ($describeRows): void {
        $describeRows(['trailing' => static fn (): array => [TextColumn::make('t')->getStateUsing(static fn (): string => 'TRAILING-CLOSURE')]]);
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-16', '160001');
        $this->indexAll();

        Livewire::test(RecordPalette::class)->set('phrase', 'OR-16')->assertSee('TRAILING-CLOSURE');
    });

    it('does not render a row for a resource the user may no longer open', function () use ($describeRows): void {
        $describeRows(['belowTitle' => [TextColumn::make('below')->getStateUsing(static fn (): string => 'BELOW-SECRET')]]);
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-17', '170001');
        $this->indexAll();

        $component = Livewire::test(RecordPalette::class)->set('phrase', 'OR-17')->assertSee('BELOW-SECRET');

        $this->signIn(['customers.viewAny', 'customers.update']);
        $component->call('submit')->assertDontSee('BELOW-SECRET');
    });

    it('refuses a column that would be a link, a button or an input inside the option', function (Closure $column, string $why) use ($describeRows): void {
        $describeRows(['belowTitle' => [Split::make([$column()])]]);
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-18', '180001');
        $this->indexAll();

        expect(fn () => Livewire::test(RecordPalette::class)->set('phrase', 'OR-18'))
            ->toThrow(LogicException::class, OrderResource::class)
            ->and(fn () => Livewire::test(RecordPalette::class)->set('phrase', 'OR-18'))
            ->toThrow(LogicException::class, '[clicked]')
            ->and(fn () => Livewire::test(RecordPalette::class)->set('phrase', 'OR-18'))
            ->toThrow(LogicException::class, $why);
    })->with([
        'an input' => [static fn () => TextInputColumn::make('clicked'), 'is editable'],
        'a url' => [static fn () => TextColumn::make('clicked')->url('https://example.test'), 'opens a link'],
        'a url that depends on the state' => [static fn () => TextColumn::make('clicked')->url(static fn (string $state): string => 'https://example.test/'.$state), 'opens a link'],
        'an action' => [static fn () => TextColumn::make('clicked')->action(static fn (): null => null), 'calls an action'],
    ]);

    it('judges the columns of a layout against the record being rendered, not the previous one', function () use ($describeRows): void {
        // Only the second record's url resolves: the first one must not be the record the check looks at.
        $describeRows(['belowTitle' => [Split::make([TextColumn::make('signature')->url(static fn (Order $record): ?string => $record->signature === 'OR-42' ? 'https://example.test/' : null)])]]);
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-41', '410041');
        $this->createOrder('OR-42', '410042');
        $this->indexAll();

        expect(fn () => Livewire::test(RecordPalette::class)->set('phrase', 'OR-4'))
            ->toThrow(LogicException::class, '[signature]');
    });

    it('lets a column display its link when the click is disabled for it', function () use ($describeRows): void {
        $describeRows(['belowTitle' => [TextColumn::make('state')->url('https://example.test')->disabledClick()]]);
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-19', '190001');
        $this->indexAll();

        Livewire::test(RecordPalette::class)->set('phrase', 'OR-19')->assertSee('unshipped');
    });
});

describe('a resource that does not describe its rows', function (): void {
    it('shows the details line of its global search as before', function (): void {
        $this->signIn($this->allAbilities());
        $this->createCustomer('Plain Row', 'plain@a.test', '777001');
        $this->indexAll();

        $html = Livewire::test(RecordPalette::class)->set('phrase', '777001')->html();

        expect($html)->toContain('Plain Row')
            ->and($html)->not->toContain('row-slotted"')
            ->and($html)->not->toContain('class="fi-ta-');
    });
});

describe('the row class of a resource', function (): void {
    it('is refused when it is not a SearchResultRow, naming the resource and the class', function (): void {
        config(['fixtures.order_row_class' => NotARow::class]);
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-30', '300001');
        $this->indexAll();

        expect(fn () => Livewire::test(RecordPalette::class)->set('phrase', 'OR-30'))
            ->toThrow(LogicException::class, OrderResource::class)
            ->and(fn () => Livewire::test(RecordPalette::class)->set('phrase', 'OR-30'))
            ->toThrow(LogicException::class, NotARow::class);
    });

    it('is built by the container, so a host can inject into it', function (): void {
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-31', '310001');
        $this->indexAll();
        config(['fixtures.order_row_class' => ConfiguredOrderRow::class]);
        config(['fixtures.order_row' => ['belowTitle' => [TextColumn::make('c')->getStateUsing(static fn (): string => 'FROM-CONTAINER')]]]);
        app()->bind(ConfiguredOrderRow::class, static fn (): ConfiguredOrderRow => new ConfiguredOrderRow);

        Livewire::test(RecordPalette::class)->set('phrase', 'OR-31')->assertSee('FROM-CONTAINER');
    });
});

it('renders the row of a resource without a row class exactly as version 0.3.1 did', function (): void {
    config(['fixtures.order_details_customer' => true, 'fixtures.order_image' => 'https://images.test/order.png']);
    $this->signIn($this->allAbilities());
    $customer = $this->createCustomer('Fallback Customer');
    $this->createOrder('OR-11', '110001', ['customer_id' => $customer->id, 'customer_name' => 'Fallback Customer']);
    $this->indexAll();

    $html = Livewire::test(RecordPalette::class)->set('phrase', 'Fallback')->html();
    preg_match('/<a\b[^>]*role="option".*?<\/a>/s', $html, $row);

    $normalize = static fn (string $markup): string => trim(str_replace('> <', '><', (string) preg_replace(
        // Only how the link is opened changed since 0.3.1 (a plain click goes through the component, to be
        // noted in the history, instead of `wire:navigate`): the rest of the row is compared as it was.
        ['/<!--.*?-->/s', '/ x-on:click="if \(\$event\.button.*?\$wire\.open\([^)]*\) \}"/', '/ wire:navigate/', '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', '/\s+/'],
        ['', '', '', '{key}', ' '],
        $markup,
    )));

    expect($normalize($row[0] ?? ''))->toBe($normalize((string) file_get_contents(__DIR__.'/../../Fixtures/Markup/palette-row-0.3.1.html')));
});

it('renders a row with an icon and a match line exactly as version 0.3.1 did', function (): void {
    $this->signIn($this->allAbilities());
    $this->createCustomer('Icon Person', null, '5550001');
    $this->indexAll();

    $html = Livewire::test(RecordPalette::class)->set('phrase', '5550001')->html();
    preg_match('/<a\b[^>]*role="option".*?<\/a>/s', $html, $row);

    $normalize = static fn (string $markup): string => trim(str_replace('> <', '><', (string) preg_replace(
        ['/<!--.*?-->/s', '/ x-on:click="if \(\$event\.button.*?\$wire\.open\([^)]*\) \}"/', '/ wire:navigate/', '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', '/\/customers\/\d+/', '/customer-\d+/', '/\s+/'],
        ['', '', '', '{key}', '/customers/{id}', 'customer-{id}', ' '],
        $markup,
    )));

    expect($normalize($row[0] ?? ''))->toBe($normalize((string) file_get_contents(__DIR__.'/../../Fixtures/Markup/palette-row-icon-hint-0.3.1.html')));
});
