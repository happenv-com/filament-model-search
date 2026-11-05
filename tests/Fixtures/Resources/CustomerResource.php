<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Resources;

use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Happenv\FilamentModelSearch\Concerns\InteractsWithModelSearch;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Customer;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\CustomerResource\Pages\EditCustomer;
use Happenv\FilamentModelSearch\Tests\Fixtures\Resources\CustomerResource\Pages\ListCustomers;
use Happenv\FilamentModelSearch\Tests\Fixtures\Searches\CustomerSearch;

final class CustomerResource extends Resource
{
    use InteractsWithModelSearch;

    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    #[\Override]
    public static function modelSearch(): string
    {
        return CustomerSearch::class;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
        ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
