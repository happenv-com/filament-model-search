<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Forms;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Support\Services\RelationshipJoiner;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\FilamentModelSearch\Support\HostScope;
use Happenv\FilamentModelSearch\Support\ParentScopes;
use Happenv\FilamentModelSearch\Support\ReadySearch;
use Happenv\FilamentModelSearch\Support\SearchArguments;
use Happenv\FilamentModelSearch\Support\SearchOutcome;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
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

        $kinds = SearchArguments::validate($searchClass, $only);

        return $select->getSearchResultsUsing(static fn (Select $component, #[\SensitiveParameter] ?string $search): array => self::search($component, (string) $search, $searchClass, $kinds, $stages) ?? $component->getSearchResultsFromRelationship($search));
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

        $offered = self::relationshipQuery($select, $relationship, $search);
        $limit = $offered->getQuery()->limit;

        if ($limit !== null) {
            $select->optionsLimit($limit);
        }

        $request = $searchClass::query($search)
            ->within(self::host($related, $offered), ParentScopes::for($related::class))
            ->forUser(Filament::auth()->user())
            ->locale(app()->getLocale())
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

        // Results that carry items are scoped and correct whatever the flags say (also while an index rebuilds);
        // a select has no room for a message, so an outcome without items is an empty list.
        $qualifiedKey = $select->getQualifiedRelatedKeyNameForRelationship($relationship);
        $keyName = Str::afterLast($qualifiedKey, '.');
        $ranked = [];

        foreach ($results as $result) {
            $key = $result->model->getAttribute($keyName);

            // A record without an option key cannot be offered.
            if ($key !== null) {
                $ranked[] = $key;
            }
        }

        if ($ranked === []) {
            return [];
        }

        // Labelled from the records the relationship query shapes (withCount, addSelect, joins), as Filament does.
        $labelled = self::relationshipQuery($select, $relationship, $search, $qualifiedKey, $ranked)->get()
            ->filter(static fn (Model $record): bool => $record->getAttribute($keyName) !== null)
            ->mapWithKeys(static fn (Model $record): array => [$record->getAttribute($keyName) => self::label($select, $record)]);

        $options = [];

        foreach ($ranked as $key) {
            $label = $labelled->get($key);

            if (is_string($label)) {
                $options[$key] = $label;
            }
        }

        return $options;
    }

    /**
     * What the relationship offers, built as Filament builds it, with the host's own modifications.
     *
     * @param  Relation<Model, Model, mixed>  $relationship
     * @param  list<mixed>|null  $keys  Restricts the query to these option keys, the way Filament loads selected values.
     * @return Builder<Model>
     */
    private static function relationshipQuery(Select $select, Relation $relationship, #[\SensitiveParameter] string $search, ?string $qualifiedKey = null, ?array $keys = null): Builder
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

        // After the callback, which may hand back a new query: the restriction must survive it.
        if ($keys !== null && $qualifiedKey !== null) {
            $query->whereIn($qualifiedKey, $keys);
        }

        if ($keys !== null) {
            $query->getQuery()->limit = null;
            $query->getQuery()->offset = null;
        }

        return $query;
    }

    /**
     * The host of the search: the related model cut down, by two semi-joins on its primary key, to what the
     * resource's scope offers and to what the relationship query offers. Each side keeps its own `orWhere`
     * inside its subquery, and no phrase is involved.
     *
     * @param  Builder<Model>  $relationshipQuery
     * @return Builder<Model>
     */
    private static function host(Model $related, Builder $relationshipQuery): Builder
    {
        $key = $related->getQualifiedKeyName();
        $host = $related->newQuery();

        $binding = resolve(ResourceBindings::class)->for($related::class);

        if ($binding !== null) {
            $host->whereIn($key, HostScope::of($binding->scope())->select($key));
        }

        $offered = HostScope::of($relationshipQuery);
        $offered->getQuery()->limit = null;
        $offered->getQuery()->offset = null;

        return $host->whereIn($key, $offered->select($key));
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
