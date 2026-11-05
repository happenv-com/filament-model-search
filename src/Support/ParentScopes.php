<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Support;

use Happenv\FilamentModelSearch\Binding\ResourceBindings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The scopes of the parents a `via` field may follow: what the other resources of the current panel offer
 * the current user.
 *
 * A search reaches a parent only through that parent's scope. A parent the user may not open is left out,
 * so a record cannot be found through a record the user is not allowed to see. Computed on every search:
 * the bindings serve many users.
 */
final class ParentScopes
{
    /**
     * @param  class-string<Model>  $exceptModel  The model being searched, which is no parent of itself.
     * @return array<class-string<Model>, Builder<Model>> The `via` argument of `within()`.
     */
    public static function for(string $exceptModel): array
    {
        $scopes = [];

        foreach (resolve(ResourceBindings::class)->all() as $binding) {
            if ($binding->modelClass() === $exceptModel || ! $binding->isAccessible()) {
                continue;
            }

            $scopes[$binding->modelClass()] = HostScope::of($binding->scope());
        }

        return $scopes;
    }
}
