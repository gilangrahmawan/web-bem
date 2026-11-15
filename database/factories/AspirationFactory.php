<?php

namespace Database\Factories;

use App\Enums\AspirationCategory;
use App\Enums\AspirationStatus;
use App\Models\Aspiration;
use App\Support\TrackingCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Aspiration>
 */
class AspirationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tracking_code' => TrackingCode::generate('ASP', fn (string $c) => Aspiration::where('tracking_code', $c)->exists()),
            'name' => fake()->name(),
            'contact' => fake()->safeEmail(),
            'is_anonymous' => false,
            'category' => fake()->randomElement(AspirationCategory::cases()),
            'body' => fake()->paragraph(),
            'status' => AspirationStatus::New,
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn (): array => ['name' => null, 'contact' => null, 'is_anonymous' => true]);
    }
}
