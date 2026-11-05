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
     * a value equal to the record's title adds nothing. A match found through a related record is told by
     * the relation's name, which the caller gives in the viewer's language (the core's label of such a match
     * is the relation's own name): "Matched by: Customer", or "Customer › Email" when a value is shown.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function of(SearchMatch $match, string $title, ?string $relation = null): ?array
    {
        if ($match->value === null) {
            return [trans('filament-model-search::match-line.matched_by'), $relation ?? $match->label];
        }

        $label = $relation === null ? $match->label : $relation.' › '.$match->label;

        return $match->value === $title ? null : [$label, $match->value];
    }
}
