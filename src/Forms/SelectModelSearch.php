<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Forms;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Support\Services\RelationshipJoiner;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\FilamentModelSearch\Support\HostScope;
use Happenv\FilamentModelSearch\Support\ReadySearch;
use Happenv\FilamentModelSearch\Support\SearchOutcome;
use Happenv\ModelSearch\Definition\ModelSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * `Select::modelSearch()`: the search of a relationship select answered by the index of the core.
 *
 * Call it after `relationship()`. Only the search is replaced: the labels of the values already
 * selected still come from the relationship. Until the type is ready the select searches natively.
 */
final class SelectModelSearch
{
    /**
     * Not static: Filament rebinds the macro closure to the select, which a static closure refuses.
     */
    public function register(): void
    {
        Select::macro('modelSearch', fn (?string $searchClass = null, ?array $only = null, bool $stages = true): Select => SelectModelSearch::configure($this, $searchClass, $only, $stages));
    }

    /**
     * @param  string|null  $searchClass  A search definition class; defaults to the one bound to the related model.
     * @param  array<array-key, mixed>|null  $only  Restricts the search to fields of these kinds; empty means every kind.
     * @param  bool  $stages  False searches every stage at once instead of the first one that answers.
     *
     * @throws LogicException when the select has no relationship yet
     * @throws InvalidArgumentException when the class is not a search definition, or a kind is not a string
     */
    public static function configure(Select $select, ?string $searchClass = null, ?array $only = null, bool $stages = true): Select
    {
        if (! $select->hasRelationship()) {
            throw new LogicException('modelSearch() needs a relationship: call ->relationship() on the select first.');
        }

        $kinds = self::validate($searchClass, $only);

        return $select->getSearchResultsUsing(static fn (Select $component, ?string $search): array => self::search($component, (string) $search, $searchClass, $kinds, $stages) ?? $component->getSearchResultsFromRelationship($search));
    }

    /**
     * @param  array<array-key, mixed>|null  $only
     * @return list<string>|null
     *
     * @throws InvalidArgumentException
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

    /**
     * The options for a phrase, or null while the index does not serve the related model (search natively then).
     *
     * @param  list<string>|null  $kinds
     * @return array<string|int, string>|null
     */
    public static function search(Select $select, #[\SensitiveParameter] string $search, ?string $searchClass, ?array $kinds, bool $stages): ?array
    {
        if (trim($search) === '') {
            return null;
        }

        /** @var Relation<Model, Model, mixed>|null $relationship The optional through-relation package may be absent, so the union is not trusted. */
        $relationship = Relation::noConstraints($select->getRelationship(...));

        if (! $relationship instanceof Relation) {
            return null;
        }

        $related = $relationship->getRelated();

        if ($searchClass !== null && $searchClass::model() !== $related::class) {
            throw new InvalidArgumentException(sprintf('%s searches %s but the select offers %s.', $searchClass, $searchClass::model(), $related::class));
        }

        $searchClass = ReadySearch::for($related::class, $searchClass);

        if ($searchClass === null) {
            return null;
        }

        $subquery = self::relationshipQuery($select, $relationship, $search);
        $limit = $subquery->getQuery()->limit;

        if ($limit !== null) {
            $select->optionsLimit($limit);
        }

        $qualifiedKey = $select->getQualifiedRelatedKeyNameForRelationship($relationship);

        $request = $searchClass::query($search)
            ->within(self::host($related, $subquery, $qualifiedKey))
            ->forUser(Filament::auth()->user())
            ->limit($select->getOptionsLimit());

        if ($kinds !== null) {
            $request = $request->only($kinds);
        }

        if (! $stages) {
            $request = $request->withoutStages();
        }

        try {
            $results = $request->get();
        } catch (Throwable $exception) {
            if (! SearchOutcome::fromException($exception) instanceof SearchOutcome) {
                throw $exception;
            }

            return [];
        }

        // A select has no room for a message: anything but results is an empty list.
        if (SearchOutcome::fromResults($results) !== SearchOutcome::Results) {
            return [];
        }

        $keyName = substr(strrchr('.'.$qualifiedKey, '.') ?: $qualifiedKey, 1);
        $options = [];

        foreach ($results as $result) {
            $options[$result->model->getAttribute($keyName)] = self::label($select, $result->model);
        }

        return $options;
    }

    /**
     * What the relationship offers, built as Filament builds it, with the host's own modifications.
     *
     * @param  Relation<Model, Model, mixed>  $relationship
     * @return Builder<Model>
     */
    private static function relationshipQuery(Select $select, Relation $relationship, #[\SensitiveParameter] string $search): Builder
    {
        $query = app(RelationshipJoiner::class)->prepareQueryForNoConstraints($relationship);

        // Closure::call binds $this to the select, so its protected settings can be read back.
        [$modify, $ignoreRecord] = (fn (): array => [$this->modifyRelationshipQueryUsing, $this->shouldIgnoreRelationshipRecord])->call($select);

        if ($ignoreRecord && ($record = $select->getRecord()) instanceof Model) {
            $query->where($record->getQualifiedKeyName(), '!=', $record->getKey());
        }

        if ($modify !== null) {
            $query = $select->evaluate($modify, ['query' => $query, 'search' => $search]) ?? $query;
        }

        return $query;
    }

    /**
     * The host of the search: the resource's scope of the related model (or the whole model), cut down to
     * the keys the relationship query offers. A semi-join keeps an `orWhere` of the relationship query from
     * leaking out of its own group, and carries no phrase.
     *
     * @param  Builder<Model>  $relationshipQuery
     * @return Builder<Model>
     */
    private static function host(Model $related, Builder $relationshipQuery, string $qualifiedKey): Builder
    {
        $binding = resolve(ResourceBindings::class)->for($related::class);
        $host = $binding !== null ? HostScope::of($binding->scope()) : $related->newQuery();

        $offered = HostScope::of($relationshipQuery);
        $offered->getQuery()->limit = null;
        $offered->getQuery()->offset = null;

        return $host->whereIn($related->getQualifiedKeyName(), $offered->select($qualifiedKey));
    }

    /**
     * The label the relationship would have given the record.
     */
    private static function label(Select $select, Model $record): string
    {
        if ($select->hasOptionLabelFromRecordUsingCallback()) {
            return $select->getOptionLabelFromRecord($record);
        }

        $attribute = (string) $select->getRelationshipTitleAttribute();
        $attribute = str_replace('->', '.', explode(' as ', $attribute)[0]);

        return (string) data_get($record, $attribute);
    }
}
