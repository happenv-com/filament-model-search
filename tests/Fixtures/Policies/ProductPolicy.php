<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Policies;

use Happenv\FilamentModelSearch\Tests\Fixtures\Models\Product;
use Happenv\FilamentModelSearch\Tests\Fixtures\Models\User;

final class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('products.viewAny');
    }

    public function view(User $user, Product $record): bool
    {
        return $user->hasAbility('products.view');
    }

    public function update(User $user, Product $record): bool
    {
        return $user->hasAbility('products.update');
    }
}
