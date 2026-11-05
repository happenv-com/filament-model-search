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

## Licence

MIT. See [LICENSE.md](LICENSE.md).
