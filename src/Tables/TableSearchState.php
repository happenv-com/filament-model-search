<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tables;

use Happenv\FilamentModelSearch\Support\SearchOutcome;

/**
 * What the table search of the current render came back with, so the empty state can say why there are no rows.
 *
 * One instance per configured table, so nothing is shared between workers or users. It holds an outcome, never the phrase.
 */
final class TableSearchState
{
    private ?SearchOutcome $outcome = null;

    public function record(?SearchOutcome $outcome): void
    {
        $this->outcome = $outcome;
    }

    public function outcome(): ?SearchOutcome
    {
        return $this->outcome;
    }
}
