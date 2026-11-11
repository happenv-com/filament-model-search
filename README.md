# Filament Model Search

Filament integration for [`happenv-com/laravel-model-search`](https://github.com/happenv-com/laravel-model-search): a record palette, table search, select search and global search, all answered by one record index.

The core package owns the index, the ranking, the privacy of the phrase and the rate limit. This package only connects it to Filament surfaces and keeps Filament's authorization in charge of what a user may see.

- [Requirements](#requirements)
- [Installation](#installation)
- [Binding resources](#binding-resources)
- [What happens until the index is ready](#what-happens-until-the-index-is-ready)
- [Tables](#tables)
- [Selects](#selects)
- [Global search](#global-search)
- [Record palette](#record-palette)
- [Configuration](#configuration)
- [Coming from another palette](#coming-from-another-palette)
- [Static analysis](#static-analysis)
- [Translations](#translations)
- [Testing](#testing)
- [AI assistants (Laravel Boost)](#ai-assistants-laravel-boost)
- [Licence](#licence)

## Requirements

- PHP 8.4+
- Laravel 13 (`illuminate/*` ^13.0)
- Filament ^5.11 and Livewire ^4.4
- [`happenv-com/laravel-model-search`](https://github.com/happenv-com/laravel-model-search) ^0.1, which brings its own requirements:
  - **PostgreSQL 18+** with the **`pg_trgm`** extension (`CREATE EXTENSION pg_trgm`). Under the core's `database` driver (small tables, local development) there is no index and no extension is needed.
  - a role that owns the watched tables for `php artisan model-search:sync` (`migrate` runs it), and a direct, not transaction-pooled, connection for it.

Read the core's README before you define searches: definitions, fields, stages, reveal rules, `version()` bumps and deployment are described there and not repeated here.

## Installation

```bash
composer require happenv-com/filament-model-search
```

The service provider is discovered automatically. Register the plugin on every panel that should offer the search:

```php
use Happenv\FilamentModelSearch\FilamentModelSearchPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            FilamentModelSearchPlugin::make()
                ->resources([
                    OrderResource::class,
                    CustomerResource::class,
                ])
                ->palette(), // default: true
        );
}
```

`resources()` lists the resources that take part in the palette and in global search. The plugin only stores class names; the bindings are made when the panel registers, and nothing about the user or the request is captured then (a panel is registered once per worker under Octane).

## Binding resources

A resource shows the records of one model, and the model has one search definition from the core. There are two ways to tell the package which.

**With the trait**, on the resource itself:

```php
use Happenv\FilamentModelSearch\Concerns\InteractsWithModelSearch;

final class OrderResource extends Resource
{
    use InteractsWithModelSearch;

    public static function modelSearch(): string
    {
        return OrderSearch::class;
    }
}
```

The class must also be listed in `FilamentModelSearchPlugin::make()->resources([...])`. A listed class that does not use the trait is refused with a `LogicException` when the panel registers. The trait overrides `getGlobalSearchResults()`, so a resource that declares its own `getGlobalSearchResults()` is refused at registration too: the trait's method would never run and the resource would silently keep searching natively. Drop your override to opt in.

**Explicitly**, for a resource you cannot or do not want to touch (a resource of another package, for example):

```php
use Happenv\FilamentModelSearch\Facades\FilamentModelSearch;

FilamentModelSearch::resource(OrderSearch::class, OrderResource::class);
```

Call it from a service provider's `boot()`. A binding made this way belongs to every panel; the plugin's bindings belong to the panel that listed the resource. Reads resolve against the current panel, and a resource counts only on the panels where it is bound.

Binding the same pair twice changes nothing. Binding a model to a second, different resource on an overlapping panel, or a definition to a resource that shows another model, throws a `LogicException`.

The core is not asked whether it knows the definition when you bind: bindings are made while panels register, so an unknown definition fails on the first search instead.

**Access is never decided at registration.** The palette and global search ask `canAccess() && canViewAny()` of the resource for the current user on every call, so with one resource registered once per worker, a user who may not open it never gets it searched, and another user in the same process does. Tables and selects follow native Filament: the page or the form that shows them is the gate.

## What happens until the index is ready

The index is built by the core in the background (`model-search:sync`, run by `migrate`). Until `ModelSearches::isReady(<model>)` is `true` for a model:

- **tables, selects and global search keep Filament's native behaviour** for that model: the native `ILIKE` search, the native select search, the resource's own global search. Nothing is replaced, and nothing is half-served;
- **the palette always asks the core**, which answers from the source tables when the definition's `fallback()` allows it and flags the answer as `indexBuilding`; the palette tells the user results may be incomplete.

Readiness is asked again on every search, with the answer remembered for `readiness_cache_seconds` (default 60), so a table or select that was native before the index was ready switches to the core's search by itself, without a restart.

## Tables

```php
use Filament\Tables\Table;

public static function table(Table $table): Table
{
    return $table
        ->query(fn () => Order::query()->with('customer'))
        ->columns([...])
        ->emptyStateHeading('No orders yet')
        ->emptyStateDescription('Orders appear here when customers check out.')
        ->modelSearch();
}
```

`modelSearch(?string $searchClass = null, ?array $only = null)` turns the table's global search box into the core's search. `$searchClass` defaults to the definition bound to the table's model; passing one that searches another model throws an `InvalidArgumentException`. `$only` restricts the search to fields of the given kinds (`->modelSearch(only: ['number', 'email'])`). Search per column is not touched.

A table that has no bound definition and is given no `$searchClass` stays native: `modelSearch()` returns it unchanged.

**Viewer's language.** Tables, selects, global search and the palette all search in the language of the current request (`app()->getLocale()`), so `Müller` also finds `mueller` for `de`.

**Fields found through a parent (`via`).** An order found by its customer's e-mail is found only through the customer's scope: the parent resource must be bound on the current panel and the user must be able to open it, otherwise nothing is found through it. The scopes are those of the bound resources and are asked on every search, for tables, selects and global search as for the palette.

Call order matters:

- Call `modelSearch()` **after** the table's query (`->query()` or the resource's own), so it knows the model.
- Call it **after** your own `emptyStateHeading()` and `emptyStateDescription()`. It keeps what you set and shows it whenever the search did not fail, and shows its own text when the search was too slow, too broad, refused or answered from an index that is still building. A heading set *after* `modelSearch()` replaces the package's and hides those states, and the user would read "No orders yet" for a search that timed out.

The empty state is Filament's own empty state component. These states are distinct and none of them pretends to be "no results":

| Outcome | Shown | Rows |
|---|---|---|
| Timed out (`SearchTimedOut`) | "Search timed out" | none |
| Too broad (`SearchTooBroad`) | "Too many matches" | none |
| Index not ready (`SearchIndexNotReady`) | "Search index is being built" | none |
| Rate limited (`SearchRateLimited`) | "Too many searches" | none |
| Search unavailable (privacy gate) | "Search unavailable" | none |
| Nothing matched | your own empty state | none |

Pagination and the total stay consistent because a refused search matches nothing in the table's own query rather than throwing.

The search is narrowed by the table's own query (its scope, soft deletes, tenant constraints) and never goes outside it. The core sees the scope as a snapshot taken before the narrowing, and **a custom `select()` on the table query is reset to `<table>.*` for that scope**: a select never changes which rows belong to the table, so the scope cannot depend on it. The select you wrote still applies to the rows shown.

The phrase is bound as a parameter by the core and never reaches a log, an event or an exception message of this package.

## Selects

```php
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;

Select::make('customer_id')
    ->relationship('customer', 'name', modifyQueryUsing: fn ($query) => $query->where('active', true))
    ->searchable()
    ->modelSearch();

SelectFilter::make('customer_id')
    ->relationship('customer', 'name')
    ->searchable()
    ->modelSearch();
```

`modelSearch(?string $searchClass = null, ?array $only = null, bool $stages = true)`:

- **`relationship()` first.** Without it a `LogicException` is thrown: the select has no related model to search. `$searchClass` defaults to the definition bound to the related model; a class that searches another model throws.
- `$only` restricts the kinds searched. `stages: false` searches every stage at once instead of the first stage that answers.
- Only the *search* is replaced. The labels of values that are already selected still come from the relationship query, so they keep rendering whatever the index holds.
- Options never leave the relationship query (including its `modifyQueryUsing`) or the related resource's own global search scope, and they come in the core's ranking order, up to the select's options limit.
- Until the index serves the related model the select searches natively. An empty phrase is native too.

A select has no room for a message. A search that timed out, was refused (too broad, rate limited, unavailable), or hits an index that is still building and found nothing offers an **empty list**. Options that *were* found are shown even when the answer is flagged as incomplete: dropping correct, correctly scoped options would be worse than showing them, and the core already marks the answer.

## Global search

```php
final class OrderResource extends Resource
{
    use InteractsWithModelSearch;

    public static function modelSearch(): string
    {
        return OrderSearch::class;
    }

    protected static ?string $recordTitleAttribute = 'number';
}
```

List the resource in the plugin (see [Binding resources](#binding-resources)). Then:

- Until the index serves the model, the resource's global search stays Filament's own.
- Once it does, the index answers inside `getGlobalSearchResults()`, in the core's ranking order, within `getGlobalSearchEloquentQuery()` (so the resource's own scope, soft deletes and tenant constraints apply), up to `getGlobalSearchResultsLimit()`.
- **Every stage is searched at once** (`withoutStages()`), as native Filament global search returns every record: this surface has no room for a note saying that only the open orders were searched. The palette, which has the room, starts with the first stage and offers the rest of them.
- Titles, urls, details and actions are the resource's own (`getGlobalSearchResultTitle()`, `getGlobalSearchResultUrl()`, `getGlobalSearchResultDetails()`, `getGlobalSearchResultActions()`); the records are loaded with the scope's own eager loads.
- A line telling what matched is added to the details: the value when the core's reveal rule lets the viewer see it, and otherwise only **"Matched by: <field>"**. A value that equals the title adds nothing, and a native detail of the same name is kept.
- `modifyGlobalSearchQuery()` is **not applied** on the index path: it works on Filament's native `ILIKE` query, which does not exist there. Put constraints into `getGlobalSearchEloquentQuery()` instead, or into the core's definition.
- Access (`canAccess()` and `canViewAny()`) is checked on every call, not when the panel boots; a user who may not open the resource gets no results from it.

A global search has no room for a message. A search that timed out, was refused, or hits an index that is still building without having found anything shows no results. Results that were found are shown whatever the flags say.

## Record palette

A modal that searches every bound resource at once (one `SearchQuery::palette()` call, so one rate-limit charge), opened from a button next to the panel's global search and by a keyboard shortcut. `FilamentModelSearchPlugin::make()->palette(false)` or `palette.enabled = false` switches it off. It is also absent for a user who may open none of the searched resources, and that is decided when the page renders, not at boot.

- Type at least two characters. The search is debounced (`palette.debounce_ms`). While a search runs, a spinner stands where the button that clears the field is; the button comes back when the answer does.
- **Type chips.** A row of chips right under the field, shown for an empty field and for results alike while at least two resources can be chosen: "All" first, then each resource, in navigation order. Icons are shown when every resource has a navigation icon ("All" then gets one too); a single resource without one leaves every chip without an icon, so the row stays even. The current one is highlighted. Choosing a resource narrows the search to it and up to `palette.per_scope` records come back instead of `palette.per_group`; "All", the chosen chip again, or Backspace in an empty field searches everywhere again. The chips are real buttons that follow the field in the tab order, and on a narrow screen the row scrolls sideways. A resource stays out of the chips with `public static function isOfferedAsPaletteScope(): bool { return false; }`: it is still searched everywhere, it only cannot be chosen alone. Only a resource the user may open is accepted, and access is asked again on every search: a resource the user may no longer open widens the search to every resource.
- **"Show all".** Each group asks for one record more than `palette.per_group`; when it comes back, the group shows `per_group` records and ends with "Show all <resources>", which narrows the search to that resource. A resource that cannot be chosen alone has no such row.
- **The list link.** In a narrowed search with results, the last option is "Open the <resources> list with “<phrase>”": the resource's list page with the phrase in the table search (`?search=`). It is absent when the user may not open the list.
- Groups follow the navigation order of the resources, except that a group holding an exact hit comes first; at most `palette.per_group` records each. Each group shows the icon of its resource, each record the resource's own title and url, and what matched (the value only when the core lets the viewer see it; otherwise "Matched by: <field>").
- **What a row shows.** The icon of the resource and its `getGlobalSearchResultTitle()`. The second line is the resource's `getGlobalSearchResultDetails()`; eager load what it reads in `getGlobalSearchEloquentQuery()`. Two optional methods add the rest: `getGlobalSearchResultImage(Model $record): ?string` shows a picture instead of the icon, and `getGlobalSearchResultRelatedTerm(Model $record): ?string` adds a magnifier that puts that term in the field and searches every resource for it (Tab on the highlighted row), so that a record leads to what is tied to it. No method or `null`: no picture, no magnifier, and there is none either when the term is the phrase already searched, since it would change nothing. What the term reveals is the resource's to decide.
- **Describe a row with table columns.** A resource opts in with `public static function getGlobalSearchResultRow(): ?string`, which names a subclass of `Happenv\FilamentModelSearch\Palette\SearchResultRow` (built through the container). Each method returns Filament table columns (or `Split` / `Stack` layouts), and all of them are optional:

  ```php
  final class OrderSearchResultRow extends SearchResultRow
  {
      public function with(): array { return ['channel']; }                      // eager loads, once per group
      public function leading(): array { return [ImageColumn::make('thumbnail')]; } // replaces the picture / icon
      public function aboveTitle(): array { return [TextColumn::make('channel.name')]; }
      public function besideTitle(): array { return [TextColumn::make('state')->badge()]; }
      public function belowTitle(): array { return [Split::make([TextColumn::make('customer'), TextColumn::make('total')->money('EUR')])]; } // replaces the details line
      public function trailing(): array { return [TextColumn::make('placed_at')->date()]; }
  }
  ```

  The order of a row is `[leading] [aboveTitle / title + besideTitle / belowTitle] [trailing] [match line] [magnifier]`; the title, the match line and the magnifier stay the palette's. An empty `leading` falls back to the picture and the icon, an empty `belowTitle` to the details line, so a resource without a row class looks exactly as before. Below the `md` breakpoint `trailing` moves under `belowTitle`. The match line is dropped when the row already shows its value. Columns only display: an editable column, or one with a `url()` or an `action()`, throws a `LogicException` naming the resource and the column (the row is itself the link to the record; `disabledClick()` makes a link column display-only). The columns are rendered outside a table, so a column that reads a relation needs it in `with()`. `copyable()`, expandable lists and `ViewColumn` / `View` layouts are not refused, but they can put click handlers or arbitrary markup into the option: keep them non-interactive.
- **Recently opened.** While the field is empty the palette lists the last records the signed-in user opened from it (`palette.history.limit`, default 5), newest first and of every type, in the same rows as results (your row class, fallbacks) but without a match line or a magnifier. "Clear history" at the bottom of the list deletes the user's history. Nothing is shown, not even a heading, when there is none. A record is noted when it is opened through the palette with a plain click or Enter; a modified click (new tab, middle click) keeps working natively and is not noted. Only the type and the key of the record, the user and the time are stored, never the phrase (type and user columns hold up to 150 characters, keys 64). The history is per user, not per panel or tenant: entries opened elsewhere are skipped when read but still count towards `keep`. A record that was deleted, that the user may no longer open, or whose resource is no longer bound is skipped. It needs a table: the package loads its migration (`run_migrations`, default `true`); a host that manages its own migrations sets it to `false` and publishes it with `php artisan vendor:publish --tag=filament-model-search-migrations`. `palette.history.enabled = false`, or no signed-in user, means nothing is read or written.
- **Nothing is loaded until it is called.** A page holds only a small loader with the shortcut and the listener for `open-modal`. The Livewire component, the modal and the styles are fetched from the panel on the first call on a page, and are refused to a user the palette is not offered to.
- A record found through a related one (a `via` field) is told by the related resource's name. It is found only if the user may also open the related resource: the parent's scope is the one of its resource, filled in by the palette. Otherwise nothing is found through it.
- **Command-palette behaviour.** However the modal is opened (the shortcut, the built-in button, or your own `open-modal` dispatch), the search field is focused at once and a previous phrase is selected, so typing replaces it. The modal sits near the top of the viewport and keeps its place while results grow; on a phone it is full width with small side gutters, and the list scrolls inside it. On iOS the keyboard only rises for focus given inside a tap: the palette listens to the `open-modal` event synchronously and focuses a hidden proxy field at once, then hands the focus to the real field, so *any* trigger that dispatches `open-modal` from its click handler (the built-in button, or your own `x-on:click="$dispatch('open-modal', { id: '...' })"`) keeps the keyboard up. Do not open it from a timer or after a request.
- **Arrow keys** move a highlight through the results, across groups, wrapping at both ends; the mouse pointer sets it too. **Enter** opens the highlighted record. The field is a `combobox` over a `listbox` (`aria-activedescendant`), so a screen reader hears the highlighted record. **Escape** closes the modal and keeps the phrase: reopening shows it again, selected.
- **Enter** with nothing highlighted opens the record when exactly one hit of the whole palette is an exact one.
- **The stage line** appears when the answer came from a stage (for example only open orders): it says which stage is shown and offers a button that repeats the search over every stage. It applies to the phrase on screen and is reset when the phrase changes, so the user asks again for the next phrase. Both texts come from the definition's stage labels, resolved in the viewer's locale:

  ```php
  public function stages(): array
  {
      return [
          SearchStage::make('open', fn (Builder $query): Builder => $query->where('state', 'open'))
              ->watching(['state'])
              ->label(fn (): string => __('Open orders')),      // "Showing: Open orders"
          SearchStage::all()->label(fn (): string => __('All orders')), // the button
      ];
  }
  ```

  Without a label, or when several types answered from their stages at once, the line falls back to the generic "Showing only records in progress." and "Search all".
- **"Search for similar"** (also `mod+enter` in the modal) finds names close to what was typed (typos). It is offered for a phrase that has letters and is not shaped like an identifier (an order number or an e-mail), while the groups are not full. It is **never** run unless the user asks: not while typing, not as a fallback. The run is one `similar()` per resource, each charged to the rate limit on its own, so a limit hit on a later resource drops the results of the whole run.
- A search that timed out, an index that is still building, a refused search and an empty answer are different states with their own notices. Records that came back are shown whatever the flags say, with a notice.
- Access is checked on every search and again when a record is opened. What was found is kept in a locked Livewire property: the browser can read it, not change it.
- The component is isolated (`#[Isolate]`), so its requests are not bundled with other components' updates; there is no polling.
- The phrase is never logged, and the palette adds nothing of it to a query other than through the core.

### Your own trigger

To open the palette from your own element (a wide search field in the topbar, say), hide the built-in button and keep the modal and the shortcut:

```php
FilamentModelSearchPlugin::make()->paletteTrigger(false) // or 'palette.trigger' => false in the config
```

The plugin option wins over the config. Then ask the package whether the palette is offered, instead of copying its rule:

```php
FilamentModelSearchPlugin::isPaletteOffered(): bool
```

It answers for the signed-in user on the current panel: the palette is switched on (config and plugin), a user is signed in, and at least one searched resource passes `canAccess() && canViewAny()`. The built-in trigger and the modal ask the same rule on every render. It does not depend on `paletteTrigger()`.

```blade
@if (\Happenv\FilamentModelSearch\FilamentModelSearchPlugin::isPaletteOffered())
    <button
        type="button"
        x-on:click="$dispatch('open-modal', { id: @js(\Happenv\FilamentModelSearch\Livewire\RecordPalette::MODAL_ID) })"
    >
        {{ __('Search') }}
    </button>
@endif
```

The modal id is `RecordPalette::MODAL_ID`. If you show a hint for the shortcut, read it from `config('filament-model-search.palette.keybindings')` (the first entry, in the form `mod+shift+k`; `mod` is Command on macOS and Control elsewhere).

## Configuration

Publish the file if you want to change anything:

```bash
php artisan vendor:publish --tag=filament-model-search-config
```

```php
// config/filament-model-search.php
return [
    'palette' => [
        'enabled' => true,
        'trigger' => true,
        'keybindings' => ['mod+shift+k'],
        'debounce_ms' => 250,
        'per_group' => 5,
        'per_scope' => 20,
        'history' => [
            'enabled' => true,
            'limit' => 5,
            'keep' => 20,
            'table' => 'model_search_palette_history',
            'run_migrations' => true,
        ],
    ],

    // How long the readiness of an index is remembered, in seconds.
    'readiness_cache_seconds' => 60,
];
```

`trigger` draws the built-in button next to the global search (see [Your own trigger](#your-own-trigger)). `keybindings` is a list of shortcuts in the form `mod+shift+k` (`mod` is Command on macOS and Control elsewhere); several may be given. Characters other than letters, digits and `+`/`-` are stripped. An empty list leaves the button as the only way in.

`history` is the palette's "Recently opened" (see [Record palette](#record-palette)): `limit` is how many are shown, `keep` how many are stored per user (older ones are dropped when a new one is written), `table` its table, and `run_migrations` whether the package loads its migration. Because Laravel merges config files one level deep, a published `palette` array replaces the whole default `palette`: copy the `history` block into it, or the defaults above apply.

The rest of the behaviour (timeouts, rate limit, privacy, word handling) is the core's configuration, `config/model-search.php`.

## Coming from another palette

If you use a spotlight-style palette that searches records, you can keep it for commands and navigation and let this one answer records, or switch the other one's record search off. The two do not share an index and do not know each other. When you move a resource over:

1. Write the core's definition for the model and run `migrate` (or `model-search:sync`) so the index exists.
2. Bind the resource and list it in the plugin.
3. Remove the other palette's provider for the same resource, so a record is not offered twice.

The default shortcut is `mod+shift+k`; if your other palette already uses it, change `palette.keybindings`.

## Static analysis

`modelSearch()` is registered as a macro on `Filament\Tables\Table`, `Filament\Forms\Components\Select` and `Filament\Tables\Filters\SelectFilter`. Filament's macros use its own `Macroable` trait, which Larastan does not know, so a host that analyses its code at a high level sees `Call to an undefined method ...::modelSearch()` and, if it silences that error, loses the analysis of everything chained after the call.

[`happenv-com/filament-phpstan-macros`](https://github.com/happenv-com/filament-phpstan-macros) (a development dependency, no configuration with `phpstan/extension-installer`) teaches PHPStan Filament macros, reading the registered closures at analysis time. Larastan boots your application, which runs this package's service provider, so the macros exist when PHPStan runs.

```bash
composer require --dev larastan/larastan happenv-com/filament-phpstan-macros
```

Without `phpstan/extension-installer`, include `vendor/happenv-com/filament-phpstan-macros/extension.neon` in your `phpstan.neon`. This package's own `phpstan/macroable.stub` is for analysing this package (it gives `Macroable::macro()` the `@param-closure-this` it needs so the macro closures see `$this`); it is not shipped as an extension and hosts do not need it.

## Translations

Every text is in `en`, `pl` and `de` under the `filament-model-search::` namespace. Publish to change them:

```bash
php artisan vendor:publish --tag=filament-model-search-translations
```

Files: `match-line.php` ("Matched by"), `record-palette.php` (the modal) and `search-outcome.php` (empty states and notices for timeouts, broad searches, rate limits and indexes that are still building).

## Testing

```bash
composer test      # Pest, needs PostgreSQL 18 with pg_trgm
composer lint      # Pint
composer analyse   # PHPStan level 8
vendor/bin/rector --dry-run
```

## AI assistants (Laravel Boost)

The package ships a [Laravel Boost](https://laravel.com/docs/boost) skill, `filament-model-search-development`, in `resources/boost/skills/`. `php artisan boost:install` (or `boost:update`) offers it to your coding agent: binding resources, `modelSearch()` on tables and selects, the palette's row methods and its behaviour, and testing. The core package ships its own skill for definitions and deployment.

## Licence

MIT. See [LICENSE.md](LICENSE.md).
