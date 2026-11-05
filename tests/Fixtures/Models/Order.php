<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $signature
 * @property string|null $external_number
 * @property string|null $customer_email
 * @property string|null $customer_name
 * @property int|null $customer_id
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

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * A relationship whose related key is not the primary key.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customerByCode(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_code', 'code');
    }
}
