<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Palette;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\Contracts\Editable;
use Filament\Tables\Columns\Layout\Component as LayoutComponent;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use LogicException;

/**
 * Renders the slots of a palette row for the records of one resource, outside a table.
 *
 * Columns need a table, and a table needs a Livewire component to belong to. The palette is not one (its state
 * is sent to the browser and back on every request, and a table's would travel with it), so the columns are given a
 * host that is never mounted: it only answers what a column asks of its table.
 *
 * Nothing here queries: the records come with what the columns read already loaded.
 */
final class PaletteRowRenderer
{
    private ?Table $table = null;

    /**
     * @param  class-string  $resourceClass  Named in the error when a column cannot be shown.
     */
    public function __construct(
        private readonly string $resourceClass,
        private readonly SearchResultRow $row,
    ) {}

    /**
     * @param  iterable<Model>  $records
     * @return array<string, RenderedPaletteRow> By record key.
     */
    public function render(iterable $records): array
    {
        // One set of components for the whole group, handed each record in turn the way a table hands them its rows.
        $slots = [
            'leading' => array_values($this->row->leading()),
            'aboveTitle' => array_values($this->row->aboveTitle()),
            'besideTitle' => array_values($this->row->besideTitle()),
            'belowTitle' => array_values($this->row->belowTitle()),
            'trailing' => array_values($this->row->trailing()),
        ];
        $table = $this->table();

        foreach ($slots as $components) {
            foreach ($components as $component) {
                $component->table($table);
            }
        }

        $rendered = [];

        foreach ($records as $record) {
            $key = (string) $record->getKey();

            $rendered[$key] = new RenderedPaletteRow(
                leading: $this->renderSlot($slots['leading'], $record, $key),
                aboveTitle: $this->renderSlot($slots['aboveTitle'], $record, $key),
                besideTitle: $this->renderSlot($slots['besideTitle'], $record, $key),
                belowTitle: $this->renderSlot($slots['belowTitle'], $record, $key),
                trailing: $this->renderSlot($slots['trailing'], $record, $key),
            );
        }

        return $rendered;
    }

    /**
     * @param  list<Column|LayoutComponent>  $components
     */
    private function renderSlot(array $components, Model $record, string $key): string
    {
        $html = '';

        foreach ($components as $component) {
            $component->record($record)->recordKey($key);

            $this->refuseInteractive($component);

            $html .= $component->renderInLayout()?->toHtml() ?? '';
        }

        return trim($html);
    }

    /**
     * The row is one link: a column that is a link or a button, or takes input, would nest inside it and take the
     * keyboard away from it.
     */
    private function refuseInteractive(Column|LayoutComponent $component): void
    {
        if ($component instanceof LayoutComponent) {
            foreach ($component->getComponents() as $child) {
                $this->refuseInteractive($child);
            }

            return;
        }

        $reason = match (true) {
            $component instanceof Editable => 'is editable',
            $component->isClickDisabled() => null,
            $component->getAction() !== null => 'calls an action',
            $component->hasStateBasedUrls(), filled($component->getUrl()) => 'opens a link',
            default => null,
        };

        if ($reason !== null) {
            throw new LogicException(sprintf(
                'The palette row of [%s] contains the column [%s], which %s. The row is itself the link to the record, so its columns can only display: remove url(), action() and editing, or call disabledClick().',
                $this->resourceClass,
                $component->getName(),
                $reason,
            ));
        }
    }

    private function table(): Table
    {
        if ($this->table instanceof Table) {
            return $this->table;
        }

        $host = new class extends Component implements HasActions, HasSchemas, HasTable
        {
            use InteractsWithActions;
            use InteractsWithSchemas;
            use InteractsWithTable;

            public function render(): string
            {
                return '<div></div>';
            }
        };

        $host->setId('filament-model-search-palette-rows');

        $table = Table::make($host);

        // `getTable()` of the host reads this property, which only a mounted component fills.
        (function () use ($table): void {
            $this->table = $table;
        })->call($host);

        return $this->table = $table;
    }
}
