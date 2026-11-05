<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * What the core is given as the host's scope: the rows in play, with nothing about how they are shown.
 */
final class HostScope
{
    /**
     * A copy of the query without orderings and without the columns it selects.
     *
     * The core snapshots what it is given and refuses orderings; a select (withCount, addSelect)
     * changes what a row carries, not which rows are in scope.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function of(Builder $query): Builder
    {
        $scope = $query->clone()->reorder();
        $scope->getQuery()->columns = null;
        $scope->getQuery()->setBindings([], 'select');

        return $scope;
    }
}
