<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Resources;

use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Product;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\ProductResource\Pages\ListProducts;

final class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('sku')->searchable(),
        ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
        ];
    }
}
