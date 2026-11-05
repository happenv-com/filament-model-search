<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Policies;

use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Customer;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\User;

final class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('customers.viewAny');
    }

    public function view(User $user, Customer $record): bool
    {
        return $user->hasAbility('customers.view');
    }

    public function update(User $user, Customer $record): bool
    {
        return $user->hasAbility('customers.update');
    }
}
