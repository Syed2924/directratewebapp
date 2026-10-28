<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public const PASSWORD = 'Secret#Pass123';

    public function definition()
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt(self::PASSWORD),
            'status' => 'active',
            'must_change_password' => false,
            'password_changed_at' => now(),
        ];
    }

    public function inactive()
    {
        return $this->state(fn () => ['status' => 'inactive', 'deactivated_at' => now(), 'deactivation_reason' => 'Test']);
    }

    public function pending()
    {
        return $this->state(fn () => ['status' => 'pending', 'must_change_password' => true, 'password_changed_at' => null]);
    }
}
