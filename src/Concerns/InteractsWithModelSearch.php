<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Concerns;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\FilamentModelSearch\Support\HostScope;
use Happenv\FilamentModelSearch\Support\ReadySearch;
use Happenv\FilamentModelSearch\Support\SearchOutcome;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Lets a resource name the search definition it shows. The panel plugin binds it when the resource is
 * listed in `FilamentModelSearchPlugin::resources()`.
 *
 * The resource's global search is answered by the index once the type is ready; until then it keeps
 * Filament's own search. The resource is never searched for a user who may not open it.
 */
trait InteractsWithModelSearch
{
    /**
     * @return class-string
     */
    abstract public static function modelSearch(): string;

    /**
     * @return Collection<int, GlobalSearchResult>
     */
    #[\Override]
    public static function getGlobalSearchResults(#[\SensitiveParameter] string $search): Collection
    {
        $binding = resolve(ResourceBindings::class)->for(static::getModel());

        // Not bound here (another panel, another resource of the model): Filament's own search, as before.
        if ($binding === null || $binding->resourceClass() !== static::class) {
            return parent::getGlobalSearchResults($search);
        }

        // Asked on every call: the resource is registered once per worker, the user changes.
        if (! $binding->isAccessible()) {
            return new Collection;
        }

        $searchClass = ReadySearch::for(static::getModel());

        if ($searchClass === null) {
            return parent::getGlobalSearchResults($search);
        }

        $scope = static::getGlobalSearchEloquentQuery();

        try {
            $results = $searchClass::query($search)
                ->within(HostScope::of($scope))
                ->forUser(Filament::auth()->user())
                ->limit(static::getGlobalSearchResultsLimit())
                ->get();
        } catch (Throwable $exception) {
            if (! SearchOutcome::fromException($exception) instanceof SearchOutcome) {
                throw $exception;
            }

            // Global search has no room for a message.
            return new Collection;
        }

        // Results that carry items are scoped and correct whatever the flags say; an empty answer is empty.
        $records = new EloquentCollection;

        foreach ($results as $result) {
            $records->push($result->model);
        }

        // What the title and details read, once for all records (the scope's own eager loads).
        $records->loadMissing($scope->getEagerLoads());

        $global = new Collection;

        foreach ($results as $result) {
            $record = $result->model;
            $url = static::getGlobalSearchResultUrl($record);

            if (blank($url)) {
                continue;
            }

            $title = static::getGlobalSearchResultTitle($record);
            $details = static::getGlobalSearchResultDetails($record);
            $match = $result->match;

            if ($match->value === null) {
                // The viewer may not see the value: what matched is still told, the value is not.
                $details[trans('filament-model-search::interacts-with-model-search.matched_by')] ??= $match->label;
            } elseif ($match->value !== ($title instanceof Htmlable ? strip_tags($title->toHtml()) : $title)) {
                // A value equal to the title adds nothing, and a native detail of the same name stays.
                $details[$match->label] ??= $match->value;
            }

            $global->push(new GlobalSearchResult(
                title: $title,
                url: $url,
                details: $details,
                actions: array_map(
                    static fn (Action $action): Action => $action->hasRecord() ? $action : $action->record($record),
                    static::getGlobalSearchResultActions($record),
                ),
            ));
        }

        return $global;
    }
}
