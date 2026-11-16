<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\Recruitment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recruitment>
 */
class RecruitmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Oprec Gelombang '.fake()->numberBetween(1, 9),
            'opens_at' => now()->subDays(2),
            'closes_at' => now()->addDays(10),
            'open_divisions' => Division::query()->whereIn('slug', ['kajian', 'medpub', 'humas'])->pluck('id')->all(),
            'is_active' => true,
        ];
    }

    public function closed(?string $closedAt = null): static
    {
        return $this->state(fn (): array => [
            'opens_at' => now()->subMonths(2),
            'closes_at' => $closedAt ?? now()->subMonth(),
        ]);
    }
}
