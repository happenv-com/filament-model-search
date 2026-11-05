<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $signature
 * @property string|null $external_number
 * @property string|null $customer_email
 * @property string|null $customer_name
 * @property string $state
 */
final class Order extends Model
{
    use HasUuids;

    protected $table = 'orders';

    protected $guarded = [];

    #[\Override]
    protected function casts(): array
    {
        return ['purchased_at' => 'datetime'];
    }
}
