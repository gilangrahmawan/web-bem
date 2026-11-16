<?php

namespace Database\Factories;

use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'UKM '.ucfirst(fake()->unique()->word()).' '.fake()->unique()->numberBetween(1, 99999),
            'type' => OrganizationType::Ukm,
            'is_active' => true,
        ];
    }
}
