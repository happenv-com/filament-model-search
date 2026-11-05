<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Support;

use Filament\Support\Contracts\ScalableIcon;
use Filament\Support\Enums\IconSize;

/**
 * Like the enums of icon-set packages: the value carries no set prefix, so only the size picks the SVG.
 */
enum SetIcon: string implements ScalableIcon
{
    case Users = 'users';

    public function getIconForSize(IconSize $size): string
    {
        return 'heroicon-o-'.$this->value;
    }
}
