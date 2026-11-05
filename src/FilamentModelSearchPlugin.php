<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch;

use Filament\Contracts\Plugin;
use Filament\Panel;

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

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}
}
