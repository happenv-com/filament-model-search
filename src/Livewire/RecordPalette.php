<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Livewire;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Support\Contracts\ScalableIcon;
use Filament\Support\Enums\IconSize;
use Happenv\FilamentModelSearch\Binding\ResourceBinding;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\FilamentModelSearch\History\PaletteHistory;
use Happenv\FilamentModelSearch\Palette\PaletteRowRenderer;
use Happenv\FilamentModelSearch\Palette\RenderedPaletteRow;
use Happenv\FilamentModelSearch\Palette\SearchResultRow;
use Happenv\FilamentModelSearch\Support\HostScope;
use Happenv\FilamentModelSearch\Support\MatchLine;
use Happenv\FilamentModelSearch\Support\PhraseKind;
use Happenv\FilamentModelSearch\Support\SearchOutcome;
use Happenv\ModelSearch\Definition\Mode;
use Happenv\ModelSearch\Definition\ModelSearch;
use Happenv\ModelSearch\Definition\SearchStage;
use Happenv\ModelSearch\Query\SearchQuery;
use Happenv\ModelSearch\Query\SearchResults;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * The record palette: one phrase, every resource the user may open, one answer.
 *
 * What was found lives in a locked property, so the client can read it but not rewrite it, and
 * `open()` accepts only what is in it. Access is asked again on every search and on every `open()`:
 * the bindings are registered once per worker, the user changes.
 *
 * @phpstan-type PaletteDetail array{label: string, value: string}
 * @phpstan-type PaletteItem array{id: string, title: string, url: string, exact: bool, hint: array{label: string, value: string}|null, details: list<PaletteDetail>, image: string|null, relatedTerm: string|null}
 * @phpstan-type PaletteGroup array{type: string, label: string, pluralLabel: string, icon: string|null, items: list<PaletteItem>, more: bool}
 * @phpstan-type RecentItem array{type: string, id: string, title: string, url: string, details: list<PaletteDetail>, image: string|null, icon: string|null}
 * @phpstan-type PaletteChip array{type: string, label: string, icon: string|null, current: bool}
 * @phpstan-type PaletteFound array{groups: list<PaletteGroup>, outcome: string|null, stage: bool, timedOut: bool, indexBuilding: bool, total: int, capacity: int}
 */
#[Isolate]
final class RecordPalette extends Component
{
    /** The id of the palette's modal: the trigger and the keybindings open it by this id. */
    public const string MODAL_ID = 'filament-model-search-record-palette';

    private const int MIN_LENGTH = 2;

    private const int MAX_LENGTH = 1000;

    public string $phrase = '';

    /**
     * The morph alias of the one resource the search is narrowed to, or null for every resource. Set only
     * through `scopeTo()`, and asked again on every search: a resource the user may no longer open, or that is
     * no longer offered under "Search in", widens it.
     */
    #[Locked]
    public ?string $scope = null;

    /**
     * What "Recently opened" shows right now: the pairs `open()` accepts besides the results. Filled while the field
     * is empty, from the history of the current user, and emptied once a phrase is typed.
     *
     * @var list<array{type: string, id: string}>
     */
    #[Locked]
    public array $recent = [];

    #[Locked]
    public bool $everyStage = false;

    #[Locked]
    public bool $similar = false;

    /** @var PaletteFound */
    #[Locked]
    public array $found = [
        'groups' => [],
        'outcome' => null,
        'stage' => false,
        'timedOut' => false,
        'indexBuilding' => false,
        'total' => 0,
        'capacity' => 0,
    ];

    /**
     * The slots of the rows, rendered from the records themselves: HTML never goes into `found`, which is the
     * snapshot the browser gets and sends back on every request. Filled by a search; a request that did not search
     * loads the records again by their keys.
     *
     * @var array<string, array<string, RenderedPaletteRow>> By morph alias, then by record key.
     */
    private array $renderedRows = [];

