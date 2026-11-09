<?php

declare(strict_types=1);

use Happenv\FilamentModelSearch\Livewire\RecordPalette;
use Happenv\FilamentModelSearch\Support\SearchOutcome;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Customer;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\CustomerResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource;
use Happenv\FilamentModelSearch\Tests\Fixtures\Support\SetIcon;
use Happenv\ModelSearch\Postgres\Indexing\IndexState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Livewire;

covers(RecordPalette::class);

beforeEach(function (): void {
    config([
        'filament-model-search.readiness_cache_seconds' => 0,
        'fixtures.customer_scope' => null,
        'fixtures.order_scope' => null,
        'fixtures.customer_label' => null,
        'fixtures.order_details_customer' => null,
        'fixtures.order_icon' => null,
        'fixtures.order_image' => null,
        'fixtures.order_related_term' => null,
    ]);
});

/**
 * @return list<string> The titles of the items of a palette, group by group.
 */
$titles = static fn (array $found): array => array_merge(...array_map(
    static fn (array $group): array => array_column($group['items'], 'title'),
    $found['groups'] ?? [],
));

it('groups results per resource with exact hits first and at most per_group each', function () use ($titles): void {
    $this->signIn($this->allAbilities());
    foreach (range(1, 7) as $i) {
        $this->createOrder('OR-'.$i, '555123-'.$i);
    }
    $this->createCustomer('Exact Holder', null, '555123');
    $this->indexAll();

    $component = Livewire::test(RecordPalette::class)->set('phrase', '555123');
    $groups = $component->get('found')['groups'];

    // Orders come first in the navigation, but only the customer holds an exact hit.
    expect(array_column($groups, 'type'))->toBe(['customer', 'order'])
        ->and(array_column($groups, 'label'))->toBe([Str::ucfirst(CustomerResource::getPluralModelLabel()), Str::ucfirst(OrderResource::getPluralModelLabel())])
        ->and(count($groups[1]['items']))->toBe(5)
        ->and($titles($component->get('found')))->toContain('Exact Holder');
});

it('hides groups the user may not access, evaluated per search', function (): void {
    $this->signIn($this->allAbilities());
    $this->createOrder('OR-9', '999001');
    $this->createCustomer('Nine Holder', null, '999001');
    $this->indexAll();

    $component = Livewire::test(RecordPalette::class)->set('phrase', '999001');
    expect(array_column($component->get('found')['groups'], 'type'))->toBe(['order', 'customer']);

    // The same process, another user: the bindings were registered once and are asked again.
    $this->signIn(['customers.viewAny', 'customers.update']);
    $component->set('phrase', '999001 ');
    expect(array_column($component->get('found')['groups'], 'type'))->toBe(['customer']);

    $this->signIn([]);
    $component->set('phrase', '999001')->assertSee(trans(SearchOutcome::Unavailable->heading()));
});

it('offers search every stage when results came from a stage', function (): void {
    $this->signIn($this->allAbilities());
    $this->createOrder('OR-OPEN', '424242-A');
    $this->createOrder('OR-SHIPPED', '424242-B', ['state' => 'shipped']);
    $this->indexAll();

    $component = Livewire::test(RecordPalette::class)->set('phrase', '424242');

    $component->assertSee('OR-OPEN')->assertDontSee('OR-SHIPPED')->assertSee(trans('filament-model-search::record-palette.search_every_stage'));

    $component->call('searchEveryStage')
        ->assertSee('OR-SHIPPED')
        ->assertDontSee(trans('filament-model-search::record-palette.search_every_stage'));
});

it('offers similar only for letters, never runs it on its own', function (): void {
    $this->signIn(['customers.viewAny', 'customers.update']);
    $this->createCustomer('Jonathan Doe');
    $this->indexAll();
    $offer = trans('filament-model-search::record-palette.widen');

    $component = Livewire::test(RecordPalette::class)->set('phrase', 'Jonathon');
    $component->assertSee($offer)->assertDontSee('Jonathan Doe');

    foreach (['12345', 'jon@doe', "\u{1F600}\u{1F600}"] as $phrase) {
        $component->set('phrase', $phrase)->assertDontSee($offer);
    }
});

it('hides the similar offer when every group is already full', function (): void {
    $this->signIn(['customers.viewAny', 'customers.update']);
    config(['filament-model-search.palette.per_group' => 1]);
    $this->createCustomer('Jonathan Doe');
    $this->indexAll();

    Livewire::test(RecordPalette::class)->set('phrase', 'Jonathan')
        ->assertSee('Jonathan Doe')
        ->assertDontSee(trans('filament-model-search::record-palette.search_similar'));
});

