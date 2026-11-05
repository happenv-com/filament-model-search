<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Support;

use Happenv\ModelSearch\Query\SearchMatch;

/**
 * The "what matched" line of a result, shared by every surface that shows one.
 */
final class MatchLine
{
    /**
     * The label and the value to show, or null when there is nothing to add.
     *
     * When the core hid the value (the viewer may not see it) the label alone is told, under "Matched by";
     * a value equal to the record's title adds nothing.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function of(SearchMatch $match, string $title): ?array
    {
        if ($match->value === null) {
            return [trans('filament-model-search::match-line.matched_by'), $match->label];
        }

        return $match->value === $title ? null : [$match->label, $match->value];
    }
}
