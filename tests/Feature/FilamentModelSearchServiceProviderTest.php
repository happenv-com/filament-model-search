<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Feature;

use Happenv\FilamentModelSearch\FilamentModelSearchServiceProvider;
use Happenv\FilamentModelSearch\Tests\TestCase;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FilamentModelSearchServiceProvider::class)]
final class FilamentModelSearchServiceProviderTest extends TestCase
{
    public function test_it_loads_the_history_migration_by_default_and_publishes_it(): void
    {
        $this->assertContains(realpath(__DIR__.'/../../database/migrations'), $this->migrationPaths());
        $this->assertNotEmpty(ServiceProvider::pathsToPublish(FilamentModelSearchServiceProvider::class, 'filament-model-search-migrations'));
    }

    /**
     * @return list<string|false>
     */
    private function migrationPaths(): array
    {
        return array_map(realpath(...), $this->app->make('migrator')->paths());
    }
}
