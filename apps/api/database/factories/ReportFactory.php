<?php

namespace Database\Factories;

use App\Enums\ReportContext;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reporter_user_id' => User::factory()->company(),
            'reported_user_id' => User::factory()->candidate(),
            'context' => ReportContext::CARD,
            'reason' => 'INAPPROPRIATE',
            'details' => null,
        ];
    }

    public function handledBy(User $admin): static
    {
        return $this->state(fn () => [
            'handled_by' => $admin->id,
            'handled_at' => now(),
        ]);
    }
}
