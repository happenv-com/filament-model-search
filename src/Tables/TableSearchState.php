<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tables;

use Happenv\FilamentModelSearch\Support\SearchOutcome;
use Illuminate\Container\Attributes\Scoped;

/**
 * What the table search of the current render came back with, so the empty state can say why there are no rows.
 *
 * Scoped: one instance per request, never shared between workers or users. It holds an outcome, never the phrase.
 */
#[Scoped]
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
