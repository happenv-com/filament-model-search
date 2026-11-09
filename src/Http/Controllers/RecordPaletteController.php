<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Http\Controllers;

use Happenv\FilamentModelSearch\FilamentModelSearchPlugin;
use Illuminate\Http\Response;
use Livewire\Livewire;

/**
 * The palette itself, asked for the first time it is called on a page: nothing of it is in the page before that.
 *
 * The same rule as the page's loader decides who gets it, asked again here because the user may have changed.
 */
final class RecordPaletteController
{
    public function __invoke(): Response
    {
        abort_unless(FilamentModelSearchPlugin::isPaletteOffered(), 403);

        return response((string) Livewire::mount(FilamentModelSearchPlugin::PALETTE_COMPONENT))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'no-store, private');
    }
}
