<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Livewire;

use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Livewire\Component;

/**
 * A form over an order whose customer select is shaped by the test through `fixtures.customer_select`.
 */
final class OrderForm extends Component implements HasForms
{
    use InteractsWithForms;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function form(Schema $schema): Schema
    {
        $shape = config('fixtures.customer_select');

        $select = Select::make('customer_id')->relationship('customer', 'name')->searchable();

        return $schema
            ->model(Order::class)
            ->components([is_callable($shape) ? $shape($select) : $select])
            ->statePath('data');
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
