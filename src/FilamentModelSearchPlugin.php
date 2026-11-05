<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Illuminate\Support\Facades\Blade;

/**
 * Registers the model search on a panel.
 *
 * The plugin only stores what it was told: nothing here depends on the request or on the user, because
 * a panel is registered once per worker.
 */
final class FilamentModelSearchPlugin implements Plugin
{
    public const string PALETTE_COMPONENT = 'filament-model-search.record-palette';

    private const string PALETTE_MODAL_ID = 'filament-model-search-record-palette';

    /** @var list<class-string> */
    private array $resources = [];

    private bool $palette = true;

    /** @var list<string> */
    private array $hookedPanels = [];

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

        // Hooked once per panel, however often the plugin registers.
        if (in_array($panel->getId(), $this->hookedPanels, true)) {
            return;
        }

        $this->hookedPanels[] = $panel->getId();

        // Always hooked, decided when the page renders: the user, the config and the bindings may all differ from the boot.
        $panel->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn (): string => $this->paletteIsOffered()
            ? view('filament-model-search::components.palette-trigger', ['modalId' => self::PALETTE_MODAL_ID])->render()
            : '');
        $panel->renderHook(PanelsRenderHook::BODY_END, fn (): string => $this->paletteIsOffered()
            ? Blade::render('@livewire($component)', ['component' => self::PALETTE_COMPONENT])
            : '');
    }

    public function boot(Panel $panel): void {}

    /**
     * Whether this page offers the palette: switched on, and at least one resource the user may open to search.
     */
    private function paletteIsOffered(): bool
    {
        if (! $this->palette || config('filament-model-search.palette.enabled') !== true) {
            return false;
        }

        foreach (resolve(ResourceBindings::class)->all() as $binding) {
            if ($binding->isAccessible()) {
                return true;
            }
        }

        return false;
    }
}
