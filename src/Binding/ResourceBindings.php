<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Binding;

use Filament\Resources\Resource;
use Happenv\FilamentModelSearch\Concerns\InteractsWithModelSearch;
use Happenv\ModelSearch\Definition\SearchesModel;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use ReflectionClass;

/**
 * Which search definition each resource shows. Holds only class names, so one instance serves
 * every request of a worker.
 */
final class ResourceBindings
{
    /** @var array<class-string<Model>, ResourceBinding> */
    private array $bindings = [];

    /**
     * Binds a definition to a resource. Binding the same pair again changes nothing.
     *
     * @param  class-string  $searchClass
     * @param  class-string<resource>  $resourceClass
     *
     * @throws LogicException when the definition searches another model than the resource shows, or the model is already bound to another resource or definition
     */
    public function register(string $searchClass, string $resourceClass): void
    {
        $model = $this->modelOf($searchClass);

        if ($resourceClass::getModel() !== $model) {
            throw new LogicException(sprintf('%s searches %s but %s shows %s.', $searchClass, $model, $resourceClass, $resourceClass::getModel()));
        }

        $existing = $this->bindings[$model] ?? null;

        if ($existing !== null) {
            if ($existing->searchClass() === $searchClass && $existing->resourceClass() === $resourceClass) {
                return;
            }

            throw new LogicException(sprintf('%s is already bound to %s.', $model, $existing->resourceClass()));
        }

        $this->bindings[$model] = new ResourceBinding($searchClass, $resourceClass, $model);
    }

    /**
     * Binds a resource that names its definition through
     * {@see InteractsWithModelSearch}. Other classes are ignored.
     *
     * @param  class-string  $resourceClass
     */
    public function registerResource(string $resourceClass): void
    {
        if (! is_subclass_of($resourceClass, Resource::class) || ! method_exists($resourceClass, 'modelSearch')) {
            return;
        }

        /** @var class-string $searchClass */
        $searchClass = $resourceClass::modelSearch();

        $this->register($searchClass, $resourceClass);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public function for(string $modelClass): ?ResourceBinding
    {
        return $this->bindings[$modelClass] ?? null;
    }

    /**
     * @return list<ResourceBinding>
     */
    public function all(): array
    {
        return array_values($this->bindings);
    }

    /**
     * @param  class-string  $searchClass
     * @return class-string<Model>
     */
    private function modelOf(string $searchClass): string
    {
        $attributes = new ReflectionClass($searchClass)->getAttributes(SearchesModel::class);

        if ($attributes === []) {
            throw new LogicException(sprintf('%s does not name its model with #[SearchesModel].', $searchClass));
        }

        return $attributes[0]->newInstance()->model;
    }
}
