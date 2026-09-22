<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'role' => fake()->randomElement(UserRole::cases()),
        ];
    }

    public function candidate(): static
    {
        return $this->state(fn () => ['role' => UserRole::CANDIDATE]);
    }

    public function company(): static
    {
        return $this->state(fn () => ['role' => UserRole::COMPANY]);
    }

    public function cfa(): static
    {
        return $this->state(fn () => ['role' => UserRole::CFA]);
    }

    public function staff(): static
    {
        return $this->state(fn () => ['role' => UserRole::STAFF]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::ADMIN]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['is_suspended' => true]);
    }
}
