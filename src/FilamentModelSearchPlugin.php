<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\FilamentModelSearch\Livewire\RecordPalette;

/**
 * Registers the model search on a panel.
 *
 * The plugin only stores what it was told: nothing here depends on the request or on the user, because
 * a panel is registered once per worker.
 */
final class FilamentModelSearchPlugin implements Plugin
{
    public const string ID = 'filament-model-search';

    public const string PALETTE_COMPONENT = 'filament-model-search.record-palette';

    /** @var list<class-string> */
    private array $resources = [];

    private bool $palette = true;

    private ?bool $paletteTrigger = null;

    /** @var list<string> */
    private array $hookedPanels = [];

    public static function make(): static
    {
        return new self;
    }

    public function getId(): string
    {
        return self::ID;
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

    /**
     * Whether the built-in trigger button is drawn next to the global search. The modal and the keyboard shortcut stay
     * either way, so a host can open the palette from its own trigger. Overrides `palette.trigger` of the config.
     */
    public function paletteTrigger(bool $shown = true): self
    {
        $this->paletteTrigger = $shown;

        return $this;
    }

    public function isPaletteTriggerShown(): bool
    {
        return $this->paletteTrigger ?? config('filament-model-search.palette.trigger') !== false;
    }

    /**
     * Whether the palette is offered to the signed-in user on the current panel: switched on, a user signed in and at
     * least one searched resource the user may open (`canAccess() && canViewAny()`). The built-in trigger and the modal
     * ask this same rule on every render, so a host's own trigger can ask it too.
     */
    public static function isPaletteOffered(): bool
    {
        $panel = Filament::getCurrentPanel();

        if (! $panel instanceof Panel || ! $panel->hasPlugin(self::ID)) {
            return false;
        }

        $plugin = $panel->getPlugin(self::ID);

        return $plugin instanceof self && $plugin->paletteIsOffered();
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
        $panel->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn (): string => $this->isPaletteTriggerShown() && $this->paletteIsOffered()
            ? view('filament-model-search::components.palette-trigger', ['modalId' => RecordPalette::MODAL_ID])->render()
            : '');
        $panel->renderHook(PanelsRenderHook::BODY_END, fn (): string => $this->paletteIsOffered()
            ? view('filament-model-search::components.palette', ['component' => self::PALETTE_COMPONENT])->render()
            : '');
    }

    public function boot(Panel $panel): void {}

    /**
     * Whether this page offers the palette: switched on, a user signed in, and at least one resource the user may open to search.
     */
    private function paletteIsOffered(): bool
    {
        if (! $this->palette || config('filament-model-search.palette.enabled') !== true || ! Filament::auth()->check()) {
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
