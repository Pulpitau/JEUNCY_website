<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBlock>
 */
class UserBlockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'blocker_user_id' => User::factory()->candidate(),
            'blocked_user_id' => User::factory()->company(),
        ];
    }
}
