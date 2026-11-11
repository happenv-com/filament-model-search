<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Binding;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource as FilamentResource;
use Happenv\FilamentModelSearch\Support\FailureReports;
use Happenv\ModelSearch\Facades\ModelSearches;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
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
