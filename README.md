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

## Record palette

A modal that searches every resource listed in the plugin at once (one `SearchQuery::palette()`), opened from a button next to the panel's global search and by `palette.keybindings` (default `mod+shift+k`). `FilamentModelSearchPlugin::make()->palette(false)` or `palette.enabled = false` switches it off; it is also absent for a user who may open none of the searched resources.

- Groups follow the navigation order of the resources, except that a group holding an exact hit comes first; at most `palette.per_group` (5) records each. Each record shows the resource's own title, icon and url, and what matched (the value only when the core lets the viewer see it).
- A record found through a related one (a `via` field) is found only if the user may also open the related resource: the parent's scope is the one of its resource, filled in by the palette. Otherwise nothing is found through it.
- Enter opens the record when exactly one hit of the whole palette is an exact one.
- "Search all" shows when the answer came from a stage; "Search for similar" (also `mod+enter` in the modal) shows for a phrase with letters that is not shaped like an identifier, while the groups are not full. Similar names are never searched unless the user asks.
- A search that timed out, an index that is still building and an empty answer are different states. Records that came back are shown whatever the flags say, with a notice.
- Access is checked on every search and again when a record is opened. What was found is kept in a locked property: the browser can read it, not change it.
- The phrase is never logged, and the palette adds nothing of it to a query.

## Licence

MIT. See [LICENSE.md](LICENSE.md).
