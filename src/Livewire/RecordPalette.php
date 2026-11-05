<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Livewire;

use BackedEnum;
use Filament\Facades\Filament;
use Happenv\FilamentModelSearch\Binding\ResourceBinding;
use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Happenv\FilamentModelSearch\Support\HostScope;
use Happenv\FilamentModelSearch\Support\MatchLine;
use Happenv\FilamentModelSearch\Support\PhraseKind;
use Happenv\FilamentModelSearch\Support\SearchOutcome;
use Happenv\ModelSearch\Definition\Mode;
use Happenv\ModelSearch\Definition\ModelSearch;
use Happenv\ModelSearch\Query\SearchQuery;
use Happenv\ModelSearch\Query\SearchResults;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
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
 * @phpstan-type PaletteItem array{id: string, title: string, url: string, exact: bool, hint: array{label: string, value: string}|null}
 * @phpstan-type PaletteGroup array{type: string, label: string, icon: string|null, items: list<PaletteItem>}
 * @phpstan-type PaletteFound array{groups: list<PaletteGroup>, outcome: string|null, stage: bool, timedOut: bool, indexBuilding: bool, total: int, capacity: int}
 */
#[Isolate]
final class RecordPalette extends Component
{
    private const int MIN_LENGTH = 2;

    private const int MAX_LENGTH = 1000;

    public string $phrase = '';

    public bool $everyStage = false;

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

    public function updatedPhrase(): void
    {
        // A new phrase starts over: similar names are asked for, never carried along.
        $this->similar = false;
        $this->search();
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
     * Enter: opens the record when exactly one hit of the whole palette is an exact one.
     */
    public function submit(): void
    {
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

        // The resource's own scope, as a subquery, so an orWhere in it cannot widen the lookup.
        $modelClass = $binding->modelClass();
        $model = new $modelClass;
        $record = $model->newQuery()
            ->whereKey($id)
            ->whereIn($model->getQualifiedKeyName(), HostScope::of($binding->scope())->select($model->getQualifiedKeyName()))
            ->first();

        $url = $record instanceof Model ? $binding->url($record) : null;

        if ($url !== null) {
            $this->redirect($url, navigate: true);
        }
    }

    public function render(): View
    {
        $found = $this->found;

        $phrase = $this->normalizedPhrase();
        $outcome = is_string($found['outcome']) ? SearchOutcome::tryFrom($found['outcome']) : null;

        return view('filament-model-search::livewire.record-palette', [
            'groups' => $found['groups'],
            'outcome' => $outcome,
            'offersEveryStage' => $found['stage'] && ! $this->everyStage && ! $this->similar,
            'offersSimilar' => $this->offersSimilar(),
            'timedOut' => $found['timedOut'],
            'indexBuilding' => $found['indexBuilding'],
            'isShort' => mb_strlen($phrase) < self::MIN_LENGTH,
            'debounce' => max(0, $this->configInt('palette.debounce_ms', 250)),
            'keyBindings' => $this->keyBindings(),
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

    private function search(): void
    {
        $phrase = $this->normalizedPhrase();

        if (mb_strlen($phrase) < self::MIN_LENGTH) {
            $this->found = $this->nothing();

            return;
        }

        // Asked now, never remembered: the user may have changed since the palette was opened.
        $bindings = array_values(array_filter(
            resolve(ResourceBindings::class)->all(),
            static fn (ResourceBinding $binding): bool => $binding->isAccessible(),
        ));

        if ($bindings === []) {
            $this->found = [...$this->nothing(), 'outcome' => SearchOutcome::Unavailable->value];

            return;
        }

        $perGroup = max(1, $this->configInt('palette.per_group', 5));

        try {
            $answers = $this->answers($bindings, $phrase, $perGroup);
        } catch (Throwable $exception) {
            $outcome = SearchOutcome::fromException($exception);

            if (! $outcome instanceof SearchOutcome) {
                throw $exception;
            }

            $this->found = [...$this->nothing(), 'outcome' => $outcome->value, 'capacity' => count($bindings) * $perGroup];

            return;
        }

        $this->found = $this->present($bindings, $answers, $perGroup);
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
     * @return PaletteFound
     */
    private function present(array $bindings, array $answers, int $perGroup): array
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

            $stage = $stage || $results->stage !== null;
            $timedOut = $timedOut || $results->timedOut;
            $indexBuilding = $indexBuilding || $results->indexBuilding;

            $group = $this->group($binding, $results);

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
    private function group(ResourceBinding $binding, SearchResults $results): array
    {
        $items = [];

        foreach ($results as $result) {
            $url = $binding->url($result->model);

            if (blank($url)) {
                continue;
            }

            $title = $binding->title($result->model);
            $title = $title instanceof Htmlable ? strip_tags($title->toHtml()) : $title;
            $line = MatchLine::of($result->match, $title);
            $via = $result->match->via;

            $items[] = [
                'id' => (string) $result->model->getKey(),
                'title' => $title,
                'url' => $url,
                'exact' => $result->match->mode === Mode::Exact,
                'hint' => $line === null ? null : [
                    'label' => $via === null ? $line[0] : Str::headline($via).' › '.$line[0],
                    'value' => $line[1],
                ],
            ];
        }

        $icon = $binding->icon();

        return [
            'type' => $this->alias($binding),
            'label' => $binding->label(),
            'icon' => $icon instanceof BackedEnum ? (string) $icon->value : $icon,
            'items' => $items,
        ];
    }

    private function isFound(string $type, string $id): bool
    {
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

    /**
     * The keys that open the palette, as the keybinding directive writes them (`mod+shift+k` is `mod-shift-k`).
     *
     * @return list<string>
     */
    private function keyBindings(): array
    {
        $configured = config('filament-model-search.palette.keybindings');
        $bindings = [];

        foreach (is_array($configured) ? $configured : [] as $binding) {
            $clean = is_string($binding) ? preg_replace('/[^A-Za-z0-9-]/', '', str_replace('+', '-', $binding)) : null;

            if (is_string($clean) && $clean !== '') {
                $bindings[] = $clean;
            }
        }

        return $bindings;
    }
}
