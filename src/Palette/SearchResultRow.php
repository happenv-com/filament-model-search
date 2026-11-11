<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Palette;

use Closure;
use Filament\Support\Concerns\EvaluatesClosures;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\Layout\Component;
use LogicException;

/**
 * How the record palette shows the records of one resource, described with Filament table columns.
 *
 * A resource opts in with an optional static method, the way it describes its table:
 *
 * ```php
 * public static function globalSearchResultRow(SearchResultRow $row): SearchResultRow
 * {
 *     return $row
 *         ->with(['channel'])
 *         ->besideTitle([TextColumn::make('state')->badge()])
 *         ->trailing(fn (SearchResultRow $row): array => [TextColumn::make('total')->money('EUR')]);
 * }
 * ```
 *
 * The skeleton of a row stays the package's own (one option per record, its title, the line telling why it was
 * found, the related search): the slots fill the places around the title.
 *
 * ```
 * [leading] [aboveTitle / title + besideTitle / belowTitle] [trailing] [hint] [related search]
 * ```
 *
 * A row returned as it was given is the palette's default look. A slot left empty falls back to what the palette
 * shows without a row: `leading` to the resource's image or icon,
 * `belowTitle` to the details line of the resource's global search, the others to nothing.
 *
 * Every slot takes the columns, or a closure returning them that is given this row as `$row`. The columns are made
 * once for all the records of a group and handed each record in turn, like a table's: what differs between records is
 * their state (`->state(fn (Order $record) => …)`), never which columns there are.
 *
 * Columns are only displayed: one that is editable, or opens a link or calls an action, is refused when a record is
 * rendered, because it would live inside an option that is itself the link.
 */
final class SearchResultRow
{
    use EvaluatesClosures;

    /** Whether anything was asked of the row: one returned as it was given is the palette's default look. */
    private bool $configured = false;

    /** @var array<int, string>|Closure */
    private array|Closure $with = [];

    /** @var array<string, array<Column|Component>|Closure|null> */
    private array $slots = [
        'leading' => null,
        'aboveTitle' => null,
        'besideTitle' => null,
        'belowTitle' => null,
        'trailing' => null,
    ];

    public function __construct()
    {
        // A closure of a slot gets this row as `$row`, or by its type.
        $this->evaluationIdentifier = 'row';
    }

    public static function make(): self
    {
        return new self;
    }

    /**
     * Relations to eager load for the records found, once for all the records of a group. Only eager loads: the
     * records are the ones the search found and the user may open, and nothing here may filter them away.
     *
     * @param  array<int, string>|Closure  $relations
     */
    public function with(array|Closure $relations): self
    {
        $this->with = $relations;
        $this->configured = true;

        return $this;
    }

    /**
     * Replaces the image and the icon in front of the title.
     *
     * @param  array<Column|Component>|Closure|null  $columns
     */
    public function leading(array|Closure|null $columns): self
    {
        return $this->slot('leading', $columns);
    }

    /**
     * A line over the title.
     *
     * @param  array<Column|Component>|Closure|null  $columns
     */
    public function aboveTitle(array|Closure|null $columns): self
    {
        return $this->slot('aboveTitle', $columns);
    }

    /**
     * Next to the title, on its line: a badge, for example.
     *
     * @param  array<Column|Component>|Closure|null  $columns
     */
    public function besideTitle(array|Closure|null $columns): self
    {
        return $this->slot('besideTitle', $columns);
    }

    /**
     * Replaces the details line under the title.
     *
     * @param  array<Column|Component>|Closure|null  $columns
     */
    public function belowTitle(array|Closure|null $columns): self
    {
        return $this->slot('belowTitle', $columns);
    }

    /**
     * At the end of the row, before the line telling why the record was found. Under `belowTitle` on a narrow screen.
     *
     * @param  array<Column|Component>|Closure|null  $columns
     */
    public function trailing(array|Closure|null $columns): self
    {
        return $this->slot('trailing', $columns);
    }

    /**
     * Whether the resource asked anything of the row. One returned untouched shows the records the default way.
     */
    public function isConfigured(): bool
    {
        return $this->configured;
    }

    /**
     * @return array<int, string>
     */
    public function getWith(): array
    {
        $relations = $this->evaluate($this->with);

        if (! is_array($relations)) {
            throw new LogicException(sprintf('The relations of a search result row must be an array, [%s] given.', get_debug_type($relations)));
        }

        return array_values($relations);
    }

    /**
     * @return list<Column|Component>
     */
    public function getLeading(): array
    {
        return $this->getSlot('leading');
    }

    /**
     * @return list<Column|Component>
     */
    public function getAboveTitle(): array
    {
        return $this->getSlot('aboveTitle');
    }

    /**
     * @return list<Column|Component>
     */
    public function getBesideTitle(): array
    {
        return $this->getSlot('besideTitle');
    }

    /**
     * @return list<Column|Component>
     */
    public function getBelowTitle(): array
    {
        return $this->getSlot('belowTitle');
    }

    /**
     * @return list<Column|Component>
     */
    public function getTrailing(): array
    {
        return $this->getSlot('trailing');
    }

    /**
     * @param  array<Column|Component>|Closure|null  $columns
     */
    private function slot(string $name, array|Closure|null $columns): self
    {
        $this->slots[$name] = $columns;
        $this->configured = true;

        return $this;
    }

    /**
     * @return list<Column|Component>
     */
    private function getSlot(string $name): array
    {
        $columns = $this->evaluate($this->slots[$name]);

        if ($columns === null) {
            return [];
        }

        if (! is_array($columns)) {
            throw new LogicException(sprintf('The [%s] slot of a search result row must give an array of table columns, [%s] given.', $name, get_debug_type($columns)));
        }

        foreach ($columns as $column) {
            if (! $column instanceof Column && ! $column instanceof Component) {
                throw new LogicException(sprintf('The [%s] slot of a search result row holds [%s], which is not a table column or a column layout.', $name, get_debug_type($column)));
            }
        }

        return array_values($columns);
    }
}
