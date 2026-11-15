<?php

namespace Database\Factories;

use App\Enums\DiscussionStatus;
use App\Models\Discussion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Discussion>
 */
class DiscussionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => ucfirst(fake()->words(4, true)),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'status' => DiscussionStatus::Draft,
        ];
    }
}
