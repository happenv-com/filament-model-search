<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Support;

use Happenv\ModelSearch\Definition\ModelSearch;
use InvalidArgumentException;

/**
 * The arguments every `modelSearch()` macro takes, checked once.
 */
final class SearchArguments
{
    /**
     * @param  array<array-key, mixed>|null  $only
     * @return list<string>|null The kinds to restrict the search to; null for every kind (also for an empty list).
     *
     * @throws InvalidArgumentException when the class is not a search definition, or a kind is not a string
     */
    public static function validate(?string $searchClass, ?array $only): ?array
    {
        if ($searchClass !== null && ! is_subclass_of($searchClass, ModelSearch::class)) {
            throw new InvalidArgumentException(sprintf('%s is not a search definition: it must extend %s.', $searchClass, ModelSearch::class));
        }

        foreach ($only ?? [] as $kind) {
            if (! is_string($kind)) {
                throw new InvalidArgumentException('The kinds given to modelSearch() must be strings.');
            }
        }

        return $only === null || $only === [] ? null : array_values($only);
    }
}
