<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property bool $active
 */
final class Customer extends Model
{
    protected $table = 'customers';

    protected $guarded = [];

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
