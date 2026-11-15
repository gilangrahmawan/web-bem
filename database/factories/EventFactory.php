<?php

namespace Database\Factories;

use App\Enums\EventCategory;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Catatan: factory menulis langsung ke tabel (tanpa EventService),
 * sehingga has_conflict tidak dihitung otomatis.
 *
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $category = fake()->randomElement(EventCategory::cases());

        return [
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(),
            'category' => $category,
            'visibility' => $category->visibility(),
            'division_id' => null,
            'starts_at' => now()->addDays(fake()->numberBetween(1, 30))->startOfDay(),
            'ends_at' => null,
            'all_day' => true,
            'has_conflict' => false,
        ];
    }

    public function category(EventCategory $category): static
    {
        return $this->state(fn (): array => [
            'category' => $category,
            'visibility' => $category->visibility(),
        ]);
    }

    public function on(string $date): static
    {
        return $this->state(fn (): array => ['starts_at' => $date, 'ends_at' => null]);
    }
}
