<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Policies;

use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Order;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\User;

final class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('orders.viewAny');
    }

    public function view(User $user, Order $record): bool
    {
        return $user->hasAbility('orders.view');
    }

    public function update(User $user, Order $record): bool
    {
        return $user->hasAbility('orders.update');
    }
}
