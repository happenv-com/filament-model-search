<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\PhpStan\Consumers;

use Filament\Resources\Resource;
use Happenv\FilamentModelSearch\Concerns\InteractsWithModelSearch;

/**
 * Exists only so PHPStan analyses the {@see InteractsWithModelSearch} trait body, which it reaches
 * through a using class. Not autoloaded and not shipped.
 */
abstract class ResourceConsumer extends Resource
{
    use InteractsWithModelSearch;
}
