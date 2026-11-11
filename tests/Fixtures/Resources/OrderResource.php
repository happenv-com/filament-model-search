<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Resources;

use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Happenv\FilamentModelSearch\Concerns\InteractsWithModelSearch;
use Happenv\FilamentModelSearch\Palette\SearchResultRow;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource\Pages\EditOrder;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource\Pages\ListOrders;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource\Pages\ViewOrder;
use Happenv\FilamentModelSearch\Tests\Fixtures\Rows\RowLabel;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\OrderSearch;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class OrderResource extends Resource
{
    use InteractsWithModelSearch;

    protected static ?string $model = Order::class;

    protected static ?string $recordTitleAttribute = 'signature';

    #[\Override]
    public static function modelSearch(): string
    {
        return OrderSearch::class;
    }

    /**
     * Tests make the details read a relation through `fixtures.order_details_customer`.
     *
     * @return array<string, string>
     */
    #[\Override]
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return config('fixtures.order_details_customer') === true
            ? ['Customer' => (string) $record->customer?->name]
            : [];
    }

    /**
     * Tests describe the row through `fixtures.order_row` (slot name => columns or a closure, `with` => relations);
     * `fixtures.order_row_returns` makes the method return something else, `fixtures.order_row_label` adds a column
     * reading the injected RowLabel.
     */
    public static function globalSearchResultRow(SearchResultRow $row, RowLabel $label): mixed
    {
        $returned = config('fixtures.order_row_returns');

        if ($returned !== null) {
            return $returned;
        }

        if (config('fixtures.order_row_label') === true) {
            $row->belowTitle([TextColumn::make('label')->getStateUsing(static fn (): string => $label->text)]);
        }

        $slots = config('fixtures.order_row');

        foreach (is_array($slots) ? $slots : [] as $slot => $value) {
            $row->{$slot}($value);
        }

        return $row;
    }

    /**
     * Tests give a result a picture through `fixtures.order_image`.
     */
    public static function getGlobalSearchResultImage(Model $record): ?string
    {
        $image = config('fixtures.order_image');

        return is_string($image) ? $image : null;
    }

    /**
     * Tests leave the resource out of "Search in" through `fixtures.order_offered_as_palette_scope`.
     */
    public static function isOfferedAsPaletteScope(): bool
    {
        return config('fixtures.order_offered_as_palette_scope') !== false;
    }

    /**
     * Tests switch the related search on through `fixtures.order_related_term`: the customer's name, or none.
     */
    public static function getGlobalSearchResultRelatedTerm(Model $record): ?string
    {
        return config('fixtures.order_related_term') === true ? $record->signature : null;
    }

    /**
     * Tests narrow what the resource offers through `fixtures.order_scope`.
     */
    #[\Override]
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        $query = parent::getGlobalSearchEloquentQuery();
        $narrow = config('fixtures.order_scope');

        if (is_callable($narrow)) {
            $query = $narrow($query);
        }

        return config('fixtures.order_details_customer') === true ? $query->with('customer') : $query;
    }

    /**
     * Tests give the resource an icon through `fixtures.order_icon`.
     */
    #[\Override]
    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return config('fixtures.order_icon') ?? parent::getNavigationIcon();
    }

    public static function table(Table $table): Table
    {
        $table = $table->columns([
            TextColumn::make('signature')->searchable()->sortable(),
        ]);

        // Tests shape the table through this hook, before the model search is wired.
        $shape = config('fixtures.order_table');

        if (is_callable($shape)) {
            $table = $shape($table);
        }

        return $table->modelSearch(only: config('fixtures.order_only'));
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
