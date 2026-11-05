<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Facades;

use Filament\Resources\Resource as FilamentResource;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Illuminate\Support\Facades\Facade;

final class FilamentModelSearch extends Facade
{
    /**
     * Binds a search definition to the resource that shows its records, on every panel.
     *
     * @param  class-string  $searchClass
     * @param  class-string<FilamentResource>  $resourceClass
     */
    public static function resource(string $searchClass, string $resourceClass): void
    {
        resolve(ResourceBindings::class)->register($searchClass, $resourceClass);
    }

    #[\Override]
    protected static function getFacadeAccessor(): string
    {
        return ResourceBindings::class;
    }
}
