<?php

namespace Database\Factories;

use App\Enums\ProgramStatus;
use App\Models\Division;
use App\Models\Period;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'period_id' => Period::factory(),
            'title' => ucfirst(fake()->words(4, true)),
            'description' => fake()->sentence(12),
            'status' => fake()->randomElement(ProgramStatus::cases()),
        ];
    }
}
