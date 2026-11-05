<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tables;

use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Happenv\FilamentModelSearch\Forms\SelectModelSearch;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * `SelectFilter::modelSearch()`: the search of a relationship filter answered by the index of the core.
 *
 * Same rules as `Select::modelSearch()`; the "no relationship" option of the filter is kept.
 */
final class SelectFilterModelSearch
{
    /**
     * Not static: Filament rebinds the macro closure to the filter, which a static closure refuses.
     */
    public function register(): void
    {
        SelectFilter::macro('modelSearch', fn (?string $searchClass = null, ?array $only = null, bool $stages = true): SelectFilter => SelectFilterModelSearch::configure($this, $searchClass, $only, $stages));
    }

    /**
     * @param  array<array-key, mixed>|null  $only
     *
     * @throws LogicException when the filter has no relationship yet
     * @throws InvalidArgumentException
     */
    public static function configure(SelectFilter $filter, ?string $searchClass = null, ?array $only = null, bool $stages = true): SelectFilter
    {
        if (! $filter->hasRelationship()) {
            throw new LogicException('modelSearch() needs a relationship: call ->relationship() on the filter first.');
        }

        $kinds = SelectModelSearch::validate($searchClass, $only);

        return $filter->getSearchResultsUsing(static function (Select $component, ?string $search) use ($filter, $searchClass, $kinds, $stages): array {
            $results = SelectModelSearch::search($component, (string) $search, $searchClass, $kinds, $stages);

            if ($results === null) {
                return $filter->getSearchResultsFromRelationship($component, $search);
            }

            if ($filter->hasEmptyRelationshipOption() && str($filter->getEmptyRelationshipOptionLabel())->lower()->contains(Str::lower((string) $search))) {
                // The key is a protected constant of the filter; the same key is what its query recognises.
                $key = (fn (): string => constant('static::EMPTY_RELATIONSHIP_OPTION_KEY'))->call($filter);

                return [$key => $filter->getEmptyRelationshipOptionLabel()] + $results;
            }

            return $results;
        });
    }
}
