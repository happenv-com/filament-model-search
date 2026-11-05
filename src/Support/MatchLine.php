<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Support;

use Happenv\FilamentModelSearch\Binding\ResourceBinding;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\ModelSearch\Query\SearchMatch;
use Happenv\ModelSearch\Query\SearchResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Throwable;

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
     * A match found through a related record is reported by the core under the parent field, so its label is
     * the relation's own name and it carries a value when that field has no reveal rule. The caller gives the
     * relation's name in the viewer's language, and it replaces the core's label: "Matched by: Customer", or
     * "Customer: Alice" when the value is shown. Without it the core's label is used.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function of(SearchMatch $match, string $title, ?string $relation = null): ?array
    {
        if ($match->value === null) {
            return [trans('filament-model-search::match-line.matched_by'), $relation ?? $match->label];
        }

        return $match->value === $title ? null : [$relation ?? $match->label, $match->value];
    }

    /**
     * The line of a result, a match found through a related record told by what the related record is called.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function forResult(SearchResult $result, string $title): ?array
    {
        $via = $result->match->via;

        return self::of($result->match, $title, $via === null ? null : self::relationLabel($result->model, $via));
    }

    /**
     * What the relation is called to the user: the label of the resource that shows the related model,
     * else the relation's own name.
     */
    private static function relationLabel(Model $model, string $relation): string
    {
        try {
            $related = $model->{$relation}();
            $parent = $related instanceof Relation ? resolve(ResourceBindings::class)->for($related->getRelated()::class) : null;
        } catch (Throwable) {
            $parent = null;
        }

        return $parent instanceof ResourceBinding ? Str::ucfirst($parent->modelLabel()) : Str::headline($relation);
    }
}
