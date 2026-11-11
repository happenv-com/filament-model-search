<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Feature;

use Happenv\FilamentModelSearch\FilamentModelSearchServiceProvider;
use Happenv\FilamentModelSearch\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FilamentModelSearchServiceProvider::class)]
final class FilamentModelSearchServiceProviderWithoutMigrationsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('filament-model-search.palette.history.run_migrations', false);
    }

    public function test_it_does_not_load_the_history_migration_when_the_host_runs_its_own(): void
    {
        $paths = array_map(realpath(...), $this->app->make('migrator')->paths());

        $this->assertNotContains(realpath(__DIR__.'/../../database/migrations'), $paths);
    }
}
