<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;

/**
 * Registers the model search on a panel.
 *
 * The plugin only stores what it was told: nothing here depends on the request or on the user, because
 * a panel is registered once per worker.
 */
final class FilamentModelSearchPlugin implements Plugin
{
    /** @var list<class-string> */
    private array $resources = [];

    private bool $palette = true;

    public static function make(): static
    {
        return new self;
    }

    public function getId(): string
    {
        return 'filament-model-search';
    }

    /**
     * @param  list<class-string>  $resourceClasses
     */
    public function resources(array $resourceClasses): self
    {
        $this->resources = $resourceClasses;

        return $this;
    }

    /**
     * @return list<class-string>
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    public function palette(bool $enabled = true): self
    {
        $this->palette = $enabled;

        return $this;
    }

    public function isPaletteEnabled(): bool
    {
        return $this->palette;
    }

    public function register(Panel $panel): void
    {
        // Idempotent: a panel may be registered again, and the bindings hold class names only.
        $bindings = resolve(ResourceBindings::class);

        foreach ($this->resources as $resource) {
            $bindings->registerResource($resource, $panel->getId());
        }
    }

    public function boot(Panel $panel): void {}
}