it('similar finds a one-typo name', function () use ($titles): void {
    $this->signIn(['customers.viewAny', 'customers.update']);
    $this->createCustomer('Jonathan Doe');
    $this->indexAll();

    $component = Livewire::test(RecordPalette::class)->set('phrase', 'Jonathon');
    expect($titles($component->get('found')))->toBe([]);

    $component->call('searchSimilar')->assertSet('similar', true);
    expect($titles($component->get('found')))->toBe(['Jonathan Doe']);

    // A new phrase starts over: similar is never carried along.
    $component->set('phrase', 'Jonathan D')->assertSet('similar', false);
});

it('ignores a request for similar names it did not offer', function (): void {
    $this->signIn(['customers.viewAny', 'customers.update']);
    $this->createCustomer('Jonathan Doe', null, '12345');
    $this->indexAll();

    Livewire::test(RecordPalette::class)->set('phrase', '12345')->call('searchSimilar')->assertSet('similar', false)
        ->set('phrase', 'jon@doe')->call('searchSimilar')->assertSet('similar', false);
});

it('enter opens the single exact hit', function (): void {
    $this->signIn($this->allAbilities());
    $customer = $this->createCustomer('Only Exact', null, '777888');
    $this->createOrder('OR-777', '777888-X');
    $this->indexAll();

    Livewire::test(RecordPalette::class)->set('phrase', '777888')
        ->call('submit')
        ->assertRedirect(CustomerResource::getUrl('edit', ['record' => $customer]));
});

it('enter opens nothing when there is no single exact hit', function (): void {
    $this->signIn($this->allAbilities());
    $this->createCustomer('Twin One', null, '666555');
    $this->createCustomer('Twin Two', null, '666555');
    $this->indexAll();

    Livewire::test(RecordPalette::class)->set('phrase', '666555')->call('submit')->assertNoRedirect();
});

it('open refuses a record that is not in the current results', function (): void {
    $this->signIn($this->allAbilities());
    $shown = $this->createCustomer('Shown Person', null, '313131');
    $other = $this->createCustomer('Other Person', null, '979797');
    $this->indexAll();

    $component = Livewire::test(RecordPalette::class)->set('phrase', '313131');

    $component->call('open', 'customer', (string) $other->getKey())->assertNoRedirect()
        ->call('open', 'order', (string) $shown->getKey())->assertNoRedirect()
        ->call('open', 'customer', (string) $shown->getKey())
        ->assertRedirect(CustomerResource::getUrl('edit', ['record' => $shown]));
});

it('open checks access again when it is called', function (): void {
    $this->signIn($this->allAbilities());
    $customer = $this->createCustomer('Revoked Person', null, '212121');
    $this->indexAll();

    $component = Livewire::test(RecordPalette::class)->set('phrase', '212121');
    // May still edit customers, but no longer open the resource: the page would be allowed, the palette is not.
    $this->signIn(['customers.update', 'orders.viewAny']);

    $component->call('open', 'customer', (string) $customer->getKey())->assertNoRedirect();
});

it('open stays inside the scope of the resource, orWhere included', function (): void {
    $this->signIn($this->allAbilities());
    $inside = $this->createCustomer('Inside Person', 'inside@a.test', '414141');
    $outside = $this->createCustomer('Outside Person', 'outside@a.test', '414141');
    $this->indexAll();

    $component = Livewire::test(RecordPalette::class)->set('phrase', '414141');
    $this->assertCount(2, $component->get('found')['groups'][0]['items']);

    // The scope narrows after the search; its second branch still holds the other customer's phone,
    // so a plain whereKey would let the other customer back in.
    config(['fixtures.customer_scope' => static fn (Builder $query): Builder => $query->where('email', 'not like', '%outside%')->orWhere('phone', 'nothing')]);

    $component->call('open', 'customer', (string) $outside->getKey())->assertNoRedirect()
        ->call('open', 'customer', (string) $inside->getKey())->assertRedirect();
});

it('keeps what it found out of the reach of the client', function (): void {
    $this->signIn($this->allAbilities());
    $this->createCustomer('Locked Person', null, '151515');
    $this->indexAll();

    $component = Livewire::test(RecordPalette::class)->set('phrase', '151515');

    expect(fn () => $component->set('found', ['groups' => []]))->toThrow(Exception::class, 'Cannot update locked property: [found]');
});

