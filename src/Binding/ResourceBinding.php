<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Binding;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource as FilamentResource;
use Happenv\FilamentModelSearch\Palette\SearchResultRow;
use Happenv\FilamentModelSearch\Support\FailureReports;
use Happenv\FilamentModelSearch\Support\HostScope;
use Happenv\ModelSearch\Facades\ModelSearches;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use LogicException;
use Throwable;

/**
 * A search definition bound to the Filament resource that shows its records.
 *
 * Nothing is remembered between calls except the readiness of the index: access, scope, title and
 * url are computed whenever they are asked for, because the binding is created once per worker and
 * is used by many users.
 */
final readonly class ResourceBinding
{
    /**
     * @param  class-string  $searchClass
     * @param  class-string<FilamentResource>  $resourceClass
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        private string $searchClass,
        private string $resourceClass,
        private string $modelClass,
    ) {}

    /**
     * @return class-string
     */
    public function searchClass(): string
    {
        return $this->searchClass;
    }

    /**
     * @return class-string<FilamentResource>
     */
    public function resourceClass(): string
    {
        return $this->resourceClass;
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * Whether the current user may open the resource at all.
     */
    public function isAccessible(): bool
    {
        $resource = $this->resourceClass;

        return $this->isOnCurrentPanel()
            && $resource::canAccess()
            && $resource::canViewAny();
    }

    /**
     * The records the resource's global search offers, before the search narrows them.
     *
     * @return Builder<Model>
     */
    public function scope(): Builder
    {
        $resource = $this->resourceClass;

        return $resource::getGlobalSearchEloquentQuery();
    }

    /**
     * The records with the given keys that are inside the resource's scope, with what its scope and its palette row
     * load. The scope is used as a subquery, so an orWhere in it cannot widen the lookup.
     *
     * @param  list<string>  $keys
     * @return EloquentCollection<int, Model>
     */
    public function recordsInScope(array $keys): EloquentCollection
    {
        $modelClass = $this->modelClass;
        $model = new $modelClass;

        if ($keys === []) {
            return $model->newCollection();
        }

        $scope = $this->scope();

        try {
            // In a transaction of its own (a savepoint inside another one), so a refused key leaves the caller's usable.
            $records = $model->getConnection()->transaction(static fn () => $model->newQuery()
                ->whereKey($keys)
                ->whereIn($model->getQualifiedKeyName(), HostScope::of($scope)->select($model->getQualifiedKeyName()))
                ->get());
        } catch (QueryException $exception) {
            // A key that is not of this model's key type (it came from the browser or from an old history row) is
            // no record of this resource. Only a data exception (SQLSTATE class 22) is taken for that.
            if (! str_starts_with((string) $exception->getCode(), '22')) {
                throw $exception;
            }

            return $model->newCollection();
        }

        $records->loadMissing($scope->getEagerLoads());
        $records->loadMissing($this->paletteRow()?->getWith() ?? []);

        return $records;
    }

    public function title(Model $record): string|Htmlable
    {
        $resource = $this->resourceClass;

        return $resource::getGlobalSearchResultTitle($record);
    }

    /**
     * The page to open for a record: its view page, else its edit page, else none. Never a delete page.
     */
    public function url(Model $record): ?string
    {
        $resource = $this->resourceClass;

        if (! $this->isOnCurrentPanel()) {
            return null;
        }

        // Like Filament's global search: a nested resource has no url of its own.
        if ($resource::getParentResourceRegistration() !== null) {
            return null;
        }

        if ($resource::hasPage('view') && $resource::canView($record)) {
            return $resource::getUrl('view', ['record' => $record]);
        }

        if ($resource::hasPage('edit') && $resource::canEdit($record)) {
            return $resource::getUrl('edit', ['record' => $record]);
        }

        return null;
    }

    /**
     * The list page of the resource with the phrase in its table search (the `search` query string of Filament's
     * list pages), or null when the user may not open a list.
     */
    public function listUrl(#[\SensitiveParameter] string $phrase): ?string
    {
        $resource = $this->resourceClass;

        if (! $this->isAccessible() || ! $resource::hasPage('index')) {
            return null;
        }

        return $resource::getUrl('index', ['search' => $phrase]);
    }

    /**
     * The second line of a result: what the resource's global search shows under the title.
     *
     * @return list<array{label: string, value: string}>
     */
    public function details(Model $record): array
    {
        $resource = $this->resourceClass;
        $details = [];

        foreach ($resource::getGlobalSearchResultDetails($record) as $label => $value) {
            if (filled($value)) {
                $details[] = ['label' => $label, 'value' => $value];
            }
        }

        return $details;
    }

    /**
     * The picture of a result, shown instead of the resource's icon: the optional
     * `getGlobalSearchResultImage(Model $record): ?string` of the resource.
     */
    public function image(Model $record): ?string
    {
        return $this->optionalString('getGlobalSearchResultImage', $record);
    }

    /**
     * What to search for to see the records tied to this one: the optional
     * `getGlobalSearchResultRelatedTerm(Model $record): ?string` of the resource. Without one the result has no related search.
     */
    public function relatedTerm(Model $record): ?string
    {
        return $this->optionalString('getGlobalSearchResultRelatedTerm', $record);
    }

    /**
     * Whether the palette offers the resource as a chip, so that the search can be narrowed to it: the
     * optional static `isOfferedAsPaletteScope(): bool` of the resource, offered without one. A resource left out
     * is still searched everywhere; it only cannot be chosen alone.
     */
    public function offersScope(): bool
    {
        $resource = $this->resourceClass;

        return ! method_exists($resource, 'isOfferedAsPaletteScope') || $resource::isOfferedAsPaletteScope() === true;
    }

    /**
     * How the palette shows the records of the resource: the row its optional static
     * `globalSearchResultRow(SearchResultRow $row): SearchResultRow` describes, called through the container so it may
     * ask for more than the row. Without the method the records look the way the palette shows them by default.
     *
     * Built again on every call: bindings live as long as the worker, the columns and their labels belong to a request.
     *
     * @throws LogicException When the method returns something else than a row, or the resource still names a row
     *                        class the way versions before 0.5 did.
     */
    public function paletteRow(): ?SearchResultRow
    {
        $resource = $this->resourceClass;

        if (method_exists($resource, 'getGlobalSearchResultRow')) {
            throw new LogicException(sprintf(
                '[%s::getGlobalSearchResultRow()] is no longer read: since version 0.5 a resource describes its row in [public static function globalSearchResultRow(SearchResultRow $row): SearchResultRow].',
                $resource,
            ));
        }

        // `method_exists()`, not `is_callable()`: a resource answers every static call through its macros.
        if (! method_exists($resource, 'globalSearchResultRow')) {
            return null;
        }

        $row = app()->call($resource.'::globalSearchResultRow', ['row' => SearchResultRow::make()]);

        if (! $row instanceof SearchResultRow) {
            throw new LogicException(sprintf(
                '[%s::globalSearchResultRow()] returned [%s] instead of the [%s] it was given.',
                $resource,
                get_debug_type($row),
                SearchResultRow::class,
            ));
        }

        return $row->isConfigured() ? $row : null;
    }

    private function optionalString(string $method, Model $record): ?string
    {
        $resource = $this->resourceClass;

        if (! method_exists($resource, $method)) {
            return null;
        }

        $value = $resource::$method($record);

        return is_string($value) && filled($value) ? $value : null;
    }

    private function isOnCurrentPanel(): bool
    {
        return in_array($this->resourceClass, Filament::getCurrentOrDefaultPanel()?->getResources() ?? [], true);
    }

    public function icon(): string|BackedEnum|null
    {
        $resource = $this->resourceClass;
        $icon = $resource::getNavigationIcon();

        return $icon instanceof Htmlable ? null : $icon;
    }

    /**
     * The plural name of what the resource lists, for a heading.
     */
    public function label(): string
    {
        $resource = $this->resourceClass;

        return $resource::getPluralModelLabel();
    }

    /**
     * The singular name of what the resource lists.
     */
    public function modelLabel(): string
    {
        $resource = $this->resourceClass;

        return $resource::getModelLabel();
    }

    public function sort(): int
    {
        $resource = $this->resourceClass;

        return $resource::getNavigationSort() ?? 0;
    }

    /**
     * Whether the core serves the model from its index. Remembered for `readiness_cache_seconds`,
     * per connection and database, on top of the core's own short cache.
     */
    public function isReady(): bool
    {
        $seconds = config('filament-model-search.readiness_cache_seconds');

        if (! is_int($seconds) || $seconds <= 0) {
            return ModelSearches::isReady($this->modelClass);
        }

        // The cache is a convenience: a failing store is reported once per worker and the core answers.
        $cache = null;
        $key = '';
        $cached = null;
        $storeName = 'default';

        try {
            $connection = (new $this->modelClass)->getConnection();
            $key = sprintf('filament-model-search:ready:%s:%s:%s', $connection->getName(), $connection->getDatabaseName(), $this->modelClass);
            $store = config('model-search.cache_store');
            $storeName = is_string($store) ? $store : 'default';
            $cache = Cache::store(is_string($store) ? $store : null);
            $cached = $cache->get($key);
        } catch (Throwable $e) {
            resolve(FailureReports::class)->reportOnce('readiness-cache:'.$storeName, $e);
            $cache = null;
        }

        if (is_bool($cached)) {
            return $cached;
        }

        $ready = ModelSearches::isReady($this->modelClass);

        if ($cache !== null) {
            try {
                $cache->put($key, $ready, $seconds);
            } catch (Throwable $e) {
                resolve(FailureReports::class)->reportOnce('readiness-cache:'.$storeName, $e);
            }
        }

        return $ready;
    }
}
