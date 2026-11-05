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

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('signature')->searchable(),
        ]);
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
