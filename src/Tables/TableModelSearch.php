<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tables;

use Filament\Facades\Filament;
use Filament\Tables\Table;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\FilamentModelSearch\Support\SearchOutcome;
use Happenv\ModelSearch\Definition\ModelSearch;
use Happenv\ModelSearch\Facades\ModelSearches;
use Illuminate\Contracts\Support\Htmlable;
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
        Table::macro('modelSearch', fn (?string $searchClass = null, ?array $only = null): Table => TableModelSearch::configure($this, $searchClass, $only));
    }

    /**
     * Call it after the table's query and after any empty state of your own: the heading and the description
     * you set earlier are kept and shown whenever the search did not fail.
     *
     * @param  string|null  $searchClass  A search definition class; defaults to the one bound to the table's model.
     * @param  array<array-key, mixed>|null  $only  Restricts the search to fields of these kinds; empty means every kind.
     *
     * @throws InvalidArgumentException when the class is not a definition of the table's model, or a kind is not a string
     */
    public static function configure(Table $table, ?string $searchClass = null, ?array $only = null): Table
    {
        if ($searchClass !== null) {
            if (! is_subclass_of($searchClass, ModelSearch::class)) {
                throw new InvalidArgumentException(sprintf('%s is not a search definition: it must extend %s.', $searchClass, ModelSearch::class));
            }

            $tableModel = $table->getModel();

            if ($tableModel !== null && $searchClass::model() !== $tableModel) {
                throw new InvalidArgumentException(sprintf('%s searches %s but the table shows %s.', $searchClass, $searchClass::model(), $tableModel));
            }
        }

        foreach ($only ?? [] as $kind) {
            if (! is_string($kind)) {
                throw new InvalidArgumentException('The kinds given to modelSearch() must be strings.');
            }
        }

        $kinds = $only === null || $only === [] ? null : array_values($only);
        /** @var list<string>|null $kinds */
        $state = new TableSearchState;

        $searchClass = self::searchClass($table, $searchClass);

        if ($searchClass === null) {
            return $table;
        }

        // Closure::call binds $this to the table, so its protected empty state can be read back.
        $previous = (fn (): array => [$this->emptyStateHeading, $this->emptyStateDescription])->call($table);

        $table
            ->searchable()
            ->searchUsing(static function (Builder $query, #[\SensitiveParameter] string $search) use ($searchClass, $kinds, $state): void {
                self::narrow($query, $search, $searchClass, $kinds, $state);
            })
            ->emptyStateHeading(static fn ($livewire): string|Htmlable|null => self::describe($livewire, $state, heading: true) ?? $table->evaluate($previous[0]))
            ->emptyStateDescription(static fn ($livewire): string|Htmlable|null => self::describe($livewire, $state, heading: false) ?? $table->evaluate($previous[1]));

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
        // Its columns are reset too: a select (withCount, addSelect) changes what a row carries, not which rows are in scope.
        $scope = $query->clone()->reorder();
        $scope->getQuery()->columns = null;
        $scope->getQuery()->setBindings([], 'select');

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