it('shows the empty state', function (): void {
    $this->signIn($this->allAbilities());
    $this->indexAll();

    Livewire::test(RecordPalette::class)->set('phrase', 'nothingmatchesthis')
        ->assertSee(trans(SearchOutcome::Empty->heading()))
        ->assertSeeHtml('fi-empty-state');
});

it('asks for more characters before it searches', function (): void {
    $this->signIn($this->allAbilities());
    $this->createCustomer('One Letter', null, 'x');
    $this->indexAll();
    $component = Livewire::test(RecordPalette::class)->set('phrase', ' x ');

    // A single character is a valid exact key for the core; the palette still waits for two.
    expect($component->get('found')['groups'])->toBe([]);
    $component->assertSee(trans('filament-model-search::record-palette.hint'))->assertDontSee(trans(SearchOutcome::Empty->heading()));
});

it('shows the timed-out state when nothing came back in time', function (): void {
    $this->signIn(['customers.viewAny', 'customers.update']);
    config(['model-search.driver' => 'database', 'model-search.search.timeout_ms' => 100]);
    $this->createCustomer('Slow Person', null, '868686');
    $this->indexAll();
    config(['fixtures.customer_scope' => static fn (Builder $query): Builder => $query->whereRaw('pg_sleep(0.5) IS NOT NULL')]);

    Livewire::test(RecordPalette::class)->set('phrase', '868686')
        ->assertSee(trans(SearchOutcome::TimedOut->heading()))
        ->assertDontSee(trans(SearchOutcome::Empty->heading()));
});

it('puts a notice above results that came back while part of the search ran out of time', function (): void {
    // The core flags a partial timeout only in rounds the public API cannot stage, so the view is given the state directly.
    $group = ['type' => 'order', 'label' => 'Orders', 'icon' => null, 'items' => [['id' => '1', 'title' => 'OR-FAST', 'url' => '/o/1', 'exact' => false, 'hint' => null, 'details' => [], 'image' => null, 'relatedTerm' => null]]];
    $state = ['groups' => [$group], 'outcome' => 'results', 'offersEveryStage' => false, 'offersSimilar' => false, 'indexBuilding' => false, 'isShort' => false, 'debounce' => 250, 'similar' => false, 'welcome' => [], 'offersWiden' => false, 'emptyHeading' => '', 'emptyDescription' => '', 'enterLabel' => null, 'placeholder' => 'filament-model-search::record-palette.placeholder', 'phrase' => 'OR'];

    $withNotice = view('filament-model-search::livewire.record-palette', [...$state, 'timedOut' => true, 'outcome' => SearchOutcome::Results])->render();
    $without = view('filament-model-search::livewire.record-palette', [...$state, 'timedOut' => false, 'outcome' => SearchOutcome::Results])->render();

    expect($withNotice)->toContain('OR-FAST')->toContain(e(trans('filament-model-search::record-palette.timed_out_notice')))
        ->and($without)->toContain('OR-FAST')->not->toContain(e(trans('filament-model-search::record-palette.timed_out_notice')));
});

it('shows results and the index-building line while a rebuild is pending', function (): void {
    $this->signIn($this->allAbilities());
    $this->createOrder('OR-BUILD', '454545-A');
    $this->indexAll();
    (new IndexState)->markForRebuild(['order']);

    Livewire::test(RecordPalette::class)->set('phrase', '454545')
        ->assertSee('OR-BUILD')
        ->assertSee(trans('filament-model-search::record-palette.index_building_notice'));
});

it('never errors for a phrase that is only emoji or shaped like an identifier', function (string $phrase): void {
    $this->signIn($this->allAbilities());
    $this->createOrder('OR-1', 'EXT-1');
    $this->indexAll();

    Livewire::test(RecordPalette::class)->set('phrase', $phrase)->assertOk()->assertSee(trans(SearchOutcome::Empty->heading()));
})->with([
    'emoji' => ["\u{1F600}\u{1F600}\u{1F600}"],
    'an e-mail shape' => ['nobody@nowhere.test'],
    'digits' => ['00000000'],
    'separators' => ['---'],
]);

it('escapes the matched value and the titles', function (): void {
    $this->signIn(['customers.viewAny', 'customers.update', 'customers.revealEmail']);
    $this->createCustomer('<b>Bold</b> Person', '<i>x</i>@a.test');
    $this->indexAll();

    Livewire::test(RecordPalette::class)->set('phrase', '<i>x</i>@a.test')
        ->assertSeeHtml('&lt;b&gt;Bold&lt;/b&gt; Person')
        ->assertSeeHtml('&lt;i&gt;x&lt;/i&gt;@a.test')
        ->assertDontSeeHtml('<i>x</i>');
});

