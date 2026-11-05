<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Happenv\FilamentModelSearch\Tests\Fixtures\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property list<string> $abilities
 */
final class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];

    #[\Override]
    protected function casts(): array
    {
        return ['abilities' => 'array'];
    }

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }

    /**
     * Whether the user holds an ability such as `orders.viewAny`.
     */
    public function hasAbility(string $ability): bool
    {
        return in_array($ability, $this->abilities, true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
