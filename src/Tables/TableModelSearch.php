<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tables;

use Filament\Facades\Filament;
use Filament\Tables\Table;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\FilamentModelSearch\Support\SearchOutcome;
use Happenv\ModelSearch\Definition\ModelSearch;
use Happenv\ModelSearch\Facades\ModelSearches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Throwable;

/**
 * `Table::modelSearch()`: the table's global search answered by the index of the core.
 *
 * Until the type is ready the table is left exactly as it was. Search per column is never touched.
 */
final class TableModelSearch
{
    /**
     * Not static: Filament rebinds the macro closure to the table, which a static closure refuses.
     */
    public function register(): void
    {
        Table::macro('modelSearch', function (?string $searchClass = null, ?array $only = null): Table {
            /** @var Table $this */
            return TableModelSearch::configure($this, $searchClass, $only); // @phpstan-ignore varTag.nativeType (Filament rebinds the macro closure to the table)
        });
    }

    /**
     * @param  string|null  $searchClass  A search definition class; defaults to the one bound to the table's model.
     * @param  array<array-key, mixed>|null  $only  Restricts the search to fields of these kinds.
     */
    public static function configure(Table $table, ?string $searchClass = null, ?array $only = null): Table
    {
        if ($searchClass !== null && ! is_subclass_of($searchClass, ModelSearch::class)) {
            throw new InvalidArgumentException(sprintf('%s is not a search definition: it must extend %s.', $searchClass, ModelSearch::class));
        }

        $only = $only === null ? null : array_values(array_filter($only, is_string(...)));

        $state = resolve(TableSearchState::class);
        $state->record(null);

        $searchClass = self::searchClass($table, $searchClass);

        if ($searchClass === null) {
            return $table;
        }

        $table
            ->searchable()
            ->searchUsing(static function (Builder $query, #[\SensitiveParameter] string $search) use ($searchClass, $only, $state): void {
                self::narrow($query, $search, $searchClass, $only, $state);
            })
            ->emptyStateHeading(static fn ($livewire): ?string => self::describe($livewire, $state, heading: true))
            ->emptyStateDescription(static fn ($livewire): ?string => self::describe($livewire, $state, heading: false));

        return $table;
    }

    /**
     * The definition that serves the table, or null while the index does not serve its model yet.
     *
     * @return class-string<ModelSearch>|null
     */
    private static function searchClass(Table $table, ?string $searchClass): ?string
    {
        $model = $searchClass !== null ? $searchClass::model() : $table->getModel();

        if ($model === null || ! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            return null;
        }

        /** @var class-string<Model> $model */
        $binding = resolve(ResourceBindings::class)->for($model);
        $searchClass ??= $binding?->searchClass();

        if ($searchClass === null || ! is_subclass_of($searchClass, ModelSearch::class)) {
            return null;
        }

        $ready = $binding !== null && $binding->searchClass() === $searchClass
            ? $binding->isReady()
            : ModelSearches::isReady($model);

        return $ready ? $searchClass : null;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  class-string<ModelSearch>  $searchClass
     * @param  list<string>|null  $only
     */
    private static function narrow(Builder $query, #[\SensitiveParameter] string $search, string $searchClass, ?array $only, TableSearchState $state): void
    {
        $state->record(null);

        // The core snapshots what it is given, so the scope never sees the narrowing; an ordering has no meaning in a scope.
        $scope = $query->clone()->reorder();

        $request = $searchClass::query($search)
            ->within($scope)
            ->forUser(Filament::auth()->user());

        if ($only !== null) {
            $request = $request->only($only);
        }

        try {
            $request->apply($query);
        } catch (Throwable $exception) {
            $outcome = SearchOutcome::fromException($exception);

            if (! $outcome instanceof SearchOutcome) {
                throw $exception;
            }

            $state->record($outcome);
            self::matchNothing($query);
        }
    }

    /**
     * Zero rows whatever the table query held: an earlier `orWhere` would otherwise survive an `AND false`.
     *
     * @param  Builder<Model>  $query
     */
    private static function matchNothing(Builder $query): void
    {
        $base = $query->getQuery();
        $base->wheres = [];
        $base->setBindings([], 'where');

        $query->whereRaw('1 = 0');
    }

    private static function describe(mixed $livewire, TableSearchState $state, bool $heading): ?string
    {
        $outcome = self::outcomeFor($livewire, $state);

        if (! $outcome instanceof SearchOutcome) {
            return null;
        }

        return trans($heading ? $outcome->heading() : $outcome->description());
    }

    private static function outcomeFor(mixed $livewire, TableSearchState $state): ?SearchOutcome
    {
        $searching = is_object($livewire) && method_exists($livewire, 'getTableSearch') && filled($livewire->getTableSearch());

        return $searching ? $state->outcome() : null;
    }
}