it('tells what matched and hides a value the viewer may not see', function (): void {
    $this->createCustomer('Alice Smith', 'alice@hidden.test');
    $this->indexAll();

    $this->signIn(['customers.viewAny', 'customers.update']);
    Livewire::test(RecordPalette::class)->set('phrase', 'alice@hidden.test')
        ->assertSee('Alice Smith')
        ->assertSee(trans('filament-model-search::match-line.matched_by'))
        ->assertDontSee('alice@hidden.test', false);

    $this->signIn(['customers.viewAny', 'customers.update', 'customers.revealEmail']);
    Livewire::test(RecordPalette::class)->set('phrase', 'alice@hidden.test')->assertSee('alice@hidden.test');
});

it('follows a parent only through the scope of its own resource', function (): void {
    $customer = $this->createCustomer('Parent Person', 'parent.only@a.test');
    $this->createOrder('OR-CHILD', null, ['customer_id' => $customer->getKey()]);
    $this->indexAll();

    // May list orders but not customers: the parent has no scope in the palette, so nothing is found through it.
    $this->signIn(['orders.viewAny', 'orders.view']);
    Livewire::test(RecordPalette::class)->set('phrase', 'parent.only@a.test')->assertDontSee('OR-CHILD');

    // With the customers listed too, the order is found through its customer and says so.
    $this->signIn($this->allAbilities());
    $found = Livewire::test(RecordPalette::class)->set('phrase', 'parent.only@a.test')->get('found');
    $orderGroup = collect($found['groups'])->firstWhere('type', 'order');

    expect($orderGroup['items'][0]['title'])->toBe('OR-CHILD')
        ->and($orderGroup['items'][0]['hint'])->toBe(['label' => trans('filament-model-search::match-line.matched_by'), 'value' => 'Customer']);
});

it('keeps the phrase out of query listeners and logs', function (): void {
    $this->signIn($this->allAbilities());
    $this->createCustomer('Secret Person', null, '909090');
    $this->indexAll();
    $phrase = 'zqxwvsecretphrase';

    $seen = [];
    DB::listen(static function ($query) use (&$seen): void {
        $seen[] = $query->sql.json_encode($query->bindings);
    });
    Event::listen(MessageLogged::class, static function (MessageLogged $log) use (&$seen): void {
        $seen[] = $log->message.json_encode($log->context);
    });

    Livewire::test(RecordPalette::class)->set('phrase', $phrase)->call('searchEveryStage')->call('searchSimilar');

    expect($seen)->not->toBe([])
        ->and(implode("\n", $seen))->not->toContain($phrase);
});

it('binds Enter to the search input only, so links and buttons keep their own', function (): void {
    $this->signIn($this->allAbilities());

    $html = Livewire::test(RecordPalette::class)->html();

    expect(substr_count($html, 'keydown.enter'))->toBe(1)
        ->and($html)->toMatch('/<input[^>]*x-on:keydown\.enter="submitOnEnter\(\$event\)"/s')
        ->and($html)->toContain('event.isComposing')
        ->and($html)->not->toContain('<x-')
        ->and($html)->toContain(e(trans('filament-model-search::record-palette.placeholder')));
});

it('rejects a client that sets the flags only the server may set', function (string $flag): void {
    $this->signIn($this->allAbilities());
    $component = Livewire::test(RecordPalette::class)->set('phrase', 'abc');

    expect(fn () => $component->set($flag, true))->toThrow(Exception::class, "Cannot update locked property: [{$flag}]");
})->with(['similar', 'everyStage']);

it('starts the stages over with a new phrase', function (): void {
    $this->signIn($this->allAbilities());
    $this->createOrder('OR-OPEN', '525252-A');
    $this->createOrder('OR-SHIPPED', '525252-B', ['state' => 'shipped']);
    $this->indexAll();

    $component = Livewire::test(RecordPalette::class)->set('phrase', '525252')->call('searchEveryStage')->assertSet('everyStage', true);

    // The answer "this came from a stage" belongs to the phrase: a new one is searched in the stage again.
    $component->set('phrase', '525252-')->assertSet('everyStage', false)
        ->assertSee(trans('filament-model-search::record-palette.search_every_stage'))
        ->assertSee(trans('filament-model-search::record-palette.stage_note'));
});

