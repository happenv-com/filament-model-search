<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Binding;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
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
     * @param  class-string<resource>  $resourceClass
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
     * @return class-string<resource>
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

        // The cache is a convenience: when it fails, the core answers.
        try {
            $connection = (new $this->modelClass)->getConnection();
            $key = sprintf('filament-model-search:ready:%s:%s:%s', $connection->getName(), $connection->getDatabaseName(), $this->modelClass);
            $store = config('model-search.cache_store');
            $cache = Cache::store(is_string($store) ? $store : null);
            $cached = $cache->get($key);

            if (is_bool($cached)) {
                return $cached;
            }

            $ready = ModelSearches::isReady($this->modelClass);
            $cache->put($key, $ready, $seconds);

            return $ready;
        } catch (Throwable) {
            return ModelSearches::isReady($this->modelClass);
        }
    }
}
