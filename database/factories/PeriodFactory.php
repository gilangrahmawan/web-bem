<?php

namespace Database\Factories;

use App\Models\Period;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Period>
 */
class PeriodFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 years', 'now');

        return [
            'name' => 'Kabinet '.fake()->unique()->word(),
            'starts_on' => $start,
            'ends_on' => (clone $start)->modify('+1 year'),
        ];
    }

    public function active(): static
    {
        return $this->afterCreating(fn (Period $period) => $period->forceFill(['is_active' => true])->save());
    }
}
