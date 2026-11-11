---
name: filament-model-search-development
description: >-
  Connect Filament resources, tables, selects, global search and the record palette to
  happenv-com/laravel-model-search with happenv-com/filament-model-search. Use when binding a
  resource to a search definition (InteractsWithModelSearch, FilamentModelSearchPlugin::resources(),
  FilamentModelSearch::resource()), calling ->modelSearch() on a Table, Select or SelectFilter,
  replacing a resource's global search (getGlobalSearchResults, getGlobalSearchEloquentQuery),
  configuring the record palette (rows, related term magnifier, type chips, trigger, keybindings,
  per_group / per_scope), or testing any of these.
---

# Filament Model Search

Filament surfaces answered by the record index of `happenv-com/laravel-model-search`: a record palette, table search, select search and global search. The core owns the index, ranking, rate limit and the privacy of the phrase; this package connects it to Filament and keeps Filament's authorization in charge. Definitions, fields, stages and `version()` bumps belong to the core (see the `laravel-model-search-development` skill).

## When to use this skill

- Making a resource searchable through the index, or a table, select or filter search through it.
- Changing what a palette row shows, or how the palette behaves.
- Writing tests for these surfaces.

## Binding a resource

Register the plugin on every panel that should offer the search, and list the resources:

```php
use Happenv\FilamentModelSearch\FilamentModelSearchPlugin;

$panel->plugin(
    FilamentModelSearchPlugin::make()
        ->resources([OrderResource::class, CustomerResource::class])
        ->palette()                 // default true
        ->paletteTrigger(true),     // false: you draw your own trigger
);
```

Each listed resource uses the trait and names its definition:

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

