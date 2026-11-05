<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Resources;

use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Happenv\FilamentModelSearch\Concerns\InteractsWithModelSearch;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource\Pages\EditOrder;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource\Pages\ListOrders;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\OrderResource\Pages\ViewOrder;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\OrderSearch;
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
