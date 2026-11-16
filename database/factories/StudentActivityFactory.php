<?php

namespace Database\Factories;

use App\Enums\ActivityStatus;
use App\Models\Organization;
use App\Models\StudentActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Catatan: menulis langsung ke tabel (tanpa ActivityWorkflowService).
 *
 * @extends Factory<StudentActivity>
 */
class StudentActivityFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(10, 40))->startOfDay();

        return [
            'organization_id' => Organization::factory(),
            'name' => 'Kegiatan '.fake()->words(3, true),
            'pic_user_id' => User::factory(),
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->copy()->addDay()->toDateString(),
            'location' => 'Aula Kampus',
            'description' => fake()->sentence(),
            'budget_estimate' => 2500000,
            'status' => ActivityStatus::Draft,
        ];
    }

    public function status(ActivityStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