it('names the parent through the label of its resource, localised', function (): void {
    config(['fixtures.customer_label' => 'Klient']);
    $customer = $this->createCustomer('Hidden Parent', 'hidden.parent@a.test');
    $this->createOrder('OR-VIA', null, ['customer_id' => $customer->getKey()]);
    $this->indexAll();

    $this->signIn($this->allAbilities());
    $found = Livewire::test(RecordPalette::class)->set('phrase', 'hidden.parent@a.test')->get('found');
    $order = collect($found['groups'])->firstWhere('type', 'order')['items'][0];

    expect($order['hint'])->toBe(['label' => trans('filament-model-search::match-line.matched_by'), 'value' => 'Klient']);
});

it('shows the rate-limited state', function (): void {
    $this->signIn($this->allAbilities());
    $this->createOrder('OR-RATE', '616161-A');
    $this->indexAll();
    config(['model-search.rate_limit.max_attempts' => 1]);

    Livewire::test(RecordPalette::class)->set('phrase', '616161')->set('phrase', '616162')
        ->assertSee(trans(SearchOutcome::RateLimited->heading()))
        ->assertDontSee(trans(SearchOutcome::Empty->heading()));
});

it('shows the unavailable state when the server could log the phrase', function (): void {
    $this->signIn($this->allAbilities());
    $this->createOrder('OR-PRIV', '717171-A');
    $this->indexAll();
    DB::statement("SET log_statement = 'all'");

    Livewire::test(RecordPalette::class)->set('phrase', '717171')
        ->assertSee(trans(SearchOutcome::Unavailable->heading()))
        ->assertDontSee(trans(SearchOutcome::Empty->heading()));
});

it('names the search field for screen readers', function (): void {
    $this->signIn($this->allAbilities());

    Livewire::test(RecordPalette::class)
        ->assertSeeHtml('aria-label="'.e(trans('filament-model-search::record-palette.search_label')).'"');
});

it('renders the navigation icon of a hit group, whether it is a string or a scalable icon enum', function (mixed $icon): void {
    config(['fixtures.order_icon' => $icon]);
    $this->signIn($this->allAbilities());
    $this->createOrder('OR-ICON', '606060');
    $this->indexAll();

    Livewire::test(RecordPalette::class)->set('phrase', '606060')
        ->assertOk()
        ->assertSee('OR-ICON')
        ->assertSeeHtml('<svg');
})->with([
    'string' => 'heroicon-o-users',
    'scalable icon enum' => SetIcon::Users,
]);

describe('a result row', function (): void {
    it('carries the details of the global search as its second line, without a query per row', function (): void {
        config(['fixtures.order_details_customer' => true]);
        $this->signIn($this->allAbilities());
        $customer = $this->createCustomer('Second Line');
        $this->createOrder('OR-77', '770001', ['customer_id' => $customer->id]);
        $this->createOrder('OR-78', '770002', ['customer_id' => $customer->id]);
        $this->indexAll();

        $component = Livewire::test(RecordPalette::class)->set('phrase', 'OR-7');

        expect($component->get('found')['groups'][0]['items'][0]['details'])->toBe([['label' => 'Customer', 'value' => 'Second Line']]);
        $component->assertSeeHtml('<b>Customer:</b>')->assertSee('Second Line');
    });

    it('does not repeat in the match line a value the second line already shows', function (): void {
        config(['fixtures.order_details_customer' => true]);
        $this->signIn($this->allAbilities());
        $customer = $this->createCustomer('Unique Holder', 'unique.holder@a.test');
        $this->createOrder('OR-71', '710001', ['customer_id' => $customer->id, 'customer_name' => 'Unique Holder']);
        $this->indexAll();

        $groups = Livewire::test(RecordPalette::class)->set('phrase', 'Unique Holder')->get('found')['groups'];
        $orders = array_values(array_filter($groups, static fn (array $group): bool => $group['type'] === 'order'))[0]['items'];

        expect($orders[0]['details'])->toBe([['label' => 'Customer', 'value' => 'Unique Holder']])
            ->and($orders[0]['hint'])->toBeNull();
    });

    it('shows the picture instead of the icon when the resource gives one', function (): void {
        config(['fixtures.order_image' => 'https://images.test/order.png']);
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-79', '790001');
        $this->indexAll();

        Livewire::test(RecordPalette::class)->set('phrase', 'OR-79')->assertSeeHtml('src="https://images.test/order.png"');
    });

    it('has no related search when the resource does not define it or names no term', function (): void {
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-80', '800001');
        $this->createCustomer('Plain Person', null, '800001');
        $this->indexAll();

        $component = Livewire::test(RecordPalette::class)->set('phrase', '800001');

        $component->assertDontSeeHtml('wire:click="searchRelated');
        expect(array_column(array_merge(...array_column($component->get('found')['groups'], 'items')), 'relatedTerm'))->toBe([null, null]);
    });

    it('searches for the term the record names, starting over', function (): void {
        config(['fixtures.order_related_term' => true]);
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-81', '810001');
        $this->createOrder('OR-82', '820001');
        $this->indexAll();

        $component = Livewire::test(RecordPalette::class)->set('phrase', 'OR-8')->assertSeeHtml('wire:click="searchRelated');
        $id = (string) Order::query()->where('signature', 'OR-82')->value('id');

        $component->call('searchRelated', 'order', $id)
            ->assertSet('phrase', 'OR-82')
            ->assertSet('similar', false)
            ->assertSet('everyStage', false)
            ->assertSee('OR-82')
            ->assertDontSee('OR-81');
    });

    it('searches only for a term a found record offered', function (): void {
        config(['fixtures.order_related_term' => true]);
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-83', '830001');
        $this->indexAll();

        Livewire::test(RecordPalette::class)->set('phrase', 'OR-83')->call('searchRelated', 'order', '999999')->assertSet('phrase', 'OR-83');
    });
});

