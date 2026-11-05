# Filament Model Search

Filament integration for [`happenv-com/laravel-model-search`](https://github.com/happenv-com/laravel-model-search): a command palette, table search, select search and global search backed by one record index.

> Work in progress. Documentation lands with the first release.

## Selects

```php
Select::make('customer_id')->relationship('customer', 'name')->searchable()->modelSearch();
SelectFilter::make('customer_id')->relationship('customer', 'name')->searchable()->modelSearch();
```

Call `modelSearch()` after `relationship()`. Only the search is replaced; labels of selected values still come from the relationship. Until the index serves the related model the select searches natively. Options never leave the relationship query (including its `modifyQueryUsing`) or the related resource's global search scope, and come in the core's ranking order, up to the select's options limit. `modelSearch(only: [...], stages: false)` restrict kinds and search every stage at once.

A select has no room for a message, so a search that timed out, was refused or hits an index that is still building offers an empty list.

## Global search

```php
final class OrderResource extends Resource
{
    use InteractsWithModelSearch;

    public static function modelSearch(): string
    {
        return OrderSearch::class;
    }
}
```

List the resource in `FilamentModelSearchPlugin::make()->resources([...])`. Until the index serves the model, the resource's global search stays Filament's own. Once it does, the index answers, in the core's ranking order, inside `getGlobalSearchEloquentQuery()`; titles, urls, details and actions are the resource's own, and a line telling what matched is added (the value only when the core's reveal rule lets the viewer see it; otherwise just "Matched by: e-mail"). Access (`canAccess()` and `canViewAny()`) is checked on every call, not when the panel boots.

A global search has no room for a message, so a search that timed out, was refused, or hits an index that is still building without having found anything shows no results. Results that were found are shown whatever the flags say. `modifyGlobalSearchQuery()` is not applied on the index path, and a resource that defines its own `getGlobalSearchResults()` is refused when it is registered, because the trait's method would never run.

## Licence

MIT. See [LICENSE.md](LICENSE.md).
