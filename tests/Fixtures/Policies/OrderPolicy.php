<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Policies;

use Happenv\FilamentModelSearch\Tests\Fixtures\Models\User;

final class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can_view_any;
    }
}
