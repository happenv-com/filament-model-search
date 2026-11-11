---
name: filament-model-search-development
description: >-
  Connect Filament resources, tables, selects, global search and the record palette to
  happenv-com/laravel-model-search with happenv-com/filament-model-search. Use when binding a
  resource to a search definition (InteractsWithModelSearch, FilamentModelSearchPlugin::resources(),
  FilamentModelSearch::resource()), calling ->modelSearch() on a Table, Select or SelectFilter,
  configuring the record palette (rows, related term magnifier, "Search in", trigger, keybindings,
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
- A resource you cannot edit: `FilamentModelSearch::resource(OrderSearch::class, OrderResource::class)` from a provider's `boot()` (binds on every panel).
- Access is never decided at registration: `canAccess() && canViewAny()` is asked for the current user on every search. Do not cache it per worker (Octane).

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

- `modelSearch(?string $searchClass = null, ?array $only = null)`; selects also take `stages: false`.
- Call it **after** `->query()` / `relationship()` (it needs the model) and **after** `emptyStateHeading()` / `emptyStateDescription()`: a heading set later hides the timed-out, too-broad, rate-limited and index-building states.
- Until `ModelSearches::isReady(<model>)` the table, select and global search stay native (`ILIKE`). They switch by themselves once the index is ready.
- The search never leaves the table's own query or the relationship query (its `modifyQueryUsing`).
- PHPStan: `modelSearch()` is a Filament macro; add `happenv-com/filament-phpstan-macros` as a dev dependency instead of ignoring the error.

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
| `getGlobalSearchResultRelatedTerm(Model $record): ?string` | a magnifier (and Tab) that puts this term in the field and searches every resource for it (optional). Not shown when it equals the phrase already searched. |
| `isOfferedAsPaletteScope(): bool` | `false` keeps the resource out of "Search in" (optional, offered by default). It is still searched everywhere. |

Return `null` (or leave the method out) for no picture or no magnifier. What a related term reveals is the resource's responsibility: return only a value the current viewer may see.

### Behaviour to keep in mind

- **"Search in"**, shown before anything is typed, narrows the search to one resource ("Search in: Orders"); choosing it again, the cross or Backspace in an empty field searches everywhere. Up to `palette.per_scope` records come back instead of `palette.per_group`.
- "Search all" repeats a staged search over every stage; "Search for similar" (`mod+enter`) runs typo tolerance only when asked.
- Timed-out, index-building, rate-limited, unavailable and empty answers are separate states; results that came back are shown with a notice.
- What was found and the scope live in locked Livewire properties; `open()` and `searchRelated()` accept only records of the current results, and access is asked again.

## Configuration

`config/filament-model-search.php` (`php artisan vendor:publish --tag=filament-model-search-config`): `palette.enabled`, `palette.trigger`, `palette.keybindings`, `palette.debounce_ms`, `palette.per_group`, `palette.per_scope`, `palette.placeholder` (a translation key), `readiness_cache_seconds`. Texts live under `filament-model-search::` in `en`, `pl` and `de`.

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

- Do not override `getGlobalSearchResults()` on a resource that uses the trait.
- Do not call `->modelSearch()` before the table query or before your own empty state.
- Do not decide access at boot or remember it between requests.
- Do not log or report the phrase; the core keeps it out of logs, keep it out of yours.
