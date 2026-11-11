<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Palette;

use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\Layout\Component;

/**
 * How the record palette shows the records of one resource, described with Filament table columns.
 *
 * A resource opts in with the optional static `getGlobalSearchResultRow(): ?string`, which returns the class of a
 * subclass of this one. The skeleton of a row stays the package's own (one option per record, its title, the line
 * telling why it was found, the related search): the slots fill the places around the title.
 *
 * ```
 * [leading] [aboveTitle / title + besideTitle / belowTitle] [trailing] [hint] [related search]
 * ```
 *
 * A slot left empty falls back to what the palette shows without a row class: `leading` to the resource's image or
 * icon, `belowTitle` to the details line of the resource's global search, the others to nothing.
 *
 * Columns are only displayed: one that is editable, or opens a link or calls an action, is refused when a record is
 * rendered, because it would live inside an option that is itself the link.
 */
abstract class SearchResultRow
{
    /**
     * Relations to eager load for the records found, once for all the records of a group.
     *
     * @return array<int, string>
     */
    public function with(): array
    {
        return [];
    }

    /**
     * Replaces the image and the icon in front of the title.
     *
     * @return array<Column|Component>
     */
    public function leading(): array
    {
        return [];
    }

    /**
     * A line over the title.
     *
     * @return array<Column|Component>
     */
    public function aboveTitle(): array
    {
        return [];
    }

    /**
     * Next to the title, on its line: a badge, for example.
     *
     * @return array<Column|Component>
     */
    public function besideTitle(): array
    {
        return [];
    }

    /**
     * Replaces the details line under the title.
     *
     * @return array<Column|Component>
     */
    public function belowTitle(): array
    {
        return [];
    }

    /**
     * At the end of the row, before the line telling why the record was found. Under `belowTitle` on a narrow screen.
     *
     * @return array<Column|Component>
     */
    public function trailing(): array
    {
        return [];
    }
}
