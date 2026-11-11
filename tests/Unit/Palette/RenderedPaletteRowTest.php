<?php

declare(strict_types=1);

use Happenv\FilamentModelSearch\Palette\RenderedPaletteRow;

covers(RenderedPaletteRow::class);

it('reads the slots as plain text and finds a value in it, whatever its case and spacing', function (): void {
    $row = new RenderedPaletteRow(
        belowTitle: '<div class="x"><span>Anna  <b>Nowak</b></span></div>',
        trailing: '<div>249,00&nbsp;z&#322;</div>',
    );

    expect($row->shows('anna nowak'))->toBeTrue()
        ->and($row->shows('ANNA'))->toBeTrue()
        ->and($row->shows('Nowak 249'))->toBeTrue()
        ->and($row->shows('Jan'))->toBeFalse()
        ->and($row->shows('  '))->toBeFalse();
});

it('is empty only when every slot is', function (): void {
    expect(new RenderedPaletteRow)->isEmpty()->toBeTrue()
        ->and(new RenderedPaletteRow(trailing: '<div>x</div>'))->isEmpty()->toBeFalse();
});
