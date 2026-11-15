<?php

namespace Database\Factories;

use App\Enums\DueType;
use App\Models\Due;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Due>
 */
class DueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Iuran '.fake()->word(),
            'amount' => 50000,
            'type' => DueType::Once,
            'due_date' => now()->addMonth()->toDateString(),
            'applies_to_all' => true,
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn (): array => ['type' => DueType::Monthly]);
    }
}
