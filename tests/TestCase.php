<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Happenv\FilamentModelSearch\FilamentModelSearchServiceProvider;
use Happenv\FilamentModelSearch\Tests\Fixtures\AdminPanelProvider;
use Happenv\FilamentModelSearch\Tests\Fixtures\FixtureSearchServiceProvider;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\User;
use Happenv\ModelSearch\ModelSearchServiceProvider;
use Happenv\ModelSearch\Postgres\Indexing\IndexState;
use Happenv\ModelSearch\Postgres\Indexing\SearchIndexer;
use Happenv\ModelSearch\Registry\ModelSearchRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // The whole test runs in one transaction: that is the baseline a drain may run at.
        config(['model-search.drain.allowed_transaction_level' => 1]);
    }

    /**
     * Brings the index up to date with the rows the test created, installing the types that were not.
     */
    protected function indexAll(): void
    {
        $registry = resolve(ModelSearchRegistry::class);
        $state = new IndexState;

        foreach ($registry->all() as $definition) {
            if ($state->find($definition->morphAlias) === null) {
                $state->recordInstall($definition);
            }
        }

        (new SearchIndexer($registry, $state))->drainNow();
    }

    protected function signIn(bool $canViewAny = true): User
    {
        $user = User::query()->create([
            'name' => 'Tester',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret',
            'can_view_any' => $canViewAny,
        ]);

        $this->actingAs($user);

        return $user;
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            ModelSearchServiceProvider::class,
            FilamentModelSearchServiceProvider::class,
            FixtureSearchServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'postgres'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', 'postgres'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/Migrations');
    }
}
