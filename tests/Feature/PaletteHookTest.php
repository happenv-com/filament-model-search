<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\FilamentModelSearch\FilamentModelSearchPlugin;
use Happenv\FilamentModelSearch\Livewire\RecordPalette;

covers(FilamentModelSearchPlugin::class);

beforeEach(function (): void {
    config(['filament-model-search.readiness_cache_seconds' => 0]);

    // A request boots the panel, which registers its render hooks; this test renders the hooks without a request.
    Filament::getPanel('admin')->boot();
});

it('puts the trigger in the topbar and the palette at the end of the body', function (): void {
    $this->signIn(['orders.viewAny', 'orders.view']);

    expect((string) FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE))->toContain(trans('filament-model-search::record-palette.trigger'))
        ->and((string) FilamentView::renderHook(PanelsRenderHook::BODY_END))->toContain('filament-model-search.record-palette')->toContain('mod-shift-k');
});

it('opens the palette under the one modal id the trigger names', function (): void {
    $this->signIn(['orders.viewAny', 'orders.view']);

    // The trigger is a Blade component tag, where a directive such as @js stays literal text: the id must reach the browser.
    expect((string) FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE))->toContain('open-modal')->toContain(RecordPalette::MODAL_ID)->not->toContain('@js')
        ->and((string) FilamentView::renderHook(PanelsRenderHook::BODY_END))->toContain(RecordPalette::MODAL_ID);
});

it('writes the configured keybindings', function (): void {
    config(['filament-model-search.palette.keybindings' => ['mod+k', 'g+p']]);
    $this->signIn(['orders.viewAny', 'orders.view']);

    expect((string) FilamentView::renderHook(PanelsRenderHook::BODY_END))->toContain('mod-k')->toContain('g-p')->not->toContain('mod-shift-k');
});

it('offers nothing when the palette is switched off in the config', function (): void {
    config(['filament-model-search.palette.enabled' => false]);
    $this->signIn(['orders.viewAny', 'orders.view']);

    expect((string) FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE))->toBe('')
        ->and((string) FilamentView::renderHook(PanelsRenderHook::BODY_END))->toBe('');
});

it('offers nothing when the plugin switches it off, even after the panel booted', function (): void {
    $this->signIn(['orders.viewAny', 'orders.view']);
    $plugin = Filament::getPanel('admin')->getPlugin('filament-model-search');
    assert($plugin instanceof FilamentModelSearchPlugin);
    $plugin->palette(false);

    try {
        expect((string) FilamentView::renderHook(PanelsRenderHook::BODY_END))->toBe('');
    } finally {
        $plugin->palette();
    }
});

it('offers nothing to a guest or to a user who may open no searched resource, evaluated per page', function (): void {
    expect((string) FilamentView::renderHook(PanelsRenderHook::BODY_END))->toBe('');

    $this->signIn([]);
    expect((string) FilamentView::renderHook(PanelsRenderHook::BODY_END))->toBe('');

    $this->signIn(['customers.viewAny', 'customers.update']);
    expect((string) FilamentView::renderHook(PanelsRenderHook::BODY_END))->toContain('filament-model-search.record-palette');
});

it('hooks the palette once per panel however often the plugin registers', function (): void {
    $panel = Panel::make()->id('registered-twice');
    $plugin = FilamentModelSearchPlugin::make();

    $plugin->register($panel);
    $plugin->register($panel);

    $hooks = (fn (): array => $this->renderHooks)->call($panel);

    // The trigger and the component: two hooks, not four.
    expect(array_sum(array_map(static fn (array $scoped): int => array_sum(array_map(count(...), $scoped)), $hooks)))->toBe(2);
});

describe('the built-in trigger', function (): void {
    it('is hidden by the plugin option while the modal and the shortcut stay', function (): void {
        $this->signIn(['orders.viewAny', 'orders.view']);
        $plugin = Filament::getPanel('admin')->getPlugin('filament-model-search');
        assert($plugin instanceof FilamentModelSearchPlugin);
        $plugin->paletteTrigger(false);

        try {
            expect((string) FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE))->toBe('')
                ->and((string) FilamentView::renderHook(PanelsRenderHook::BODY_END))
                ->toContain('filament-model-search.record-palette')->toContain('mod-shift-k')->toContain(RecordPalette::MODAL_ID);
        } finally {
            $plugin->paletteTrigger();
        }
    });

    it('is hidden by the config while the modal and the shortcut stay', function (): void {
        config(['filament-model-search.palette.trigger' => false]);
        $this->signIn(['orders.viewAny', 'orders.view']);

        expect((string) FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE))->toBe('')
            ->and((string) FilamentView::renderHook(PanelsRenderHook::BODY_END))->toContain('mod-shift-k');
    });

    it('lets the plugin option win over the config in both directions', function (): void {
        $this->signIn(['orders.viewAny', 'orders.view']);
        $plugin = Filament::getPanel('admin')->getPlugin('filament-model-search');
        assert($plugin instanceof FilamentModelSearchPlugin);

        try {
            config(['filament-model-search.palette.trigger' => false]);
            $plugin->paletteTrigger(true);
            expect((string) FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE))->toContain(trans('filament-model-search::record-palette.trigger'));

            config(['filament-model-search.palette.trigger' => true]);
            $plugin->paletteTrigger(false);
            expect((string) FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE))->toBe('');
        } finally {
            (fn () => $this->paletteTrigger = null)->call($plugin);
        }
    });
});

describe('FilamentModelSearchPlugin::isPaletteOffered()', function (): void {
    it('is true for a signed-in user who may open a searched resource', function (): void {
        $this->signIn(['orders.viewAny', 'orders.view']);

        expect(FilamentModelSearchPlugin::isPaletteOffered())->toBeTrue();
    });

    it('is true even when the built-in trigger is hidden', function (): void {
        config(['filament-model-search.palette.trigger' => false]);
        $this->signIn(['orders.viewAny', 'orders.view']);

        expect(FilamentModelSearchPlugin::isPaletteOffered())->toBeTrue();
    });

    it('is false when signed out', function (): void {
        expect(FilamentModelSearchPlugin::isPaletteOffered())->toBeFalse();
    });

    it('is false when the user may open no searched resource', function (): void {
        $this->signIn([]);

        expect(FilamentModelSearchPlugin::isPaletteOffered())->toBeFalse();
    });

    it('is false when the resource passes canAccess but not viewAny', function (): void {
        $this->signIn(['orders.view']);

        expect(FilamentModelSearchPlugin::isPaletteOffered())->toBeFalse();
    });

    it('is false when no resource is searched', function (): void {
        $this->signIn(['orders.viewAny', 'orders.view']);
        app()->instance(ResourceBindings::class, new ResourceBindings);

        expect(FilamentModelSearchPlugin::isPaletteOffered())->toBeFalse();
    });

    it('is false when the config switches the palette off', function (): void {
        config(['filament-model-search.palette.enabled' => false]);
        $this->signIn(['orders.viewAny', 'orders.view']);

        expect(FilamentModelSearchPlugin::isPaletteOffered())->toBeFalse();
    });

    it('is false when the plugin switches the palette off', function (): void {
        $this->signIn(['orders.viewAny', 'orders.view']);
        $plugin = Filament::getPanel('admin')->getPlugin('filament-model-search');
        assert($plugin instanceof FilamentModelSearchPlugin);
        $plugin->palette(false);

        try {
            expect(FilamentModelSearchPlugin::isPaletteOffered())->toBeFalse();
        } finally {
            $plugin->palette();
        }
    });
});