    public function updatedPhrase(#[\SensitiveParameter] mixed $value = null): void
    {
        $this->startOver();
    }

    public function searchEveryStage(): void
    {
        $this->everyStage = true;
        $this->search();
    }

    public function searchSimilar(): void
    {
        // The shortcut reaches here whether or not the button is offered: the same rule decides.
        if (! $this->offersSimilar()) {
            return;
        }

        $this->similar = true;
        $this->search();
    }

    /**
     * Narrows the search to one resource of "Search in", or back to every resource when it is the one already
     * chosen. Only a resource listed there is accepted: one the user may open and search, and that is offered.
     */
    public function scopeTo(string $type): void
    {
        if ($this->scope === $type) {
            $this->clearScope();

            return;
        }

        if (! $this->scopeChoice($type, $this->scopeChoices()) instanceof ResourceBinding) {
            return;
        }

        $this->scope = $type;
        $this->startOver();
    }

    public function clearScope(): void
    {
        if ($this->scope === null) {
            return;
        }

        $this->scope = null;
        $this->startOver();
    }

    /**
     * Puts in the field what a record of the current results names as its term, and searches everywhere for it:
     * the term leads away from the record, often to another resource. Only a term the record itself offered is
     * accepted, the client cannot search through this for anything else.
     */
    public function searchRelated(string $type, string $id): void
    {
        $term = $this->relatedTermOf($type, $id);

        if ($term === null) {
            return;
        }

        $this->phrase = $term;
        $this->scope = null;
        $this->startOver();
    }

    /**
     * Enter: with nothing found, widens the search when it can be widened; otherwise opens the record
     * when exactly one hit of the whole palette is an exact one.
     */
    public function submit(): void
    {
        if ($this->offersWiden()) {
            $this->searchSimilar();

            return;
        }

        $exact = [];

        foreach ($this->found['groups'] as $group) {
            foreach ($group['items'] as $item) {
                if ($item['exact']) {
                    $exact[] = [$group['type'], $item['id']];
                }
            }
        }

        if (count($exact) === 1) {
            $this->open($exact[0][0], $exact[0][1]);
        }
    }

    /**
     * Opens a record of the current results, when the user may still open it.
     */
    public function open(string $type, string $id): void
    {
        if (! $this->isFound($type, $id)) {
            return;
        }

        $binding = $this->bindingOfType($type);

        if (! $binding instanceof ResourceBinding || ! $binding->isAccessible()) {
            return;
        }

        $record = $binding->recordsInScope([$id])->first();

        $url = $record instanceof Model ? $binding->url($record) : null;

        if ($url !== null) {
            resolve(PaletteHistory::class)->record(Filament::auth()->user(), $type, (string) $record->getKey());

            $this->redirect($url, navigate: true);
        }
    }

    /**
     * Forgets what the current user opened from the palette.
     */
    public function clearHistory(): void
    {
        resolve(PaletteHistory::class)->clear(Filament::auth()->user());

        $this->recent = [];
    }

    public function render(): View
    {
        $found = $this->found;

        $phrase = $this->normalizedPhrase();
        $outcome = is_string($found['outcome']) ? SearchOutcome::tryFrom($found['outcome']) : null;

        $isShort = mb_strlen($phrase) < self::MIN_LENGTH;
        $offersWiden = $this->offersWiden();
        $identifierShaped = PhraseKind::isIdentifierShaped($phrase);

        // Asked once per render, for the list and for the label alike.
        $choices = $this->scopeChoices();
        $scoped = $this->scope === null ? null : $this->scopeChoice($this->scope, $choices);
        $listUrl = $scoped instanceof ResourceBinding && $found['groups'] !== [] ? $scoped->listUrl($phrase) : null;

        $recent = $isShort ? $this->recentOpened() : ['items' => [], 'rows' => []];
        $this->recent = array_map(static fn (array $item): array => ['type' => $item['type'], 'id' => $item['id']], $recent['items']);

        return view('filament-model-search::livewire.record-palette', [
            'recentItems' => $recent['items'],
            'recentRows' => $recent['rows'],
            'groups' => $found['groups'],
            'rows' => $this->rowsOf($found['groups']),
            'chips' => count($choices) >= 2 ? $this->chips($choices) : [],
            'listUrl' => $listUrl,
            'listLabel' => $scoped instanceof ResourceBinding ? $scoped->label() : null,
            'phraseShown' => $phrase,
            'outcome' => $outcome,
            'offersEveryStage' => $found['stage'] && ! $this->everyStage && ! $this->similar,
            'offersSimilar' => $this->offersSimilar(),
            'offersWiden' => $offersWiden,
            'emptyHeading' => $this->emptyHeading($outcome),
            'emptyDescription' => $this->emptyDescription($outcome, $offersWiden, $identifierShaped),
            'enterLabel' => $offersWiden ? trans('filament-model-search::record-palette.widen') : null,
            'timedOut' => $found['timedOut'],
            'indexBuilding' => $found['indexBuilding'],
            'isShort' => $isShort,
            'similar' => $this->similar,
            'placeholder' => $this->placeholder(),
            'debounce' => max(0, $this->configInt('palette.debounce_ms', 250)),
        ]);
    }

    /**
     * Similar names are offered for a phrase that has letters and is not an identifier, while the groups
     * are not already full. Never after they were asked for, and never on their own.
     */
    private function offersSimilar(): bool
    {
        $phrase = $this->normalizedPhrase();

        return ! $this->similar
            && $this->found['outcome'] !== SearchOutcome::Unavailable->value
            && mb_strlen($phrase) >= self::MIN_LENGTH
            && PhraseKind::hasLetter($phrase)
            && ! PhraseKind::isIdentifierShaped($phrase)
            && $this->found['total'] < $this->found['capacity'];
    }

    /**
     * Nothing was found and something can still be asked: the only step left is similar names, because
     * a type with no hit in its first stage has already been answered from all of them.
     */
    private function offersWiden(): bool
    {
        return $this->found['outcome'] === SearchOutcome::Empty->value && $this->offersSimilar();
    }

    private function emptyHeading(?SearchOutcome $outcome): string
    {
        return $outcome === SearchOutcome::Empty && $this->similar
            ? 'filament-model-search::record-palette.similar_empty_heading'
            : ($outcome?->heading() ?? '');
    }

    private function emptyDescription(?SearchOutcome $outcome, bool $offersWiden, bool $identifierShaped): string
    {
        return match (true) {
            $outcome !== SearchOutcome::Empty => $outcome?->description() ?? '',
            $this->similar => 'filament-model-search::record-palette.similar_empty_description',
            $offersWiden => 'filament-model-search::record-palette.widen_description',
            $identifierShaped => 'filament-model-search::record-palette.identifier_empty_description',
            default => $outcome->description(),
        };
    }

    /**
     * The translation key of the field's placeholder: the host names what its records are found by.
     */
    private function placeholder(): string
    {
        $placeholder = config('filament-model-search.palette.placeholder');

        return is_string($placeholder) && $placeholder !== '' ? $placeholder : 'filament-model-search::record-palette.placeholder';
    }

    /**
     * What the search can be narrowed to, in the order of the navigation: the chips under the field.
     *
     * @param  list<ResourceBinding>  $bindings  The current choices of the narrowing.
     * @return list<PaletteChip>
     */
    private function chips(array $bindings): array
    {
        usort($bindings, static fn (ResourceBinding $a, ResourceBinding $b): int => $a->sort() <=> $b->sort());

        return array_map(fn (ResourceBinding $binding): array => [
            'type' => $this->alias($binding),
            'label' => Str::ucfirst($binding->label()),
            'icon' => $this->iconName($binding->icon()),
            'current' => $this->alias($binding) === $this->scope,
        ], $bindings);
    }

    /**
     * A new question starts over: similar names are asked for, never carried along, and "results came from a
     * stage" is an answer to the phrase it was given for.
     */
    private function startOver(): void
    {
        $this->similar = false;
        $this->everyStage = false;
        $this->search();
    }

    /**
     * The resources of "Search in": offered there, the user may open them now and they have a search of their own.
     *
     * @return list<ResourceBinding>
     */
    private function scopeChoices(): array
    {
        return array_values(array_filter(
            resolve(ResourceBindings::class)->all(),
            static fn (ResourceBinding $binding): bool => $binding->offersScope()
                && $binding->isAccessible()
                && is_subclass_of($binding->searchClass(), ModelSearch::class),
        ));
    }

    /**
     * The one place that says whether a type may narrow the search right now.
     *
     * @param  list<ResourceBinding>  $choices  The current choices of "Search in".
     */
    private function scopeChoice(string $type, array $choices): ?ResourceBinding
    {
        foreach ($choices as $binding) {
            if ($this->alias($binding) === $type) {
                return $binding;
            }
        }

        return null;
    }

    private function search(): void
    {
        $this->renderedRows = [];

        $phrase = $this->normalizedPhrase();

        if (mb_strlen($phrase) < self::MIN_LENGTH) {
            $this->found = $this->nothing();

            return;
        }

        // Asked now, never remembered: the user may have changed since the palette was opened. A resource that can
        // no longer narrow the search (the user may not open it, or it left "Search in") widens it to every resource.
        $choices = $this->scopeChoices();
        $scoped = $this->scope === null ? null : $this->scopeChoice($this->scope, $choices);

        if (! $scoped instanceof ResourceBinding) {
            $this->scope = null;
        }

        $bindings = $scoped instanceof ResourceBinding ? [$scoped] : array_values(array_filter(
            resolve(ResourceBindings::class)->all(),
            static fn (ResourceBinding $binding): bool => $binding->isAccessible(),
        ));

        if ($bindings === []) {
            $this->found = [...$this->nothing(), 'outcome' => SearchOutcome::Unavailable->value];

            return;
        }

        $perGroup = $this->scope === null
            ? max(1, $this->configInt('palette.per_group', 5))
            : max(1, $this->configInt('palette.per_scope', 20));

        try {
            // One more than is shown, to know whether a group has more to show.
            // One more than is shown, to know whether a group has more to show.
            $answers = $this->answers($bindings, $phrase, $perGroup + 1);
        } catch (Throwable $exception) {
            $outcome = SearchOutcome::fromException($exception);

            if (! $outcome instanceof SearchOutcome) {
                throw $exception;
            }

            $this->found = [...$this->nothing(), 'outcome' => $outcome->value, 'capacity' => count($bindings) * $perGroup];

            return;
        }

        $this->found = $this->present($bindings, $answers, $perGroup, $choices);
    }

    /**
     * @param  list<ResourceBinding>  $bindings
     * @return array<string, SearchResults> By morph alias.
     */
    private function answers(array $bindings, #[\SensitiveParameter] string $phrase, int $perGroup): array
    {
        $user = Filament::auth()->user();
        $queries = [];

        foreach ($bindings as $binding) {
            $searchClass = $binding->searchClass();

            if (! is_subclass_of($searchClass, ModelSearch::class)) {
                continue;
            }

            $query = $searchClass::query($phrase)
                ->within(HostScope::of($binding->scope()))
                ->forUser($user)
                ->locale(app()->getLocale())
                ->limit($perGroup);

            $queries[$this->alias($binding)] = $this->everyStage ? $query->withoutStages() : $query;
        }

        if (! $this->similar) {
            return SearchQuery::palette(...array_values($queries));
        }

        // Typo tolerance is a question of its own, one per type, asked only because the user asked.
        return array_map(static fn (SearchQuery $query): SearchResults => $query->similar(), $queries);
    }

    /**
     * @param  list<ResourceBinding>  $bindings
     * @param  array<string, SearchResults>  $answers
     * @param  list<ResourceBinding>  $choices  What the search can be narrowed to, which decides who offers "show all".
     * @return PaletteFound
     */
    private function present(array $bindings, array $answers, int $perGroup, array $choices): array
    {
        $groups = [];
        $stage = false;
        $timedOut = false;
        $indexBuilding = false;
        $total = 0;

        foreach ($bindings as $order => $binding) {
            $results = $answers[$this->alias($binding)] ?? null;

            if (! $results instanceof SearchResults) {
                continue;
            }

            // Only a type that answered from its first stage has anything left to show: `all` has searched everything.
            $stage = $stage || ($results->stage !== null && $results->stage !== SearchStage::ALL);
            $timedOut = $timedOut || $results->timedOut;
            $indexBuilding = $indexBuilding || $results->indexBuilding;

            $group = $this->group($binding, $results, $perGroup, $this->scope === null && $this->scopeChoice($this->alias($binding), $choices) instanceof ResourceBinding);

            if ($group['items'] !== []) {
                $total += count($group['items']);
                $groups[] = ['order' => $order, 'sort' => $binding->sort(), 'exact' => in_array(true, array_column($group['items'], 'exact'), true), 'group' => $group];
            }
        }

        // Exact hits first, then the order of the navigation.
        usort($groups, static fn (array $a, array $b): int => [$b['exact'], $a['sort'], $a['order']] <=> [$a['exact'], $b['sort'], $b['order']]);

        // Items win over the flags: what came back is scoped and correct, the flags only warn. Without items
        // the flags are the answer, and an empty one is empty.
        $outcome = $total > 0
            ? SearchOutcome::Results
            : SearchOutcome::fromResults(new SearchResults(timedOut: $timedOut, indexBuilding: $indexBuilding));

        return [
            'groups' => array_map(static fn (array $entry): array => $entry['group'], $groups),
            'outcome' => $outcome->value,
            'stage' => $stage,
            'timedOut' => $timedOut,
            'indexBuilding' => $indexBuilding,
            'total' => $total,
            'capacity' => count($bindings) * $perGroup,
        ];
    }

    /**
     * @return PaletteGroup
     */
    private function group(ResourceBinding $binding, SearchResults $results, int $perGroup, bool $canNarrow): array
    {
        $items = [];

        // One more was asked for than is shown: its presence is the whole answer to "is there more".
        $hits = iterator_to_array($results, false);
        $more = count($hits) > $perGroup;
        $hits = array_slice($hits, 0, $perGroup);

        // What the details read, once for all records (the scope's own eager loads).
        $records = new EloquentCollection;

        foreach ($hits as $result) {
            $records->push($result->model);
        }

        $records->loadMissing($binding->scope()->getEagerLoads());

        $row = $binding->paletteRow();
        $rows = [];

        if ($row instanceof SearchResultRow) {
            $records->loadMissing($row->with());
            $rows = new PaletteRowRenderer($binding->resourceClass(), $row)->render($records);
            $this->renderedRows[$this->alias($binding)] = $rows;
        }

        foreach ($hits as $result) {
            $url = $binding->url($result->model);

            if (blank($url)) {
                continue;
            }

            $title = $binding->title($result->model);
            $title = $title instanceof Htmlable ? strip_tags($title->toHtml()) : $title;
            $line = MatchLine::forResult($result, $title);

            $details = $binding->details($result->model);

            $rendered = $rows[(string) $result->model->getKey()] ?? null;

            // A value the row already shows needs no line of its own: the details line, unless the slots replace
            // it, and whatever the slots say.
            $detailsShown = ! $rendered instanceof RenderedPaletteRow || $rendered->belowTitle === '';

            if ($line !== null && (
                ($detailsShown && in_array($line[1], array_column($details, 'value'), true))
                || ($rendered instanceof RenderedPaletteRow && $rendered->shows($line[1]))
            )) {
                $line = null;
            }

            $relatedTerm = $binding->relatedTerm($result->model);

            // A magnifier that would put back the phrase already searched would do nothing: it is not offered.
            if ($relatedTerm !== null && mb_strtolower(trim($relatedTerm)) === mb_strtolower($this->normalizedPhrase())) {
                $relatedTerm = null;
            }

            $items[] = [
                'id' => (string) $result->model->getKey(),
                'title' => $title,
                'url' => $url,
                'exact' => $result->match->mode === Mode::Exact,
                'hint' => $line === null ? null : ['label' => $line[0], 'value' => $line[1]],
                'details' => $details,
                'image' => $binding->image($result->model),
                'relatedTerm' => $relatedTerm,
            ];
        }

        $icon = $binding->icon();

        return [
            'type' => $this->alias($binding),
            'label' => Str::ucfirst($binding->label()),
            'pluralLabel' => $binding->label(),
            'icon' => $this->iconName($icon),
            'items' => $items,
            'more' => $more && $canNarrow,
        ];
    }

    /**
     * The records the current user opened from the palette, newest first, in the rows the results use. A record is
     * skipped when it was deleted, when the user may no longer open it, or when its resource is no longer bound,
     * accessible or shown by this panel: the history is a list of what can be opened now, not of what was.
     *
     * @return array{items: list<RecentItem>, rows: array<string, array<string, RenderedPaletteRow>>}
     */
    private function recentOpened(): array
    {
        $history = resolve(PaletteHistory::class);
        $entries = $history->recent(Filament::auth()->user());

        if ($entries === []) {
            return ['items' => [], 'rows' => []];
        }

        $keysByType = [];

        foreach ($entries as $entry) {
            $keysByType[$entry['type']][] = $entry['key'];
        }

        $limit = max(1, $this->configInt('palette.history.limit', 5));
        $records = [];
        $bindings = [];
        $rows = [];

        foreach ($keysByType as $type => $keys) {
            $binding = $this->bindingOfType((string) $type);

            if (! $binding instanceof ResourceBinding || ! $binding->isAccessible()) {
                continue;
            }

            $bindings[$type] = $binding;
            $loaded = $binding->recordsInScope($keys);
            $records[$type] = $loaded->keyBy(static fn (Model $record): string => (string) $record->getKey());
        }

        $items = [];
        $shown = [];

        foreach ($entries as $entry) {
            $record = ($records[$entry['type']] ?? null)?->get($entry['key']);
            $binding = $bindings[$entry['type']] ?? null;

            if (! $record instanceof Model || ! $binding instanceof ResourceBinding) {
                continue;
            }

            $url = $binding->url($record);

            if (blank($url)) {
                continue;
            }

            $title = $binding->title($record);

            $items[] = [
                'type' => $entry['type'],
                'id' => $entry['key'],
                'title' => $title instanceof Htmlable ? strip_tags($title->toHtml()) : $title,
                'url' => $url,
                'details' => $binding->details($record),
                'image' => $binding->image($record),
                'icon' => $this->iconName($binding->icon()),
            ];
            $shown[$entry['type']][] = $record;

            if (count($items) === $limit) {
                break;
            }
        }

        // Only what is shown is rendered.
        foreach ($shown as $type => $shownRecords) {
            $row = $bindings[$type]->paletteRow();

            if ($row instanceof SearchResultRow) {
                $rows[$type] = new PaletteRowRenderer($bindings[$type]->resourceClass(), $row)->render($shownRecords);
            }
        }

        return ['items' => $items, 'rows' => $rows];
    }

    /**
     * The rendered slots of what is on screen, for the resources that describe their rows. A search rendered them
     * already; any other request loads the records again, inside the scope of their resource and only while the user
     * may still open it.
     *
     * @param  list<PaletteGroup>  $groups
     * @return array<string, array<string, RenderedPaletteRow>> By morph alias, then by record key.
     */
    private function rowsOf(array $groups): array
    {
        $rows = [];

        foreach ($groups as $group) {
            $binding = $this->bindingOfType($group['type']);
            $row = $binding?->paletteRow();

            if (! $binding instanceof ResourceBinding || ! $row instanceof SearchResultRow || ! $binding->isAccessible()) {
                continue;
            }

            $known = $this->renderedRows[$group['type']] ?? [];
            $missing = array_values(array_filter(
                array_column($group['items'], 'id'),
                static fn (string $id): bool => ! isset($known[$id]),
            ));

            if ($missing !== []) {
                $known += new PaletteRowRenderer($binding->resourceClass(), $row)->render($binding->recordsInScope($missing));
            }

            $rows[$group['type']] = $known;
        }

        return $rows;
    }

    /**
     * The icon name the way Filament resolves it when it draws an icon, at the size the palette draws it.
     */
    private function iconName(string|BackedEnum|null $icon): ?string
    {
        return match (true) {
            $icon instanceof ScalableIcon => $icon->getIconForSize(IconSize::Small),
            $icon instanceof BackedEnum => (string) $icon->value,
            default => $icon,
        };
    }

    private function relatedTermOf(string $type, string $id): ?string
    {
        foreach ($this->found['groups'] as $group) {
            if ($group['type'] !== $type) {
                continue;
            }

            foreach ($group['items'] as $item) {
                if ($item['id'] === $id) {
                    return $item['relatedTerm'];
                }
            }
        }

        return null;
    }

    private function isFound(string $type, string $id): bool
    {
        if (in_array(['type' => $type, 'id' => $id], $this->recent, true)) {
            return true;
        }

        foreach ($this->found['groups'] as $group) {
            if ($group['type'] !== $type) {
                continue;
            }

            foreach ($group['items'] as $item) {
                if ($item['id'] === $id) {
                    return true;
                }
            }
        }

        return false;
    }

    private function bindingOfType(string $type): ?ResourceBinding
    {
        foreach (resolve(ResourceBindings::class)->all() as $binding) {
            if ($this->alias($binding) === $type) {
                return $binding;
            }
        }

        return null;
    }

    private function alias(ResourceBinding $binding): string
    {
        $modelClass = $binding->modelClass();

        return (new $modelClass)->getMorphClass();
    }

    private function normalizedPhrase(): string
    {
        return mb_substr(trim($this->phrase), 0, self::MAX_LENGTH);
    }

    /**
     * @return PaletteFound
     */
    private function nothing(): array
    {
        return [
            'groups' => [],
            'outcome' => null,
            'stage' => false,
            'timedOut' => false,
            'indexBuilding' => false,
            'total' => 0,
            'capacity' => 0,
        ];
    }

    private function configInt(string $key, int $default): int
    {
        $value = config("filament-model-search.{$key}");

        return is_int($value) ? $value : $default;
    }
}
