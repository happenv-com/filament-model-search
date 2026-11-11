<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Rows;

/**
 * A service the fixture row asks the container for, so a test can tell the row method was called through it.
 */
final readonly class RowLabel
{
    public function __construct(public string $text = 'DEFAULT-LABEL') {}
}