describe('before anything is typed', function (): void {
    it('lists what can be searched, only what the user may open', function (): void {
        $this->signIn(['customers.viewAny', 'customers.update']);

        $component = Livewire::test(RecordPalette::class);

        $component->assertSee(trans('filament-model-search::record-palette.welcome_heading'))
            ->assertSee(Str::ucfirst(CustomerResource::getPluralModelLabel()))
            ->assertDontSee(Str::ucfirst(OrderResource::getPluralModelLabel()))
            ->assertSeeHtml('href="'.e(CustomerResource::getUrl('index')).'"');
    });

    it('hides the list once results are shown', function (): void {
        $this->signIn($this->allAbilities());
        $this->createOrder('OR-90', '900001');
        $this->indexAll();

        Livewire::test(RecordPalette::class)->set('phrase', 'OR-90')->assertDontSee(trans('filament-model-search::record-palette.welcome_heading'));
    });
});

describe('when nothing is found', function (): void {
    $widen = static fn (): string => trans('filament-model-search::record-palette.widen');

    it('offers to widen the search for a name, and Enter does it', function () use ($widen): void {
        $this->signIn(['customers.viewAny', 'customers.update']);
        $this->createCustomer('Jonathan Doe');
        $this->indexAll();

        $component = Livewire::test(RecordPalette::class)->set('phrase', 'Jonathon')->assertSee($widen())->assertDontSee('Jonathan Doe');

        $component->call('submit')->assertSet('similar', true)->assertSee('Jonathan Doe')->assertDontSee($widen());
    });

    it('does not offer it for an identifier, where typos are not tolerated', function () use ($widen): void {
        $this->signIn($this->allAbilities());
        $this->indexAll();

        Livewire::test(RecordPalette::class)->set('phrase', '98765')
            ->assertDontSee($widen())
            ->assertSee(trans('filament-model-search::record-palette.identifier_empty_description'));
    });

    it('says so when even similar names found nothing, and offers nothing more', function () use ($widen): void {
        $this->signIn(['customers.viewAny', 'customers.update']);
        $this->indexAll();

        Livewire::test(RecordPalette::class)->set('phrase', 'Qwertyuiop')->call('searchSimilar')
            ->assertSee(trans('filament-model-search::record-palette.similar_empty_heading'))
            ->assertDontSee($widen());
    });

    it('does not offer every stage: a type with no hit in its first stage has searched them all', function (): void {
        $this->signIn($this->allAbilities());
        $this->indexAll();

        Livewire::test(RecordPalette::class)->set('phrase', 'Nobody Here')->assertDontSee(trans('filament-model-search::record-palette.search_every_stage'));
    });
});

it('does not offer every stage when the results already came from all of them', function (): void {
    $this->signIn($this->allAbilities());
    $this->createOrder('OR-SHIPPED-ONLY', '424299-A', ['state' => 'shipped']);
    $this->indexAll();

    Livewire::test(RecordPalette::class)->set('phrase', '424299')
        ->assertSee('OR-SHIPPED-ONLY')
        ->assertDontSee(trans('filament-model-search::record-palette.search_every_stage'));
});
