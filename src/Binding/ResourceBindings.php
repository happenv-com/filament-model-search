<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Binding;

use Filament\Facades\Filament;
use Filament\Resources\Resource as FilamentResource;
use Happenv\FilamentModelSearch\Concerns\InteractsWithModelSearch;
use Happenv\ModelSearch\Definition\ModelSearch;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use ReflectionMethod;

/**
 * Which search definition each resource shows, per panel. Holds only class names, so one instance
 * serves every request of a worker.
 *
 * A binding made with a panel id belongs to that panel; one made without belongs to every panel.
 * Reads resolve against the current panel.
 */
final class ResourceBindings
{
    /** @var array<class-string<Model>, list<array{binding: ResourceBinding, panel: string|null}>> */
    private array $entries = [];

    /**
     * Binds a definition to a resource. Binding the same pair for the same panel again changes nothing.
     * The core is not asked whether it knows the definition: a definition it does not know fails
     * on the first search, not here, because bindings are made while panels register.
     *
     * @param  class-string  $searchClass
     * @param  class-string<FilamentResource>  $resourceClass
     *
     * @throws LogicException when the class is not a search definition, the definition searches another model than the resource shows, or the model is already bound to a different resource on an overlapping panel
     */
    public function register(string $searchClass, string $resourceClass, ?string $panelId = null): void
    {
        if (! is_subclass_of($searchClass, ModelSearch::class)) {
            throw new LogicException(sprintf('%s is not a search definition: it must extend %s.', $searchClass, ModelSearch::class));
        }

        $model = $searchClass::model();

        if ($resourceClass::getModel() !== $model) {
            throw new LogicException(sprintf('%s searches %s but %s shows %s.', $searchClass, $model, $resourceClass, $resourceClass::getModel()));
        }

        foreach ($this->entries[$model] ?? [] as $entry) {
            $samePair = $entry['binding']->searchClass() === $searchClass && $entry['binding']->resourceClass() === $resourceClass;

            if ($samePair && $entry['panel'] === $panelId) {
                return;
            }

            if (! $samePair && ($entry['panel'] === null || $panelId === null || $entry['panel'] === $panelId)) {
                throw new LogicException(sprintf('%s is already bound to %s.', $model, $entry['binding']->resourceClass()));
            }
        }

        $this->entries[$model][] = ['binding' => new ResourceBinding($searchClass, $resourceClass, $model), 'panel' => $panelId];
    }

    /**
     * Binds a resource that names its definition through {@see InteractsWithModelSearch}.
     *
     * @param  class-string  $resourceClass
     *
     * @throws LogicException when the class is not a resource using the trait
     */
    public function registerResource(string $resourceClass, ?string $panelId = null): void
    {
        if (! is_subclass_of($resourceClass, FilamentResource::class) || ! in_array(InteractsWithModelSearch::class, class_uses_recursive($resourceClass), true)) {
            throw new LogicException(sprintf('%s is listed for the model search but does not use %s; add the trait, or bind it with FilamentModelSearch::resource().', $resourceClass, InteractsWithModelSearch::class));
        }

        // The trait's global search would never run: say so instead of silently searching natively.
        $own = new ReflectionMethod($resourceClass, 'getGlobalSearchResults');

        if ($own->getFileName() !== new ReflectionMethod(InteractsWithModelSearch::class, 'getGlobalSearchResults')->getFileName()) {
            throw new LogicException(sprintf('%s defines its own getGlobalSearchResults(), which hides the one of %s. Remove it, or bind the search with FilamentModelSearch::resource() and call the search yourself.', $resourceClass, InteractsWithModelSearch::class));
        }

        $searchClass = new ReflectionMethod($resourceClass, 'modelSearch')->invoke(null);

        if (! is_string($searchClass) || ! class_exists($searchClass)) {
            throw new LogicException(sprintf('%s::modelSearch() must return a search definition class.', $resourceClass));
        }

        $this->register($searchClass, $resourceClass, $panelId);
    }

    /**
     * The binding of a model on the current panel.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function for(string $modelClass): ?ResourceBinding
    {
        $panelId = Filament::getCurrentOrDefaultPanel()?->getId();
        $fallback = null;

        foreach ($this->entries[$modelClass] ?? [] as $entry) {
            if ($panelId !== null && $entry['panel'] === $panelId) {
                return $entry['binding'];
            }

            if ($entry['panel'] === null) {
                $fallback = $entry['binding'];
            }
        }

        return $fallback;
    }

    /**
     * The bindings of the current panel.
     *
     * @return list<ResourceBinding>
     */
    public function all(): array
    {
        $bindings = [];

        foreach (array_keys($this->entries) as $model) {
            $binding = $this->for($model);

            if ($binding instanceof ResourceBinding) {
                $bindings[] = $binding;
            }
        }

        return $bindings;
    }
}
