<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Rows;

use Happenv\FilamentModelSearch\Palette\SearchResultRow;

/**
 * A row class whose slots tests describe through `fixtures.order_row`: slot name => columns, or a closure returning them.
 */
final class ConfiguredOrderRow extends SearchResultRow
{
    public function with(): array
    {
        return $this->slot('with');
    }

    public function leading(): array
    {
        return $this->slot('leading');
    }

    public function aboveTitle(): array
    {
        return $this->slot('aboveTitle');
    }

    public function besideTitle(): array
    {
        return $this->slot('besideTitle');
    }

    public function belowTitle(): array
    {
        return $this->slot('belowTitle');
    }

    public function trailing(): array
    {
        return $this->slot('trailing');
    }

    /**
     * @return array<int, mixed>
     */
    private function slot(string $name): array
    {
        $slots = config('fixtures.order_row');

        return is_array($slots) && isset($slots[$name]) ? value($slots[$name]) : [];
    }
}
