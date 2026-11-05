<?php

declare(strict_types=1);

namespace Happenv\FilamentModelSearch\Tests\Fixtures\Factories;

use Happenv\FilamentModelSearch\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret',
            'abilities' => [],
        ];
    }

    /**
     * @param  list<string>  $abilities  For example `orders.viewAny`, `orders.view`, `customers.update`.
     */
    public function withAbilities(array $abilities): self
    {
        return $this->state(['abilities' => $abilities]);
    }
}
