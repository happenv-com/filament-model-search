<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Concerns;

/**
 * Lets a resource name the search definition it shows. The panel plugin binds it when the resource is
 * listed in `FilamentModelSearchPlugin::resources()`.
 */
trait InteractsWithModelSearch
{
    /**
     * @return class-string
     */
    abstract public static function modelSearch(): string;
}
