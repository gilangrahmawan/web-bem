<?php

namespace Database\Factories;

use App\Models\Gallery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Gallery>
 */
class GalleryFactory extends Factory
{
    public function definition(): array
    {
        $title = Str::title(fake()->words(3, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'event_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'description' => fake()->sentence(),
        ];
    }
}
