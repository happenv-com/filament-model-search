<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Support;

use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\ModelSearch\Definition\ModelSearch;
use Happenv\ModelSearch\Facades\ModelSearches;
use Illuminate\Database\Eloquent\Model;

/**
 * The search definition that serves a model right now, or none while the index does not serve it yet.
 *
 * Asked on every search, never remembered: readiness changes while the process lives.
 */
final class ReadySearch
{
    /**
     * @param  class-string<Model>  $model
     * @param  string|null  $searchClass  An explicit definition; defaults to the one bound to the model.
     * @return class-string<ModelSearch>|null
     */
    public static function for(string $model, ?string $searchClass = null): ?string
    {
        $binding = resolve(ResourceBindings::class)->for($model);
        $searchClass ??= $binding?->searchClass();

        if ($searchClass === null || ! is_subclass_of($searchClass, ModelSearch::class)) {
            return null;
        }

        $ready = $binding !== null && $binding->searchClass() === $searchClass
            ? $binding->isReady()
            : ModelSearches::isReady($model);

        return $ready ? $searchClass : null;
    }
}