- A listed resource without the trait, or one that declares its own `getGlobalSearchResults()`, is refused when the panel registers.
- A resource you cannot edit: `FilamentModelSearch::resource(OrderSearch::class, OrderResource::class)` from a provider's `boot()` (binds on every panel). The palette, tables and selects use such a binding, but **global search stays Filament's own**: only the trait replaces `getGlobalSearchResults()`.
- Binding a model to a second resource on an overlapping panel, or a definition to a resource of another model, throws `LogicException`. An unknown definition is not checked at binding; it fails on the first search.
- Access is never decided at registration: the palette and global search ask `canAccess() && canViewAny()` for the current user on every call (tables and selects rely on the page or form that shows them). Do not cache it per worker (Octane).
- **`via` fields** (an order found by its customer's e-mail) reach the parent only through the parent resource's scope: bind and list the parent's resource on the same panel, or nothing is found through it — silently. A user who may not open the parent resource finds nothing through it either.

## Tables, selects and filters

```php
public static function table(Table $table): Table
{
    return $table
        ->query(fn () => Order::query()->with('customer'))
        ->columns([...])
        ->emptyStateHeading('No orders yet')
        ->modelSearch();            // after the query and after your own empty state
}

Select::make('customer_id')->relationship('customer', 'name')->searchable()->modelSearch();
SelectFilter::make('customer_id')->relationship('customer', 'name')->searchable()->modelSearch();
```

- `modelSearch(?string $searchClass = null, ?array $only = null)`; `Select` and `SelectFilter` also take `stages: false` (every stage at once). `$only` restricts the field kinds searched (`only: ['number', 'email']`).
- A `$searchClass` for another model throws `InvalidArgumentException`; a `Select` / `SelectFilter` without `relationship()` throws `LogicException`. A table whose model has no bound definition and gets no `$searchClass` stays native — `modelSearch()` returns it unchanged.
- Call it **after** `->query()` / `relationship()` (it needs the model) and **after** `emptyStateHeading()` / `emptyStateDescription()`: a heading set later hides the timed-out, too-broad, rate-limited and index-building states.
- Until `ModelSearches::isReady(<model>)` the table, select and global search stay native (`ILIKE`). They switch by themselves once the index is ready.
- The search never leaves the table's own query or the relationship query (its `modifyQueryUsing`). A custom `select()` on the table query is reset to `<table>.*` for the scope only; the rows shown keep your select.
- PHPStan: `modelSearch()` is a Filament macro; add `happenv-com/filament-phpstan-macros` as a dev dependency instead of ignoring the error.
- Selects and global search have no room for a message: a timed-out or refused search shows nothing; found records are shown even when the answer is flagged incomplete.

## Global search

The trait's `getGlobalSearchResults()` takes one of two paths. When the model's index is ready, it runs the core's search inside `getGlobalSearchEloquentQuery()` (scope, soft deletes, tenancy), up to `getGlobalSearchResultsLimit()`, in the core's ranking order, **every stage at once** (`withoutStages()`). Otherwise (index not ready, or the resource is not the one bound on this panel) it falls back to Filament's own global search, where `modifyGlobalSearchQuery()` applies.

- `modifyGlobalSearchQuery()` therefore **stops applying** once the index is ready. Put constraints in `getGlobalSearchEloquentQuery()` (used on both paths) or in the core's definition.
- Titles, urls, details and actions stay the resource's own; a "Matched by" line is added to the details (the value only when the core lets the viewer see it).

## The record palette

One modal searching every bound resource the user may open (`SearchQuery::palette()`, one rate-limit charge). It loads on first call only. Opened by the built-in trigger, `palette.keybindings` (default `mod+shift+k`) or your own element:

```blade
@if (\Happenv\FilamentModelSearch\FilamentModelSearchPlugin::isPaletteOffered())
    <button type="button" x-on:click="$dispatch('open-modal', { id: @js(\Happenv\FilamentModelSearch\Livewire\RecordPalette::MODAL_ID) })">
        {{ __('Search') }}
    </button>
@endif
```

Dispatch `open-modal` synchronously from the click (iOS raises the keyboard only for focus given inside a tap). Ask `isPaletteOffered()`; do not copy its rule.

### What a row shows: methods on the resource

| Method | Effect |
|---|---|
| `getGlobalSearchResultTitle()` | the title |
| `getGlobalSearchResultDetails()` | the second line; eager load what it reads in `getGlobalSearchEloquentQuery()` |
| `getGlobalSearchResultImage(Model $record): ?string` | a picture instead of the resource icon (optional) |
| `getGlobalSearchResultRow(): ?string` | a subclass of `Happenv\FilamentModelSearch\Palette\SearchResultRow` (built by the container) describing the row with table columns: `leading()`, `aboveTitle()`, `besideTitle()`, `belowTitle()`, `trailing()` return columns or `Split`/`Stack` layouts, `with()` the relations to eager load once per group (optional). Empty slots fall back to the image/icon and the details line. Editable columns and columns with `url()`/`action()` throw a `LogicException` (the row is itself the link; `disabledClick()` allows display). |
| `getGlobalSearchResultRelatedTerm(Model $record): ?string` | a magnifier (and Tab) that puts this term in the field and searches every resource for it (optional). Not shown when it equals the phrase already searched. |
| `isOfferedAsPaletteScope(): bool` | `false` keeps the resource out of the chips (optional, offered by default). It is still searched everywhere. |

Return `null` (or leave the method out) for no picture or no magnifier. What a related term reveals is the resource's responsibility: return only a value the current viewer may see.

### Behaviour to keep in mind

- **Type chips** under the field ("All" plus one per resource that can be chosen, at least two) narrow the search to one resource; "All", the chosen chip again or Backspace in an empty field searches everywhere. Up to `palette.per_scope` records come back instead of `palette.per_group`.
- A group with more than `palette.per_group` records ends with "Show all <resources>" (narrows to that resource, not shown for a resource kept out of the chips); a narrowed search ends with a link to the resource's list with the phrase in `?search=`.
- **Recently opened** (empty field): the last records the current user opened from the palette, newest first, same rows as results without the match line or magnifier, plus "Clear history". Stored per user as type + key + time in `palette.history.table` (migration loaded by the package unless `palette.history.run_migrations` is false; publish tag `filament-model-search-migrations`); never the phrase. Plain clicks go through `open()`, modified clicks stay native and are not noted. `palette.history.enabled`, `limit`, `keep`.
- "Search all" repeats a staged search over every stage; "Search for similar" (`mod+enter`) runs typo tolerance only when asked.
- Timed-out, index-building, rate-limited, unavailable and empty answers are separate states; results that came back are shown with a notice.
- What was found and the scope live in locked Livewire properties. `searchRelated()` accepts only a term a record of the current results offered; `open()` opens any record the user may open now (a bound, accessible resource and a record inside its scope), so a click still works when the results moved on, and access is asked again every time.

## Configuration

- `config/filament-model-search.php` (`php artisan vendor:publish --tag=filament-model-search-config`): `palette.enabled`, `palette.trigger`, `palette.keybindings`, `palette.debounce_ms`, `palette.per_group`, `palette.per_scope`, `palette.history.*`, `palette.placeholder` (a translation key), `readiness_cache_seconds`.
- `paletteTrigger()` overrides `palette.trigger`. The palette is offered only when both `palette()` and `palette.enabled` are on.
- Texts live under `filament-model-search::` in `en`, `pl` and `de`; publish with `--tag=filament-model-search-translations`.
- v0.4.0 removed the `welcome_heading`, `scope` and `clear_scope` keys and added `all`, `scope_label`, `show_all`, `open_list_with_phrase`, `open_list_enter`, `recently_opened` and `clear_history`.
- v0.3.0 added the `narrow`, `scope`, `clear_scope` and `search_term` keys and removed `open_list`: an app that published the translations before must republish them (or add the keys).

## Testing

```php
use Happenv\FilamentModelSearch\Livewire\RecordPalette;
use Happenv\ModelSearch\Postgres\Indexing\SearchIndexer;
use Livewire\Livewire;

Order::factory()->create(['number' => 'OR-1001']);
resolve(SearchIndexer::class)->drainNow();   // the index sees the write

Livewire::test(RecordPalette::class)
    ->set('phrase', 'OR-1001')
    ->assertSee('OR-1001');

Livewire::test(RecordPalette::class)->call('scopeTo', 'order')->assertSet('scope', 'order');
```

Act as a user who may open the resource, or the group is left out. Tests need PostgreSQL 18+ with `pg_trgm` (or the core's `database` driver).

## Do not

- Do not override `getGlobalSearchResults()` on a resource that uses the trait, and do not rely on `modifyGlobalSearchQuery()` once the index answers.
- Do not call `->modelSearch()` before the table query or before your own empty state.
- Do not decide access at boot or remember it between requests.
- Do not log or report the phrase; the core keeps it out of logs, keep it out of yours.
