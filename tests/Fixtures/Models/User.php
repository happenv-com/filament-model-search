<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $can_view_any
 */
final class User extends Authenticatable implements FilamentUser
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];

    #[\Override]
    protected function casts(): array
    {
        return ['can_view_any' => 'boolean'];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
